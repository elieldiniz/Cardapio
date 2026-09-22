<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a user lands after authenticating, based on their role (US-8.2).
 */
class HomeRedirect
{
    public static function for(User $user): string
    {
        return $user->isSuperAdmin() ? url('/admin') : route('panel.home');
    }
}
