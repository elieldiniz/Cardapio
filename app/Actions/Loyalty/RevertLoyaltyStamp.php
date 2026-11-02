<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyCard;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Takes back the stamp of a cancelled order (RF-18, RF-08c). Once only; a card whose reward was
 * already redeemed is left alone, because the customer already got it.
 */
class RevertLoyaltyStamp
{
    public function handle(Order $order): void
    {
        if ($order->loyalty_card_id === null || $order->loyalty_reverted_at !== null) {
            return;
        }

        DB::transaction(function () use ($order) {
            $claimed = DB::table('orders')->where('id', $order->id)->whereNull('loyalty_reverted_at')->update(['loyalty_reverted_at' => now()]);

            if ($claimed === 0) {
                return;
            }

            $card = LoyaltyCard::query()->lockForUpdate()->find($order->loyalty_card_id);

            if ($card !== null && $card->redeemed_at === null && $card->stamps > 0) {
                $card->stamps--;

                if ($card->stamps < $card->program->stamps_required) {
                    $card->completed_at = null;
                }

                $card->save();
            }

            $order->refresh();
        });
    }
}
