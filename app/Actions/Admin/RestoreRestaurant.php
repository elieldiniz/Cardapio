<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a restaurant back from the trash with its users (US-7.2). It returns
 * on the Grátis plan — the paid subscription was canceled at deletion.
 */
class RestoreRestaurant
{
    public function handle(User $admin, Restaurant $restaurant): void
    {
        DB::transaction(function () use ($admin, $restaurant) {
            $restaurant->restore();
            User::onlyTrashed()->where('restaurant_id', $restaurant->id)->restore();

            AdminLog::record($admin, 'conta_restaurada', "restaurant:{$restaurant->id}", ['name' => $restaurant->name]);
        });
    }
}
