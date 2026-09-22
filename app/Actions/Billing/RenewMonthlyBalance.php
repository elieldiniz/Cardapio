<?php

namespace App\Actions\Billing;

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * On a paid subscription invoice (US-5.2): the monthly balance is *reset* to the
 * plan's allowance — leftovers never accumulate — and renews_at advances.
 * Addon balance is never touched. Idempotent per invoice.
 */
class RenewMonthlyBalance
{
    public function handle(Restaurant $restaurant, string $invoiceId, ?CarbonInterface $renewsAt): void
    {
        DB::transaction(function () use ($restaurant, $invoiceId, $renewsAt) {
            $typeId = GenerationLedgerType::idFor('renovacao');
            $reference = "stripe_invoice:{$invoiceId}";

            if (GenerationLedger::query()->where('restaurant_id', $restaurant->id)->where('reference', $reference)->exists()) {
                return;
            }

            $allowance = (int) $restaurant->plan()->value('monthly_generations');

            $balance = GenerationBalance::query()->lockForUpdate()->firstOrCreate(['restaurant_id' => $restaurant->id]);
            $balance->update(['monthly_balance' => $allowance, 'renews_at' => $renewsAt]);

            GenerationLedger::create([
                'restaurant_id' => $restaurant->id,
                'type_id' => $typeId,
                'quantity' => $allowance,
                'reference' => $reference,
            ]);
        });
    }
}
