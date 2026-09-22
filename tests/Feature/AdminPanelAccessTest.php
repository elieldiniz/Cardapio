<?php

use App\Models\User;

it('does not let a dono user access the admin panel', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)->get('/admin')->assertForbidden();
});

it('lets a super admin user access the admin panel', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
});
