<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create(['email' => 'ana@fumaca.com']);
    $this->actingAs($this->dono);
});

it('is reachable from the sidebar', function () {
    $this->get(route('panel.home'))->assertSee(route('panel.account'), false)->assertSee('Minha conta');
    $this->get(route('panel.account'))->assertOk()->assertSee('Trocar senha')->assertSee('Excluir conta');
});

it('updates the name without asking for the password', function () {
    Livewire::test('pages::panel.account')
        ->set('name', 'Ana Souza')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($this->dono->fresh()->name)->toBe('Ana Souza');
});

it('requires the current password to change the e-mail and asks to verify the new one', function () {
    Notification::fake();

    Livewire::test('pages::panel.account')
        ->set('email', 'novo@fumaca.com')
        ->call('updateProfile')
        ->assertHasErrors('email_password');

    Livewire::test('pages::panel.account')
        ->set('email', 'novo@fumaca.com')
        ->set('email_password', 'password')
        ->call('updateProfile')
        ->assertRedirect(route('verification.notice'));

    $user = $this->dono->fresh();

    expect($user->email)->toBe('novo@fumaca.com')->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('refuses an e-mail that belongs to another account', function () {
    User::factory()->create(['email' => 'outro@fumaca.com']);

    Livewire::test('pages::panel.account')
        ->set('email', 'outro@fumaca.com')
        ->set('email_password', 'password')
        ->call('updateProfile')
        ->assertHasErrors(['email' => 'unique']);
});

it('changes the password only with the correct current password', function () {
    Livewire::test('pages::panel.account')
        ->set('current_password', 'errada')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nova-senha-123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    Livewire::test('pages::panel.account')
        ->set('current_password', 'password')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nova-senha-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('nova-senha-123', $this->dono->fresh()->password))->toBeTrue();
});

it('renames the restaurant without changing its link or qr code', function () {
    $slug = $this->dono->restaurant->slug;

    Livewire::test('pages::panel.account')
        ->set('restaurant_name', 'Fumaça Grill')
        ->call('updateRestaurant')
        ->assertHasNoErrors();

    expect($this->dono->restaurant->fresh())->name->toBe('Fumaça Grill')->slug->toBe($slug);
});

it('keeps the video-ready e-mail off by default and lets the dono turn it on', function () {
    expect($this->dono->fresh()->notify_video_ready)->toBeFalse();

    Livewire::test('pages::panel.account')->set('notify_video_ready', true);

    expect($this->dono->fresh()->notify_video_ready)->toBeTrue();
});
