<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderRejectedException;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Orders\OrderCodeGenerator;
use App\Services\Orders\OrderPaymentRegistrar;
use App\Services\Orders\OrderPricer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sale made at the counter (RF-13). It needs the caixa open: without it nothing is
 * created. The cart is priced by the server (OrderPricer), a discount may be applied, and
 * the order is written already paid — payments and cash movements in the same
 * transaction, so a sale is never half recorded (RNF-04). With "enviar para a cozinha" the
 * order lands in "Em preparo" for the kitchen; otherwise it is finalized on the spot.
 *
 * options: discount, customer_name, notes, send_to_kitchen (bool).
 */
class CreatePdvSale
{
    public function __construct(
        private readonly OrderPricer $pricer,
        private readonly OrderCodeGenerator $codes,
        private readonly OrderPaymentRegistrar $registrar,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $payments
     * @param  array<string, mixed>  $options
     *
     * @throws OrderRejectedException
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $items, array $payments, array $options = [], ?User $by = null): Order
    {
        if (! $restaurant->hasFeature('pdv')) {
            throw ValidationException::withMessages(['pdv' => 'O PDV não está incluído no seu plano.']);
        }

        if ($restaurant->openCashSession() === null) {
            throw ValidationException::withMessages(['cash' => 'Abra o caixa para vender no balcão.']);
        }

        $priced = $this->pricer->price($restaurant, $items, ['fulfillment' => 'balcao']);

        $discount = filled($options['discount'] ?? null) ? Money::parse((string) $options['discount']) : '0.00';

        if ($discount === null || (float) $discount < 0 || (float) $discount > (float) $priced->subtotal) {
            throw ValidationException::withMessages(['discount' => 'Desconto inválido: não pode passar do valor da venda.']);
        }

        $total = number_format((float) $priced->subtotal - (float) $discount, 2, '.', '');
        $kitchen = (bool) ($options['send_to_kitchen'] ?? false);
        $name = filled($options['customer_name'] ?? null) ? mb_substr(trim($options['customer_name']), 0, 120) : null;

        return DB::transaction(function () use ($restaurant, $priced, $payments, $options, $by, $discount, $total, $kitchen, $name) {
            $now = now();
            $status = $kitchen ? 'em_preparo' : 'finalizado';

            $order = $restaurant->orders()->create([
                'code' => $this->codes->code($restaurant),
                'tracking_token' => $this->codes->token(),
                'status_id' => OrderStatus::idFor($status),
                'fulfillment' => 'balcao',
                'channel' => 'pdv',
                'origin' => 'balcao',
                'customer_name' => $name,
                'notes' => filled($options['notes'] ?? null) ? trim($options['notes']) : null,
                'subtotal' => $priced->subtotal,
                'delivery_fee' => null,
                'discount' => $discount,
                'total' => $total,
                'accepted_at' => $now,
                'finished_at' => $kitchen ? null : $now,
            ]);

            foreach ($priced->lines as $line) {
                $order->items()->create([
                    'dish_id' => $line['dish_id'],
                    'name' => $line['name'],
                    'sale_type' => $line['sale_type'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['total'],
                    'notes' => $line['notes'],
                    'selections' => $line['selections'],
                    'display_order' => $line['display_order'],
                ]);
            }

            $order->history()->create(['status_id' => $order->status_id, 'user_id' => $by?->id, 'changed_at' => $now]);

            $order->setRelation('restaurant', $restaurant);
            $this->registrar->register($order, $payments, $by);

            return $order->refresh()->load(['items', 'payments', 'status']);
        });
    }
}
