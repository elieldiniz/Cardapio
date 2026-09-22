<?php

namespace App\Filament\Widgets;

use App\Models\VideoGeneration;
use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Monthly AI spend shown above the generation queue (US-7.4).
 */
class AiCostThisMonth extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $month = now()->startOfMonth();
        $generations = VideoGeneration::query()->where('created_at', '>=', $month);

        return [
            Stat::make('Custo de IA no mês', 'US$ '.number_format(app(BusinessMetrics::class)->aiCostUsd(), 2, ',', '.')),
            Stat::make('Gerações no mês', (clone $generations)->count()),
            Stat::make('Com erro no mês', (clone $generations)->whereHas('status', fn ($status) => $status->where('slug', 'erro'))->count()),
        ];
    }
}
