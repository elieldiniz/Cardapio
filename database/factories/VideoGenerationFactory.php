<?php

namespace Database\Factories;

use App\Models\AiPreset;
use App\Models\AiProvider;
use App\Models\Dish;
use App\Models\GenerationStatus;
use App\Models\VideoGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoGeneration>
 */
class VideoGenerationFactory extends Factory
{
    protected $model = VideoGeneration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'preset_id' => AiPreset::factory(),
            'provider_id' => AiProvider::factory(),
            'status_id' => $this->statusId('fila', 'Fila'),
            'variations_requested' => 2,
            'cost_usd' => null,
        ];
    }

    /**
     * Indicate that the generation is currently being processed by the provider.
     */
    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $this->statusId('gerando', 'Gerando'),
        ]);
    }

    /**
     * Indicate that the generation finished successfully.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $this->statusId('pronto', 'Pronto'),
            'cost_usd' => fake()->randomFloat(4, 0.05, 0.50),
        ]);
    }

    /**
     * Indicate that the generation failed at the provider (never consumes balance).
     */
    public function errored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $this->statusId('erro', 'Erro'),
        ]);
    }

    private function statusId(string $slug, string $name): int
    {
        return GenerationStatus::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }
}
