<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'plan_id' => PlanFactory::freePlanId(),
            'status_id' => RestaurantStatus::query()->firstOrCreate(
                ['slug' => 'ativo'],
                ['name' => 'Ativo']
            )->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_path' => null,
            'accent_color' => null,
            'font' => null,
            'stripe_id' => null,
        ];
    }
}
