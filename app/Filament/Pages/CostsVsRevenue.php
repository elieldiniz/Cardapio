<?php

namespace App\Filament\Pages;

use App\Models\MonthlyCost;
use App\Services\Admin\BusinessMetrics;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * AI + Mux spend against MRR + addon revenue for the month (US-7.6). The
 * Mux bill is typed in by the super admin for now — no automated import.
 */
class CostsVsRevenue extends Page
{
    protected string $view = 'filament.pages.costs-vs-revenue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Custos x receita';

    protected static ?string $title = 'Custos x receita';

    protected static ?string $slug = 'custos-receita';

    public string $month = '';

    public ?string $muxCostUsd = null;

    public ?string $usdBrlRate = null;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->loadEntered();
    }

    public function updatedMonth(): void
    {
        $this->loadEntered();
    }

    private function monthDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    private function loadEntered(): void
    {
        $entered = MonthlyCost::forMonth($this->monthDate());

        $this->muxCostUsd = $entered?->mux_cost_usd;
        $this->usdBrlRate = $entered?->usd_brl_rate;
    }

    public function save(): void
    {
        $this->validate([
            'month' => ['required', 'date_format:Y-m'],
            'muxCostUsd' => ['required', 'numeric', 'min:0'],
            'usdBrlRate' => ['nullable', 'numeric', 'gt:0'],
        ], attributes: ['muxCostUsd' => 'custo do Mux', 'usdBrlRate' => 'cotação do dólar']);

        MonthlyCost::query()->updateOrCreate(
            ['month' => $this->monthDate()->toDateString()],
            ['mux_cost_usd' => $this->muxCostUsd, 'usd_brl_rate' => $this->usdBrlRate ?: null],
        );

        Notification::make()->title('Custos do mês salvos')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return app(BusinessMetrics::class)->costVsRevenue($this->monthDate());
    }
}
