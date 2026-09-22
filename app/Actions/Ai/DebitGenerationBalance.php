<?php

namespace App\Actions\Ai;

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;

/**
 * Debits a finished generation (US-3.4): one `uso` ledger entry per variation
 * actually generated, spending monthly_balance before addon_balance.
 * Idempotent per generation, so reprocessing never double-debits.
 */
class DebitGenerationBalance
{
    public static function reference(VideoGeneration $generation): string
    {
        return "video_generation:{$generation->id}";
    }

    public function handle(VideoGeneration $generation, int $generatedCount): void
    {
        if ($generatedCount <= 0) {
            return;
        }

        DB::transaction(function () use ($generation, $generatedCount) {
            $restaurantId = $generation->dish()->value('restaurant_id');
            $reference = static::reference($generation);
            $usoTypeId = GenerationLedgerType::idFor('uso');

            $alreadyDebited = GenerationLedger::query()
                ->where('restaurant_id', $restaurantId)
                ->where('type_id', $usoTypeId)
                ->where('reference', $reference)
                ->exists();

            if ($alreadyDebited) {
                return;
            }

            $balance = GenerationBalance::query()
                ->where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->firstOrFail();

            $fromMonthly = min($balance->monthly_balance, $generatedCount);
            $fromAddon = min($balance->addon_balance, $generatedCount - $fromMonthly);

            $balance->update([
                'monthly_balance' => $balance->monthly_balance - $fromMonthly,
                'addon_balance' => $balance->addon_balance - $fromAddon,
            ]);

            for ($i = 0; $i < $generatedCount; $i++) {
                GenerationLedger::create([
                    'restaurant_id' => $restaurantId,
                    'type_id' => $usoTypeId,
                    'quantity' => -1,
                    'reference' => $reference,
                ]);
            }
        });
    }
}
