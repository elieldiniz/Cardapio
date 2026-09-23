<?php

use App\Models\User;

it('provisions a super admin without a restaurant', function () {
    seedReferenceData();

    $this->artisan('app:create-super-admin', ['email' => 'admin@degustou.test', '--password' => 'segredo123'])
        ->assertSuccessful();

    $admin = User::where('email', 'admin@degustou.test')->sole();

    expect($admin->isSuperAdmin())->toBeTrue()
        ->and($admin->restaurant_id)->toBeNull();

    $this->artisan('app:create-super-admin', ['email' => 'admin@degustou.test', '--password' => 'segredo123'])
        ->assertFailed();
});
