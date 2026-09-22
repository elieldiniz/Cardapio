<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DishPhoto>
 */
class DishPhotoFactory extends Factory
{
    protected $model = DishPhoto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'file_path' => 'dish-photos/'.fake()->uuid().'.jpg',
            'display_order' => 0,
        ];
    }
}
