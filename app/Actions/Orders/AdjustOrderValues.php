<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fixes the amounts of an order after weighing (RF-15): the value of the products and the
 * delivery fee are set separately, the total is recalculated and the original total is kept
 * on the order together with the moment of the adjustment, so the change is never silent. Only an order still
 * being handled and not yet paid can be adjusted; the row is locked while it changes.
 */
class AdjustOrderValues
{
    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?string $products, ?string $fee = null, ?User $by = null): Order
    {
        $productsValue = Money::parse((string) $products);

        if ($productsValue === null) {
            throw ValidationException::withMessages(['products' => 'Informe o valor dos produtos. Use o formato 52,30.']);
        }

        $feeValue = null;

        if (filled($fee)) {
            $feeValue = Money::parse((string) $fee);

            if ($feeValue === null) {
                throw ValidationException::withMessages(['fee' => 'Taxa de entrega inválida. Use o formato 5,00.']);
            }
        }

        return DB::transaction(function () use ($order, $productsValue, $feeValue) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->with('status')->firstOrFail();

            if (! in_array($locked->status->slug, ['novo', 'em_preparo', 'em_rota'], true)) {
                throw ValidationException::withMessages(['status' => 'Só é possível ajustar o valor de um pedido em andamento.']);
            }

            if ($locked->payments()->exists()) {
                throw ValidationException::withMessages(['status' => 'Este pedido já foi pago. Ajuste o valor antes de receber.']);
            }

            if ((float) $productsValue < (float) $locked->discount) {
                throw ValidationException::withMessages(['products' => 'O valor dos produtos não pode ser menor que o desconto do pedido.']);
            }

            $newFee = $locked->isDelivery() && ! $locked->delivery_fee_pending
                ? ($feeValue ?? $locked->delivery_fee ?? '0.00')
                : ($locked->isDelivery() ? ($feeValue ?? $locked->delivery_fee) : null);

            $total = number_format((float) $productsValue - (float) $locked->discount + (float) ($newFee ?? 0), 2, '.', '');

            $locked->update([
                'original_total' => $locked->original_total ?? $locked->total,
                'subtotal' => $productsValue,
                'delivery_fee' => $newFee,
                'delivery_fee_pending' => $locked->isDelivery() && $newFee === null ? $locked->delivery_fee_pending : false,
                'total' => $total,
                'adjusted_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
