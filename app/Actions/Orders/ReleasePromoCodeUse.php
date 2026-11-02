<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Gives back the use of a discount code when its order is refused or cancelled (RF-17, RF-08c).
 * The order remembers it was released, so doing it twice returns only one use, and the counter
 * never goes below zero (RNF-04).
 */
class ReleasePromoCodeUse
{
    public function handle(Order $order): void
    {
        if ($order->promo_code_id === null || $order->promo_released_at !== null) {
            return;
        }

        DB::transaction(function () use ($order) {
            $claimed = DB::table('orders')
                ->where('id', $order->id)
                ->whereNull('promo_released_at')
                ->update(['promo_released_at' => now()]);

            if ($claimed === 1) {
                DB::table('promo_codes')->where('id', $order->promo_code_id)->where('uses', '>', 0)->decrement('uses');
                $order->refresh();
            }
        });
    }
}
