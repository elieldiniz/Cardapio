<?php

use App\Models\User;

test('logging out invalidates the session and protected routes redirect to login', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)
        ->get(route('panel.home'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false);

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('panel.home'))->assertRedirect(route('login'));
});
