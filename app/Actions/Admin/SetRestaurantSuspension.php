<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspends or reactivates a restaurant (US-7.2): a suspended restaurant's
 * panel and public feed become unavailable. Every toggle is audited.
 */
class SetRestaurantSuspension
{
    public function handle(User $admin, Restaurant $restaurant, bool $suspend): void
    {
        DB::transaction(function () use ($admin, $restaurant, $suspend) {
            $restaurant->update(['status_id' => RestaurantStatus::idFor($suspend ? 'suspenso' : 'ativo')]);

            AdminLog::record(
                $admin,
                $suspend ? 'restaurante_suspenso' : 'restaurante_reativado',
                "restaurant:{$restaurant->id}",
                ['name' => $restaurant->name],
            );
        });
    }
}
