<?php

namespace App\Services\Orders;

/**
 * The result of pricing a cart on the server (RF-05): lines, subtotal, delivery fee
 * and total as decimal strings, so no float ever carries money (RNF-02).
 */
final class PricedOrder
{
    /**
     * @param  array<int, array{dish_id: int, name: string, sale_type: string, quantity: string, unit_price: string, total: string, notes: ?string, selections: array<string, mixed>}>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly string $subtotal,
        public readonly ?string $deliveryFee,
        public readonly bool $deliveryFeePending,
        public readonly string $discount,
        public readonly string $total,
        public readonly string $fulfillment,
        public readonly ?string $neighborhood,
        public readonly bool $freeDelivery = false,
        public readonly ?string $missingForFreeDelivery = null,
        public readonly ?float $distanceKm = null,
        public readonly ?int $promoCodeId = null,
        public readonly ?string $promoCode = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->deliveryFee,
            'delivery_fee_pending' => $this->deliveryFeePending,
            'discount' => $this->discount,
            'total' => $this->total,
            'free_delivery' => $this->freeDelivery,
            'missing_for_free_delivery' => $this->missingForFreeDelivery,
            'promo_code' => $this->promoCode,
        ];
    }
}
