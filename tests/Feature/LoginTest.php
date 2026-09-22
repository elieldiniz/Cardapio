<?php

use App\Models\User;
use Livewire\Livewire;

test('a dono is redirected to the restaurant panel after login', function () {
    $dono = User::factory()->create(['email' => 'dono@example.com']);

    Livewire::test('pages::auth.login')
        ->set('email', 'dono@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('panel.home'));

    $this->assertAuthenticatedAs($dono);
});

test('a super admin is redirected to the admin panel after login', function () {
    $admin = User::factory()->superAdmin()->create(['email' => 'admin@example.com']);

    Livewire::test('pages::auth.login')
        ->set('email', 'admin@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(url('/admin'));

    $this->assertAuthenticatedAs($admin);
});

test('invalid credentials show a generic error without revealing account existence', function () {
    User::factory()->create(['email' => 'dono@example.com']);

    $wrongPassword = Livewire::test('pages::auth.login')
        ->set('email', 'dono@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $unknownEmail = Livewire::test('pages::auth.login')
        ->set('email', 'nobody@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    expect($wrongPassword->errors()->first('email'))
        ->toBe('E-mail ou senha inválidos.')
        ->toBe($unknownEmail->errors()->first('email'));

    $this->assertGuest();
});

test('a guest visiting the panel is sent to the login screen', function () {
    $this->get(route('panel.home'))->assertRedirect(route('login'));
});
