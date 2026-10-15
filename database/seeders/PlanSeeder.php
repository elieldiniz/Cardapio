<?php

namespace Database\Seeders;

use App\Models\MetricsLevel;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the baseline Grátis/Básico/Pro plans idempotently.
     *
     * Model adopted 15/10/2026 (see Docs/custos-e-precificacao.md): AI generations are sold
     * as add-on packs, so the plans carry no monthly allowance; Grátis gets a one-time
     * trial at signup and each paid plan a one-time bonus on the first paid subscription.
     * Editable later in the admin (US-7.3), not hardcoded business rules.
     */
    public function run(): void
    {
        $metricsLevelIds = MetricsLevel::query()->pluck('id', 'slug');

        $plans = [
            [
                'name' => 'Grátis',
                'price_cents' => 0,
                'monthly_generations' => 0,
                'initial_generations' => 3,
                'first_subscription_bonus' => 0,
                'dish_limit' => 15,
                'removes_branding' => false,
                'qr_customization' => false,
                'metrics_level_id' => $metricsLevelIds['cardapio_total'],
                'is_active' => true,
            ],
            [
                'name' => 'Básico',
                'price_cents' => 3990,
                'monthly_generations' => 0,
                'initial_generations' => 0,
                'first_subscription_bonus' => 6,
                'dish_limit' => 30,
                'removes_branding' => true,
                'qr_customization' => true,
                'metrics_level_id' => $metricsLevelIds['por_prato'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'price_cents' => 7990,
                'monthly_generations' => 0,
                'initial_generations' => 0,
                'first_subscription_bonus' => 12,
                'dish_limit' => null,
                'removes_branding' => true,
                'qr_customization' => true,
                'metrics_level_id' => $metricsLevelIds['por_prato_com_tempo_assistido'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
