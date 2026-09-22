<?php

use App\Filament\Pages\CostsVsRevenue;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\MonthlyCost;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VideoAddonPackage;
use App\Models\VideoGeneration;
use App\Services\Admin\BusinessMetrics;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));
});

test('ai cost sums only the current months generation rows', function () {
    VideoGeneration::factory()->ready()->create(['cost_usd' => 1.25]);
    VideoGeneration::factory()->ready()->create(['cost_usd' => 0.75]);
    VideoGeneration::factory()->errored()->create(['cost_usd' => null]);
    VideoGeneration::factory()->ready()->create(['cost_usd' => 40, 'created_at' => now()->subMonth()]);
    VideoGeneration::factory()->ready()->create(['cost_usd' => 40, 'created_at' => now()->addMonth()]);

    expect(app(BusinessMetrics::class)->aiCostUsd())->toBe(2.0);
});

it('compares ai plus mux cost with mrr plus addon revenue', function () {
    Plan::where('name', 'Pro')->update(['stripe_price_id' => 'price_pro', 'price_cents' => 12900]);
    $restaurant = Restaurant::factory()->create();
    $restaurant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_pro']);
    GenerationLedger::create(['restaurant_id' => $restaurant->id, 'type_id' => GenerationLedgerType::idFor('compra'), 'quantity' => 10, 'amount_cents' => 2900, 'reference' => 'stripe_checkout:cs_1']);
    VideoGeneration::factory()->ready()->create(['cost_usd' => 4]);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CostsVsRevenue::class)
        ->assertSeeHtml('data-margin')
        ->assertSee('Informe a cotação do dólar')
        ->set('muxCostUsd', '6')
        ->set('usdBrlRate', '5.5')
        ->call('save')
        ->assertHasNoErrors();

    expect(MonthlyCost::forMonth(now())->mux_cost_usd)->toBe('6.00');

    $summary = app(BusinessMetrics::class)->costVsRevenue();

    // Costs: (4 + 6) USD × 5.5 = R$ 55,00. Revenue: R$ 129,00 + R$ 29,00 = R$ 158,00.
    expect($summary)->toMatchArray([
        'ai_cost_usd' => 4.0,
        'mux_cost_usd' => 6.0,
        'total_cost_brl_cents' => 5500,
        'mrr_cents' => 12900,
        'addon_revenue_cents' => 2900,
        'revenue_cents' => 15800,
        'margin_cents' => 10300,
    ]);

    Livewire::test(CostsVsRevenue::class)->assertSee('R$ 103,00')->assertSee('R$ 158,00');
});

it('stores the paid amount of an addon purchase for revenue reporting', function () {
    $restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_123']);
    $package = VideoAddonPackage::create(['name' => 'P10', 'generations_count' => 10, 'price_cents' => 2900, 'stripe_price_id' => 'price_p10']);

    stripeWebhook('checkout.session.completed', [
        'id' => 'cs_9', 'object' => 'checkout.session', 'customer' => 'cus_123', 'mode' => 'payment',
        'payment_status' => 'paid', 'amount_total' => 2610, 'metadata' => ['addon_package_id' => (string) $package->id],
    ]);

    expect(app(BusinessMetrics::class)->addonRevenueCents())->toBe(2610);
});
