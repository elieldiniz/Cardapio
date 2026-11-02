<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyCard;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Gives the customer one stamp when an order is finalized (RF-18). Does nothing when the program
 * is off, the order has no customer, it is below the program's minimum, or the card is already
 * full. An order stamps at most once: it remembers the card it stamped (RNF-04).
 */
class CreditLoyaltyStamp
{
    public function handle(Order $order): void
    {
        if ($order->loyalty_card_id !== null || $order->customer_id === null) {
            return;
        }

        $program = $order->restaurant->loyaltyProgram;

        if ($program === null || ! $program->is_active || ! $order->restaurant->hasFeature('loyalty')) {
            return;
        }

        if ($program->min_order !== null && (float) $order->subtotal < (float) $program->min_order) {
            return;
        }

        DB::transaction(function () use ($order, $program) {
            $card = LoyaltyCard::query()
                ->where('loyalty_program_id', $program->id)
                ->where('customer_id', $order->customer_id)
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->first() ?? LoyaltyCard::create(['loyalty_program_id' => $program->id, 'customer_id' => $order->customer_id]);

            if ($card->completed_at !== null) {
                return;
            }

            $card->stamps++;

            if ($card->stamps >= $program->stamps_required) {
                $card->completed_at = now();
            }

            $card->save();
            $order->forceFill(['loyalty_card_id' => $card->id, 'loyalty_reverted_at' => null])->save();
        });
    }
}
