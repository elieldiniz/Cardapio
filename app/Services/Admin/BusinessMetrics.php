<?php

namespace App\Services\Admin;

use App\Models\GenerationLedger;
use App\Models\MonthlyCost;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\VideoGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The super admin's business health figures (US-7.1).
 */
class BusinessMetrics
{
    /**
     * Monthly recurring revenue in cents: the plan price of every currently
     * active subscription.
     */
    public function mrrCents(): int
    {
        return (int) Subscription::query()
            ->where('stripe_status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->join('plans', 'plans.stripe_price_id', '=', 'subscriptions.stripe_price')
            ->sum('plans.price_cents');
    }

    /**
     * Active (not suspended) restaurants per plan name.
     *
     * @return Collection<string, int>
     */
    public function activeRestaurantsByPlan(): Collection
    {
        $counts = Restaurant::query()
            ->whereHas('status', fn ($status) => $status->where('slug', 'ativo'))
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        return Plan::query()->orderBy('price_cents')->get()
            ->mapWithKeys(fn (Plan $plan) => [$plan->name => (int) ($counts[$plan->id] ?? 0)]);
    }

    public function newRestaurants(?CarbonImmutable $month = null): int
    {
        [$from, $to] = $this->monthRange($month);

        return Restaurant::query()->whereBetween('created_at', [$from, $to])->count();
    }

    /**
     * Restaurants whose paid subscription ended within the month.
     */
    public function canceledRestaurants(?CarbonImmutable $month = null): int
    {
        [$from, $to] = $this->monthRange($month);

        return Subscription::query()
            ->where('stripe_status', 'canceled')
            ->whereBetween('ends_at', [$from, $to])
            ->distinct()
            ->count('restaurant_id');
    }

    /**
     * Share of the period's signups (every account starts on Grátis) that
     * went on to start a paid subscription, as a percentage.
     */
    public function freeToPaidConversionRate(?CarbonImmutable $month = null): float
    {
        [$from, $to] = $this->monthRange($month);

        $signups = Restaurant::query()->whereBetween('created_at', [$from, $to]);
        $total = (clone $signups)->count();

        if ($total === 0) {
            return 0.0;
        }

        $upgraded = (clone $signups)
            ->whereHas('subscriptions', fn ($subscription) => $subscription->whereNotIn('stripe_status', ['incomplete', 'incomplete_expired']))
            ->count();

        return round($upgraded / $total * 100, 1);
    }

    /**
     * AI spend for the month: the provider cost recorded on each generation (US-7.4, US-7.6).
     */
    public function aiCostUsd(?CarbonImmutable $month = null): float
    {
        [$from, $to] = $this->monthRange($month);

        return round((float) VideoGeneration::query()->whereBetween('created_at', [$from, $to])->sum('cost_usd'), 4);
    }

    /**
     * One-time addon purchases paid within the month, in cents (US-7.6).
     */
    public function addonRevenueCents(?CarbonImmutable $month = null): int
    {
        [$from, $to] = $this->monthRange($month);

        return (int) GenerationLedger::query()
            ->whereHas('type', fn ($type) => $type->where('slug', 'compra'))
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_cents');
    }

    /**
     * Costs (USD) against revenue (BRL) for the month (US-7.6). The USD→BRL
     * rate and Mux bill are entered by the super admin; without a rate the
     * margin cannot be computed and is null.
     *
     * @return array{ai_cost_usd: float, mux_cost_usd: float, total_cost_usd: float, usd_brl_rate: ?float, total_cost_brl_cents: ?int, mrr_cents: int, addon_revenue_cents: int, revenue_cents: int, margin_cents: ?int}
     */
    public function costVsRevenue(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $entered = MonthlyCost::forMonth($month);

        $ai = $this->aiCostUsd($month);
        $mux = (float) ($entered?->mux_cost_usd ?? 0);
        $rate = $entered?->usd_brl_rate !== null ? (float) $entered->usd_brl_rate : null;
        $totalUsd = round($ai + $mux, 4);
        $costBrlCents = $rate !== null ? (int) round($totalUsd * $rate * 100) : null;

        $mrr = $this->mrrCents();
        $addon = $this->addonRevenueCents($month);
        $revenue = $mrr + $addon;

        return [
            'ai_cost_usd' => $ai,
            'mux_cost_usd' => $mux,
            'total_cost_usd' => $totalUsd,
            'usd_brl_rate' => $rate,
            'total_cost_brl_cents' => $costBrlCents,
            'mrr_cents' => $mrr,
            'addon_revenue_cents' => $addon,
            'revenue_cents' => $revenue,
            'margin_cents' => $costBrlCents !== null ? $revenue - $costBrlCents : null,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthRange(?CarbonImmutable $month): array
    {
        $month ??= CarbonImmutable::now();

        return [$month->startOfMonth(), $month->endOfMonth()];
    }
}
