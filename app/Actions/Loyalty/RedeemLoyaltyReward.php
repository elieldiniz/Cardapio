<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The dono hands over the reward of a full card (RF-18). Once: the card is closed and the
 * customer's next order starts a new one.
 */
class RedeemLoyaltyReward
{
    /**
     * @throws ValidationException
     */
    public function handle(LoyaltyCard $card): LoyaltyCard
    {
        return DB::transaction(function () use ($card) {
            $locked = LoyaltyCard::query()->lockForUpdate()->findOrFail($card->id);

            if ($locked->redeemed_at !== null) {
                throw ValidationException::withMessages(['card' => 'Este prêmio já foi resgatado.']);
            }

            if ($locked->completed_at === null) {
                throw ValidationException::withMessages(['card' => 'O cartão ainda não está completo.']);
            }

            $locked->update(['redeemed_at' => now()]);

            return $locked;
        });
    }
}
