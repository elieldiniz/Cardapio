<?php

namespace App\Actions\Billing;

use App\Actions\RegisterRestaurantOwner;
use App\Models\DishStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Notifications\PlanDowngraded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Falls back to Grátis without taking the cardápio down (US-5.5): the
 * restaurant status is untouched, and dishes beyond Grátis' dish_limit are
 * hidden (oculto) — keeping the first ones in menu order visible.
 */
class DowngradeToFreePlan
{
    public function handle(Restaurant $restaurant, bool $forNonPayment): void
    {
        $freePlan = Plan::query()->where('name', RegisterRestaurantOwner::FREE_PLAN_NAME)->firstOrFail();

        $hiddenNames = DB::transaction(function () use ($restaurant, $freePlan) {
            $restaurant->update(['plan_id' => $freePlan->id]);

            if ($freePlan->dish_limit === null) {
                return [];
            }

            $hiddenId = DishStatus::idFor('oculto');

            $overLimit = $restaurant->dishes()
                ->where('dishes.status_id', '!=', $hiddenId)
                ->join('categories', 'categories.id', '=', 'dishes.category_id')
                ->orderByDesc('categories.is_visible')
                ->orderBy('categories.display_order')
                ->orderBy('dishes.display_order')
                ->orderBy('dishes.id')
                ->select('dishes.*')
                ->get()
                ->slice($freePlan->dish_limit);

            $restaurant->dishes()->whereIn('id', $overLimit->pluck('id'))->update(['status_id' => $hiddenId]);

            return $overLimit->pluck('name')->all();
        });

        $owners = $restaurant->users()->whereHas('role', fn ($role) => $role->where('slug', 'dono'))->get();

        Notification::send($owners, new PlanDowngraded($forNonPayment, $hiddenNames, $freePlan->dish_limit));
    }
}
