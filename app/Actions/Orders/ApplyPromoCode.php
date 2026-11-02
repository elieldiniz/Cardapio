<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderRejectedException;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Support\BusinessHours;
use Illuminate\Support\Facades\DB;

/**
 * Finds and checks a customer's discount code (RF-17) and, when the order is really placed,
 * spends one use of it. The use is taken with a single conditional UPDATE, so two orders racing
 * for the last use cannot both get it (RNF-04).
 */
class ApplyPromoCode
{
    /**
     * The code and its discount in cents for a purchase, or an OrderRejectedException saying why not.
     *
     * @return array{promo: PromoCode, discount: int}
     *
     * @throws OrderRejectedException
     */
    public function check(Restaurant $restaurant, string $code, int $subtotalCents): array
    {
        if (! $restaurant->hasFeature('promo_codes')) {
            throw new OrderRejectedException([['type' => 'promo_code', 'message' => 'Cupom indisponível.']]);
        }

        $promo = PromoCode::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('code', strtoupper(trim($code)))
            ->first();

        if ($promo === null) {
            throw new OrderRejectedException([['type' => 'promo_code', 'message' => 'Cupom inválido.']]);
        }

        $problem = $promo->problem($subtotalCents, BusinessHours::localNow($restaurant)->toDateString());

        if ($problem !== null) {
            throw new OrderRejectedException([['type' => 'promo_code', 'message' => $problem]]);
        }

        return ['promo' => $promo, 'discount' => $promo->discountCents($subtotalCents)];
    }

    /**
     * Takes one use of the code inside the order's transaction.
     *
     * @throws OrderRejectedException
     */
    public function consume(PromoCode $promo): void
    {
        $taken = DB::table('promo_codes')
            ->where('id', $promo->id)
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
            ->increment('uses');

        if ($taken === 0) {
            throw new OrderRejectedException([['type' => 'promo_code', 'message' => 'Este cupom atingiu o limite de usos.']]);
        }
    }
}
