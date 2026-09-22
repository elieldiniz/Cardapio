<?php

namespace App\Actions\Billing;

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\Restaurant;
use App\Models\VideoAddonPackage;
use Illuminate\Support\Facades\DB;

/**
 * A paid one-time addon checkout (US-5.3): credits addon_balance, which never
 * expires, and logs a `compra` entry. Idempotent per Stripe checkout session.
 */
class CreditAddonPurchase
{
    public function handle(Restaurant $restaurant, VideoAddonPackage $package, string $checkoutSessionId, ?int $amountCents = null): void
    {
        DB::transaction(function () use ($restaurant, $package, $checkoutSessionId, $amountCents) {
            $reference = "stripe_checkout:{$checkoutSessionId}";

            if (GenerationLedger::query()->where('restaurant_id', $restaurant->id)->where('reference', $reference)->exists()) {
                return;
            }

            $balance = GenerationBalance::query()->lockForUpdate()->firstOrCreate(['restaurant_id' => $restaurant->id]);
            $balance->increment('addon_balance', $package->generations_count);

            GenerationLedger::create([
                'restaurant_id' => $restaurant->id,
                'type_id' => GenerationLedgerType::idFor('compra'),
                'quantity' => $package->generations_count,
                'amount_cents' => $amountCents ?? $package->price_cents,
                'reference' => $reference,
            ]);
        });
    }
}
