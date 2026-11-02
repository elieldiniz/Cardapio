<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Refuses an order that cannot be served (RF-08). The reason is optional here and is kept
 * on the order; the customer's page tells them to talk to the store.
 */
class RefuseOrder
{
    public function __construct(private readonly AdvanceOrder $advance, private readonly ReleasePromoCodeUse $releasePromoCode) {}

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?string $reason = null, ?User $by = null): Order
    {
        $refused = $this->advance->handle($order, 'recusado', $by, [
            'cancel_reason' => filled($reason) ? mb_substr(trim($reason), 0, 255) : null,
        ]);

        // The customer did not get the order, so the discount code use goes back (RF-17).
        $this->releasePromoCode->handle($refused);

        return $refused;
    }
}
