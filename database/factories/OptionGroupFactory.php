<?php

namespace Database\Factories;

use App\Models\OptionGroup;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    protected $model = OptionGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->unique()->words(2, true),
            'mode' => 'many',
            'max_choices' => 3,
            'is_required' => false,
            'allow_repeat' => false,
            'display_order' => 0,
            'is_active' => true,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true, 'mode' => 'one', 'max_choices' => 1]);
    }
}
