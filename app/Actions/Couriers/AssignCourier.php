<?php

namespace App\Actions\Couriers;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Puts a delivery order in a courier's hands (RF-22), or takes it back (`null`). Only delivery
 * orders still open, and only couriers of the same store, can be chosen.
 */
class AssignCourier
{
    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?Courier $courier): Order
    {
        $order->loadMissing('status', 'restaurant.plan');

        if (! $order->isDelivery() || in_array($order->status->slug, ['finalizado', 'recusado', 'cancelado'], true)) {
            throw ValidationException::withMessages(['courier' => 'Só é possível escolher motoboy em uma entrega em andamento.']);
        }

        if ($courier !== null && ($courier->restaurant_id !== $order->restaurant_id || ! $courier->is_active)) {
            throw ValidationException::withMessages(['courier' => 'Motoboy indisponível.']);
        }

        if ($courier !== null && ! $order->restaurant->hasFeature('couriers')) {
            throw ValidationException::withMessages(['courier' => 'Motoboys não estão incluídos no seu plano.']);
        }

        if ($order->courier_settled_at !== null) {
            throw ValidationException::withMessages(['courier' => 'O acerto deste pedido já foi feito.']);
        }

        $order->update(['courier_id' => $courier?->id, 'delivered_at' => $courier?->id === $order->courier_id ? $order->delivered_at : null]);

        return $order;
    }
}
