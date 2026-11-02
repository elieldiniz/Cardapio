<?php

namespace Database\Factories;

use App\Models\BusinessHour;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    protected $model = BusinessHour::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'weekday' => 1,
            'shift' => 1,
            'opens_at' => '18:00',
            'closes_at' => '23:00',
        ];
    }
}
