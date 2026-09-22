<?php

namespace Database\Seeders;

use App\Models\Role;

class RoleSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return Role::class;
    }

    protected function rows(): array
    {
        return [
            'dono' => 'Dono',
            'super_admin' => 'Super Admin',
        ];
    }
}
