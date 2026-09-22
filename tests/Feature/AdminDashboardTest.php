<?php

use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\BusinessMetrics;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedReferenceData();
    Plan::where('name', 'Básico')->update(['stripe_price_id' => 'price_basico', 'price_cents' => 6900]);
    Plan::where('name', 'Pro')->update(['stripe_price_id' => 'price_pro', 'price_cents' => 12900]);
});

function subscribe(Restaurant $restaurant, string $price, string $status, array $extra = []): Subscription
{
    return $restaurant->subscriptions()->create(array_merge([
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(8),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
    ], $extra));
}

test('mrr sums only currently active subscriptions', function () {
    subscribe(Restaurant::factory()->create(), 'price_basico', 'active');
    subscribe(Restaurant::factory()->create(), 'price_pro', 'active');
    subscribe(Restaurant::factory()->create(), 'price_pro', 'canceled', ['ends_at' => now()->subDay()]);
    subscribe(Restaurant::factory()->create(), 'price_pro', 'incomplete');
    subscribe(Restaurant::factory()->create(), 'price_basico', 'active', ['ends_at' => now()->subHour()]);

    expect(app(BusinessMetrics::class)->mrrCents())->toBe(6900 + 12900);
});

test('conversion rate divides upgraded restaurants by total free plan signups in the period', function () {
    $month = CarbonImmutable::parse('2026-09-15');
    $this->travelTo($month);

    $signups = Restaurant::factory()->count(4)->create();
    subscribe($signups[0], 'price_basico', 'active');
    subscribe($signups[1], 'price_pro', 'canceled', ['ends_at' => now()]);
    subscribe($signups[2], 'price_pro', 'incomplete'); // never paid: not an upgrade

    // A signup from last month that upgraded now does not count in September.
    $older = Restaurant::factory()->create(['created_at' => $month->subMonth()]);
    subscribe($older, 'price_pro', 'active');

    $metrics = app(BusinessMetrics::class);

    expect($metrics->freeToPaidConversionRate($month))->toBe(50.0)
        ->and($metrics->newRestaurants($month))->toBe(4)
        ->and($metrics->canceledRestaurants($month))->toBe(1);
});

it('counts active restaurants per plan and renders the dashboard for a super admin', function () {
    Restaurant::factory()->count(2)->create();
    Restaurant::factory()->create(['plan_id' => Plan::where('name', 'Pro')->value('id')]);
    Restaurant::factory()->create(['status_id' => RestaurantStatus::idFor('suspenso')]);

    expect(app(BusinessMetrics::class)->activeRestaurantsByPlan()->all())
        ->toBe(['Grátis' => 2, 'Básico' => 0, 'Pro' => 1]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('MRR')
        ->assertSee('Conversão Grátis → pago')
        ->assertSee('Clientes ativos por plano');
});
