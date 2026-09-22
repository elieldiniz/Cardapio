<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'category_id' => function (array $attributes) {
                return Category::factory()->create([
                    'restaurant_id' => $attributes['restaurant_id'],
                ])->id;
            },
            'status_id' => DishStatus::query()->firstOrCreate(
                ['slug' => 'ativo'],
                ['name' => 'Ativo']
            )->id,
            'name' => fake()->unique()->words(3, true),
            'price' => fake()->randomFloat(2, 10, 150),
            'short_description' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'display_order' => 0,
        ];
    }
}
