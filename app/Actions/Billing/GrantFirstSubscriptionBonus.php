<?php

namespace App\Actions\Billing;

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * Welcome bonus on the first paid subscription: the plan's
 * `first_subscription_bonus` generations go to addon_balance (they never
 * expire). It is given once per restaurant — a later cancel and resubscribe
 * does not repeat it — and logged as a `bonus` ledger entry.
 */
class GrantFirstSubscriptionBonus
{
    public function handle(Restaurant $restaurant): bool
    {
        $bonus = (int) $restaurant->plan()->value('first_subscription_bonus');

        if ($bonus <= 0) {
            return false;
        }

        return DB::transaction(function () use ($restaurant, $bonus) {
            // Serialises concurrent webhook deliveries for the same restaurant.
            $balance = GenerationBalance::query()->lockForUpdate()->firstOrCreate(['restaurant_id' => $restaurant->id]);

            $reference = "first_subscription_bonus:{$restaurant->id}";

            if (GenerationLedger::query()->where('restaurant_id', $restaurant->id)->where('reference', $reference)->exists()) {
                return false;
            }

            $balance->increment('addon_balance', $bonus);

            GenerationLedger::create([
                'restaurant_id' => $restaurant->id,
                'type_id' => GenerationLedgerType::idFor('bonus'),
                'quantity' => $bonus,
                'reference' => $reference,
            ]);

            return true;
        });
    }
}
