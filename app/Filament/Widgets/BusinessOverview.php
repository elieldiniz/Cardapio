<?php

namespace App\Filament\Widgets;

use App\Services\Admin\BusinessMetrics;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * MRR, monthly movement and Grátis → pago conversion (US-7.1).
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $metrics = app(BusinessMetrics::class);

        return [
            Stat::make('MRR', Money::brlFromCents($metrics->mrrCents()))
                ->description('Assinaturas ativas'),
            Stat::make('Novos restaurantes no mês', $metrics->newRestaurants()),
            Stat::make('Cancelamentos no mês', $metrics->canceledRestaurants()),
            Stat::make('Conversão Grátis → pago', number_format($metrics->freeToPaidConversionRate(), 1, ',', '.').'%')
                ->description('Cadastros do mês que assinaram'),
        ];
    }
}
