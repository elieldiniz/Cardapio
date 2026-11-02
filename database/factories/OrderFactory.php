<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'code' => 'D-'.strtoupper(Str::random(5)),
            'tracking_token' => Str::random(32),
            'status_id' => fn () => OrderStatus::firstOrCreate(['slug' => 'novo'], ['name' => 'Novo'])->id,
            'fulfillment' => 'retirada',
            'channel' => 'feed',
            'origin' => 'direto',
            'customer_name' => 'Ana Teste',
            'customer_phone' => '5569999990000',
            'subtotal' => '40.00',
            'delivery_fee' => null,
            'discount' => '0.00',
            'total' => '40.00',
        ];
    }

    public function status(string $slug): static
    {
        return $this->state(fn () => [
            'status_id' => OrderStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id,
        ]);
    }

    public function delivery(): static
    {
        return $this->state(fn () => [
            'fulfillment' => 'entrega',
            'address_street' => 'Rua das Flores',
            'address_number' => '120',
            'neighborhood' => 'Centro',
            'delivery_fee' => '5.00',
            'total' => '45.00',
        ]);
    }
}
