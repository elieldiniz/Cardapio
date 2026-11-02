<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * A dono sees and handles only the orders of their own restaurant (RNF-01).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() && $user->restaurant_id !== null;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->restaurant_id !== null && $user->restaurant_id === $order->restaurant_id;
    }
}
