<?php

namespace App\Policies;

use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * A dono manages only the order settings of their own restaurant (RNF-01).
 */
class RestaurantOrderSettingPolicy
{
    public function update(User $user, RestaurantOrderSetting $setting): bool
    {
        return $user->isOwner() && $user->restaurant_id !== null && $user->restaurant_id === $setting->restaurant_id;
    }
}
