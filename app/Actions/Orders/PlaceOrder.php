<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderRejectedException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Services\Orders\OrderCodeGenerator;
use App\Services\Orders\OrderPricer;
use App\Services\Orders\PricedOrder;
use App\Support\BusinessHours;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a customer's checkout and records it as an order with status "novo" (RF-02a,
 * RF-05, RF-06, RF-06b, RF-06c, RF-06e). Everything is decided on the server: the store
 * must be open (or accept off hours orders), every price is recalculated, and the order
 * with its item snapshot, customer and first history row is written in one transaction.
 * Nothing about money depends on a queue job (RNF-04): the Pix is not marked as paid here.
 */
class PlaceOrder
{
    public const ORIGINS = ['direto', 'anuncio', 'instagram', 'whatsapp', 'facebook', 'qrcode', 'outro'];

    public function __construct(
        private readonly OrderPricer $pricer,
        private readonly OrderCodeGenerator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated PlaceOrderRequest payload
     *
     * @throws OrderRejectedException
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data): Order
    {
        $settings = $restaurant->orderSettings();

        if (blank($settings->whatsapp_number)) {
            throw new OrderRejectedException([['type' => 'store_not_ready', 'message' => 'Esta loja ainda não está recebendo pedidos.']]);
        }

        $state = BusinessHours::state($restaurant);
        $offHours = false;

        if (! $state['open']) {
            if ($state['reason'] === 'manual' || ! $settings->accepts_when_closed) {
                throw new OrderRejectedException([['type' => 'store_closed', 'message' => $state['reason'] === 'manual'
                    ? 'A loja está fechada para pedidos agora.'
                    : 'A loja está fechada agora. '.$state['label'].'.']]);
            }

            $offHours = true;
        }

        $address = $data['address'] ?? [];
        $fulfillment = $data['fulfillment'];

        $priced = $this->pricer->price($restaurant, $data['items'], [
            'fulfillment' => $fulfillment,
            'zone_id' => $address['zone_id'] ?? null,
            'neighborhood' => $address['neighborhood'] ?? null,
            'lat' => $address['lat'] ?? null,
            'lng' => $address['lng'] ?? null,
            'promo_code' => $data['promo_code'] ?? null,
        ]);

        $changeFor = $this->changeFor($data, $priced);

        return $this->persist($restaurant, $data, $priced, $changeFor, $offHours);
    }

    /**
     * "Troco para" only makes sense for cash and cannot be below the total (RF-06c).
     */
    private function changeFor(array $data, PricedOrder $priced): ?string
    {
        if (($data['payment_method'] ?? null) !== 'dinheiro' || blank($data['change_for'] ?? null)) {
            return null;
        }

        $amount = Money::parse((string) $data['change_for']);

        if ($amount === null) {
            throw ValidationException::withMessages(['change_for' => 'Valor do troco inválido. Use o formato 100,00.']);
        }

        if ((float) $amount < (float) $priced->total) {
            throw ValidationException::withMessages(['change_for' => 'O troco precisa ser maior ou igual ao total do pedido.']);
        }

        return $amount;
    }

    private function persist(Restaurant $restaurant, array $data, PricedOrder $priced, ?string $changeFor, bool $offHours): Order
    {
        $address = $data['address'] ?? [];
        $delivery = $priced->fulfillment === 'entrega';

        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($restaurant, $data, $priced, $changeFor, $offHours, $address, $delivery) {
                    if ($priced->promoCodeId !== null) {
                        app(ApplyPromoCode::class)->consume(PromoCode::query()->findOrFail($priced->promoCodeId));
                    }

                    $customer = $this->customer($restaurant, $data['customer']['name'], $data['customer']['phone']);

                    $order = $restaurant->orders()->create([
                        'code' => $this->codes->code($restaurant),
                        'tracking_token' => $this->codes->token(),
                        'status_id' => OrderStatus::idFor('novo'),
                        'fulfillment' => $priced->fulfillment,
                        'channel' => 'feed',
                        'origin' => $this->origin($data['origin'] ?? null),
                        'campaign' => filled($data['campaign'] ?? null) ? trim($data['campaign']) : null,
                        'customer_id' => $customer->id,
                        'customer_name' => trim($data['customer']['name']),
                        'customer_phone' => $data['customer']['phone'],
                        'address_street' => $delivery ? trim($address['street']) : null,
                        'address_number' => $delivery ? trim($address['number']) : null,
                        'address_complement' => $delivery ? ($address['complement'] ?? null) : null,
                        'neighborhood' => $delivery ? $priced->neighborhood : null,
                        'address_reference' => $delivery ? ($address['reference'] ?? null) : null,
                        'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                        'subtotal' => $priced->subtotal,
                        'delivery_fee' => $priced->deliveryFee,
                        'delivery_fee_pending' => $priced->deliveryFeePending,
                        'discount' => $priced->discount,
                        'distance_km' => $priced->distanceKm,
                        'promo_code_id' => $priced->promoCodeId,
                        'total' => $priced->total,
                        'payment_method_id' => PaymentMethod::idFor($data['payment_method']),
                        'change_for' => $changeFor,
                        'placed_off_hours' => $offHours,
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

                    $order->history()->create(['status_id' => $order->status_id, 'changed_at' => now()]);

                    return $order->load(['items', 'paymentMethod', 'status']);
                });
            } catch (QueryException $exception) {
                // Two simultaneous orders drew the same code: draw again (RNF-04).
                if (++$attempts >= 3 || ! str_contains($exception->getMessage(), 'orders_restaurant_id_code_unique')) {
                    throw $exception;
                }
            }
        }
    }

    private function customer(Restaurant $restaurant, string $name, string $phone): Customer
    {
        $customer = Customer::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('whatsapp', $phone)
            ->lockForUpdate()
            ->first();

        if ($customer === null) {
            return Customer::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => trim($name),
                'whatsapp' => $phone,
                'orders_count' => 1,
                'first_order_at' => now(),
                'last_order_at' => now(),
            ]);
        }

        $customer->update([
            'name' => trim($name),
            'orders_count' => $customer->orders_count + 1,
            'last_order_at' => now(),
            'erased_at' => null,
        ]);

        return $customer;
    }

    private function origin(?string $origin): string
    {
        $origin = strtolower(trim((string) $origin));

        if ($origin === '') {
            return 'direto';
        }

        return in_array($origin, self::ORIGINS, true) ? $origin : 'outro';
    }
}
