<?php

namespace Database\Factories;

use App\Models\MetricsLevel;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'price_cents' => fake()->numberBetween(1000, 20000),
            'stripe_price_id' => 'price_'.fake()->unique()->bothify('????????'),
            'monthly_generations' => 30,
            'initial_generations' => 0,
            'dish_limit' => 60,
            'removes_branding' => true,
            'metrics_level_id' => $this->metricsLevelId('por_prato', 'Por prato'),
            'is_active' => true,
        ];
    }

    /**
     * The seeded Grátis plan: 5 one-time generations, 15 dishes, no monthly renewal.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Grátis',
            'price_cents' => 0,
            'stripe_price_id' => null,
            'monthly_generations' => 0,
            'initial_generations' => 5,
            'dish_limit' => 15,
            'removes_branding' => false,
            'metrics_level_id' => $this->metricsLevelId('cardapio_total', 'Cardápio total'),
        ]);
    }

    /**
     * A top-tier plan with per-dish metrics including watch time and no dish limit.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'price_cents' => 12900,
            'monthly_generations' => 100,
            'dish_limit' => null,
            'metrics_level_id' => $this->metricsLevelId('por_prato_com_tempo_assistido', 'Por prato com tempo assistido'),
        ]);
    }

    /**
     * Resolve the shared Grátis plan, creating it once so every factory-built restaurant reuses it.
     */
    public static function freePlanId(): int
    {
        return Plan::query()->where('name', 'Grátis')->value('id')
            ?? Plan::factory()->free()->create()->id;
    }

    private function metricsLevelId(string $slug, string $name): int
    {
        return MetricsLevel::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }
}
