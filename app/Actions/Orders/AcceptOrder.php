<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a new order (RF-08). When the delivery fee was left "a combinar" the dono must
 * set it now: it is saved with the new total in the same transaction, so the customer's
 * page and the message the dono sends show the final amount.
 */
class AcceptOrder
{
    public function __construct(private readonly AdvanceOrder $advance) {}

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?string $fee = null, ?User $by = null): Order
    {
        return DB::transaction(function () use ($order, $fee, $by) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $attributes = [];

            if ($locked->status->slug === 'novo' && $locked->delivery_fee_pending) {
                $amount = filled($fee) ? Money::parse($fee) : null;

                if ($amount === null) {
                    throw ValidationException::withMessages(['fee' => 'Informe a taxa de entrega para aceitar este pedido (pode ser 0,00).']);
                }

                $attributes = [
                    'delivery_fee' => $amount,
                    'delivery_fee_pending' => false,
                    'total' => number_format((float) $locked->subtotal + (float) $amount - (float) $locked->discount, 2, '.', ''),
                ];
            }

            return $this->advance->handle($locked, 'em_preparo', $by, $attributes);
        });
    }
}
