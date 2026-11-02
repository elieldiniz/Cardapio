<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantOrderSetting>
 */
class RestaurantOrderSettingFactory extends Factory
{
    protected $model = RestaurantOrderSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'whatsapp_number' => '5569996041447',
            'address' => 'Rua das Flores, 120 — Centro, Porto Velho',
            'timezone' => RestaurantOrderSetting::DEFAULT_TIMEZONE,
            'accepts_orders' => true,
            'hours_enabled' => false,
            'delivery_mode' => 'a_combinar',
        ];
    }
}
