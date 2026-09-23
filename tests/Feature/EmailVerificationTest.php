<?php

use App\Actions\Admin\Impersonation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(fn () => seedReferenceData());

it('emails a verification link on signup and locks the panel until it is used', function () {
    Notification::fake();

    Livewire::test('pages::auth.register')
        ->set('name', 'Ana')
        ->set('restaurant_name', 'Fumaça')
        ->set('email', 'ana@fumaca.com')
        ->set('password', 'segredo123')
        ->call('register');

    $user = User::where('email', 'ana@fumaca.com')->sole();

    Notification::assertSentTo($user, VerifyEmail::class);
    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user)->get(route('panel.home'))->assertRedirect(route('verification.notice'));
    $this->get(route('verification.notice'))->assertOk()->assertSee('Confirme seu e-mail');

    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($link)->assertRedirect(route('panel.home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get(route('panel.home'))->assertOk();
});

it('lets a super admin impersonate an unverified dono for support', function () {
    $dono = User::factory()->unverified()->create();

    $this->actingAs(User::factory()->superAdmin()->create());
    app(Impersonation::class)->start(auth()->user(), $dono->restaurant);

    $this->get(route('panel.home'))->assertOk();
});
