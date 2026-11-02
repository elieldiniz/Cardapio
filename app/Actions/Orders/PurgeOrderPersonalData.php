<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Removes the delivery address and the notes of orders that ended long ago (RNF-08), as
 * the privacy notice of the checkout promises. Names, WhatsApp and amounts stay for the
 * dono's history until "Apagar dados" or two years without an order. Safe to run twice:
 * an order already purged is skipped.
 */
class PurgeOrderPersonalData
{
    public const RETENTION_DAYS = 30;

    /**
     * @return int how many orders were purged
     */
    public function handle(?CarbonInterface $now = null): int
    {
        $limit = ($now ?? now())->copy()->subDays(self::RETENTION_DAYS);
        $purged = 0;

        Order::query()
            ->whereNull('personal_data_purged_at')
            ->whereHas('status', fn ($status) => $status->whereIn('slug', ['finalizado', 'recusado', 'cancelado']))
            ->whereRaw('COALESCE(finished_at, cancelled_at, updated_at) <= ?', [$limit])
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$purged) {
                DB::transaction(function () use ($orders, &$purged) {
                    foreach ($orders as $order) {
                        $order->items()->update(['notes' => null]);

                        $order->update([
                            'address_street' => null,
                            'address_number' => null,
                            'address_complement' => null,
                            'neighborhood' => null,
                            'address_reference' => null,
                            'notes' => null,
                            'personal_data_purged_at' => now(),
                        ]);

                        $purged++;
                    }
                });
            });

        return $purged;
    }
}
