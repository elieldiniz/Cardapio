<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->unique()->city(),
            'fee' => '5.00',
            'is_active' => true,
            'display_order' => 0,
        ];
    }
}
