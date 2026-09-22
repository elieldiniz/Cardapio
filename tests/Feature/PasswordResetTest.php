<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

test('a reset link is emailed for an existing account', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'dono@example.com']);

    Livewire::test('pages::auth.forgot-password')
        ->set('email', 'dono@example.com')
        ->call('sendResetLink')
        ->assertSet('status', 'Se este e-mail estiver cadastrado, você receberá um link para redefinir sua senha. O link vale por 60 minutos.');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        return str_contains($mail->actionUrl, '/redefinir-senha/'.$notification->token)
            && in_array('Este link expira em 60 minutos.', array_merge($mail->introLines, $mail->outroLines), true);
    });

    expect(config('auth.passwords.users.expire'))->toBe(60);
});

test('requesting a reset for an unknown email shows the same confirmation message', function () {
    Notification::fake();
    User::factory()->create(['email' => 'dono@example.com']);

    $known = Livewire::test('pages::auth.forgot-password')
        ->set('email', 'dono@example.com')
        ->call('sendResetLink');

    $unknown = Livewire::test('pages::auth.forgot-password')
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink')
        ->assertHasNoErrors();

    expect($unknown->get('status'))->toBe($known->get('status'));
});

test('a valid token sets the new password and revokes existing sessions', function () {
    $user = User::factory()->create(['email' => 'dono@example.com']);
    $oldRememberToken = $user->remember_token;

    DB::table('sessions')->insert([
        ['id' => 'phone-session', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'laptop-session', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    $token = Password::createToken($user);

    Livewire::test('pages::auth.reset-password', ['token' => $token])
        ->set('email', 'dono@example.com')
        ->set('password', 'nova-senha-123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect(Hash::check('nova-senha-123', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldRememberToken)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('an expired or reused token is rejected', function () {
    $user = User::factory()->create(['email' => 'dono@example.com']);
    $token = Password::createToken($user);

    Livewire::test('pages::auth.reset-password', ['token' => $token])
        ->set('email', 'dono@example.com')
        ->set('password', 'nova-senha-123')
        ->call('resetPassword')
        ->assertHasNoErrors();

    // Reusing the same token fails.
    Livewire::test('pages::auth.reset-password', ['token' => $token])
        ->set('email', 'dono@example.com')
        ->set('password', 'outra-senha-456')
        ->call('resetPassword')
        ->assertHasErrors('email');

    // An expired token fails.
    $expired = Password::createToken($user);
    $this->travel(61)->minutes();

    Livewire::test('pages::auth.reset-password', ['token' => $expired])
        ->set('email', 'dono@example.com')
        ->set('password', 'outra-senha-456')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('nova-senha-123', $user->fresh()->password))->toBeTrue();
});
