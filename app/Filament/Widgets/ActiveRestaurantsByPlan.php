<?php

namespace App\Filament\Widgets;

use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Active customers segmented by plan (US-7.1).
 */
class ActiveRestaurantsByPlan extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Clientes ativos por plano';

    protected function getStats(): array
    {
        return app(BusinessMetrics::class)->activeRestaurantsByPlan()
            ->map(fn (int $count, string $plan) => Stat::make($plan, $count))
            ->values()
            ->all();
    }
}
