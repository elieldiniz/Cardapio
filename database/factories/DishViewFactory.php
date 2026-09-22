<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishView;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DishView>
 */
class DishViewFactory extends Factory
{
    protected $model = DishView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'dish_id' => function (array $attributes) {
                return Dish::factory()->create(['restaurant_id' => $attributes['restaurant_id']])->id;
            },
            'session_token' => (string) Str::uuid(),
            'seconds_watched' => 0,
            'viewed_on' => now()->toDateString(),
        ];
    }
}
