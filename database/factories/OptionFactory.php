<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_group_id' => OptionGroup::factory(),
            'name' => fake()->unique()->word(),
            'price' => null,
            'is_available' => true,
            'display_order' => 0,
        ];
    }
}
