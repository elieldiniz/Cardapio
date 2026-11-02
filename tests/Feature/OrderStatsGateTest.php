<?php

use App\Models\Category;
use App\Models\FunnelEvent;
use App\Models\MetricsLevel;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Plan;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use App\Services\Orders\OrderStats;
use Livewire\Livewire;

/**
 * RF-21 — simple counters for every plan; funnel, rankings and peak hour only with per-dish metrics.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->planWith = function (string $level) {
        $plan = Plan::factory()->create(['metrics_level_id' => MetricsLevel::where('slug', $level)->value('id'), 'features' => ['orders' => true]]);
        $this->store->update(['plan_id' => $plan->id]);
    };
    $this->order = fn (string $origin = 'instagram') => $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 2]], 'fulfillment' => 'retirada', 'origin' => $origin,
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ])->assertCreated();
    $this->page = fn () => Livewire::actingAs(User::find($this->dono->id))->test('pages::panel.views');
});

it('shows only the simple counters on the cardapio_total level', function () {
    ($this->planWith)('cardapio_total');
    ($this->order)();

    ($this->page)()->assertSeeHtml('data-order-counters')->assertSeeHtml('data-total-orders')->assertDontSeeHtml('data-advanced-stats')->assertDontSeeHtml('data-funnel');
});

it('shows funnel, rankings and peak hour on the per-dish level', function () {
    ($this->planWith)('por_prato');
    FunnelEvent::create(['restaurant_id' => $this->store->id, 'session_token' => 'sessao-um-1', 'event' => 'visit', 'dish_id' => 0, 'occurred_on' => today(), 'hour' => 12]);
    FunnelEvent::create(['restaurant_id' => $this->store->id, 'session_token' => 'sessao-um-1', 'event' => 'add', 'dish_id' => $this->dish->id, 'occurred_on' => today(), 'hour' => 12]);
    ($this->order)();

    ($this->page)()->assertSeeHtml('data-advanced-stats')->assertSee('Funil do pedido')->assertSee('X-Bacon')->assertSee('Enviaram o pedido')->assertSeeHtml('data-peak');
});

it('computes the funnel steps with the rate from the previous step', function () {
    ($this->planWith)('por_prato');
    foreach (['a', 'b', 'c', 'd'] as $i => $letter) {
        FunnelEvent::create(['restaurant_id' => $this->store->id, 'session_token' => "sessao-{$letter}-000", 'event' => 'visit', 'dish_id' => 0, 'occurred_on' => today(), 'hour' => 10]);
    }
    FunnelEvent::create(['restaurant_id' => $this->store->id, 'session_token' => 'sessao-a-000', 'event' => 'add', 'dish_id' => $this->dish->id, 'occurred_on' => today(), 'hour' => 10]);
    ($this->order)();
    Order::query()->update(['whatsapp_opened_at' => now(), 'customer_confirmed_at' => now()]);

    $funnel = collect(app(OrderStats::class)->advanced($this->store->fresh(), 30)['funnel'])->keyBy('key');

    expect($funnel['visit']['count'])->toBe(4)->and($funnel['add']['count'])->toBe(1)->and($funnel['add']['rate'])->toBe(25)
        ->and($funnel['order']['count'])->toBe(1)->and($funnel['confirmed']['count'])->toBe(1)->and($funnel['confirmed']['rate'])->toBe(100);
});

it('counts orders, sales and origins in the counters and ignores refused ones', function () {
    ($this->planWith)('cardapio_total');
    ($this->order)('instagram');
    ($this->order)('instagram');
    ($this->order)('whatsapp');
    Order::orderBy('id')->first()->update(['status_id' => OrderStatus::idFor('recusado')]);

    $counters = app(OrderStats::class)->counters($this->store->fresh(), 30);

    expect($counters['orders'])->toBe(2)->and($counters['by_origin'])->toBe(['instagram' => 1, 'whatsapp' => 1]);
});
