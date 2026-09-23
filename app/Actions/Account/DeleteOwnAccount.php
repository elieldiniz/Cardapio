<?php

namespace App\Actions\Account;

use App\Actions\RegisterRestaurantOwner;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The dono deletes their account: billing stops right away, and the
 * restaurant and its users go to the trash (soft delete). The cardápio leaves
 * the air immediately; the super admin later restores or purges it.
 */
class DeleteOwnAccount
{
    public function handle(User $owner): void
    {
        $restaurant = $owner->restaurant;

        // Stop charging first — if Stripe refuses, nothing is deleted.
        $subscription = $restaurant->subscription('default');

        if ($subscription?->valid()) {
            $subscription->cancelNow();
        }

        DB::transaction(function () use ($restaurant) {
            $restaurant->update(['plan_id' => Plan::query()->where('name', RegisterRestaurantOwner::FREE_PLAN_NAME)->value('id') ?? $restaurant->plan_id]);

            $userIds = $restaurant->users()->pluck('id');

            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
            $restaurant->users()->each(fn (User $user) => $user->delete());
            $restaurant->delete();
        });
    }
}
