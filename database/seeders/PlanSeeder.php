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
     * Price/limits are the "sugestão inicial, valores em aberto" reference table from
     * Docs/Desegner/Documentação Completa — Cardápio Digital em Vídeo (1).md — editable
     * later through Phase 18's admin CRUD (US-7.3), not hardcoded business rules.
     */
    public function run(): void
    {
        $metricsLevelIds = MetricsLevel::query()->pluck('id', 'slug');

        $plans = [
            [
                'name' => 'Grátis',
                'price_cents' => 0,
                'monthly_generations' => 0,
                'initial_generations' => 5,
                'dish_limit' => 15,
                'removes_branding' => false,
                'metrics_level_id' => $metricsLevelIds['cardapio_total'],
                'is_active' => true,
            ],
            [
                'name' => 'Básico',
                'price_cents' => 6900,
                'monthly_generations' => 30,
                'initial_generations' => 0,
                'dish_limit' => 60,
                'removes_branding' => true,
                'metrics_level_id' => $metricsLevelIds['por_prato'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'price_cents' => 12900,
                'monthly_generations' => 100,
                'initial_generations' => 0,
                'dish_limit' => null,
                'removes_branding' => true,
                'metrics_level_id' => $metricsLevelIds['por_prato_com_tempo_assistido'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
