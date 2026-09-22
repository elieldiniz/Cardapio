<?php

use App\Models\User;

test('a dono user cannot access the admin panel', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)->get('/admin')->assertForbidden();
});

test('a super admin user can access the admin panel', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
});
