<?php

namespace App\Actions\Couriers;

use App\Models\Courier;

/**
 * Revokes a courier's link (it stops opening at once) or makes a new one, which replaces the old.
 */
class RotateCourierLink
{
    public function revoke(Courier $courier): Courier
    {
        $courier->update(['revoked_at' => now()]);

        return $courier;
    }

    public function regenerate(Courier $courier): Courier
    {
        $courier->update(['token' => Courier::newToken(), 'revoked_at' => null]);

        return $courier;
    }
}
