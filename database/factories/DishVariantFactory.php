<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DishVariant>
 */
class DishVariantFactory extends Factory
{
    protected $model = DishVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name' => fake()->randomElement(['Pequeno', 'Médio', 'Grande']),
            'price' => fake()->randomFloat(2, 10, 150),
        ];
    }
}
