<?php

namespace App\Actions\Admin;

use App\Jobs\PurgeRestaurant;
use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\User;
use InvalidArgumentException;

/**
 * Permanently deletes a restaurant the dono already deleted (in the trash).
 * The heavy lifting — Mux assets, files, every row — runs on the queue.
 */
class PurgeRestaurantAccount
{
    public function handle(User $admin, Restaurant $restaurant): void
    {
        if (! $restaurant->trashed()) {
            throw new InvalidArgumentException('Only a deleted (trashed) restaurant can be purged.');
        }

        AdminLog::record($admin, 'conta_excluida_definitivamente', "restaurant:{$restaurant->id}", [
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'owner_emails' => User::withTrashed()->where('restaurant_id', $restaurant->id)->pluck('email')->all(),
            'deleted_by_owner_at' => $restaurant->deleted_at?->toIso8601String(),
        ]);

        PurgeRestaurant::dispatch($restaurant->id);
    }
}
