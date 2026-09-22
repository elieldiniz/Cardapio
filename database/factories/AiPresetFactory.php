<?php

namespace Database\Factories;

use App\Models\AiPreset;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPreset>
 */
class AiPresetFactory extends Factory
{
    protected $model = AiPreset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => AiProvider::factory(),
            'name' => fake()->unique()->words(2, true),
            'prompt' => fake()->paragraph(),
            'camera_movement' => '30-45° com leve aproximação',
            'duration_seconds' => fake()->numberBetween(5, 10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the preset is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
