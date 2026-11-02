<?php

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Orders\CustomerNoticeBuilder;
use App\Support\PanelNavigation;
use Livewire\Livewire;

/**
 * RF-08, RF-08a, UI-03 — real time board: poll, menu counter, sound and customer notices.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
});

it('polls on its own so a new order shows up without a reload', function () {
    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->assertSeeHtml('wire:poll.8s')->assertDontSee('D-CHEGOU');

    Order::factory()->create(['restaurant_id' => $this->restaurant->id, 'code' => 'D-CHEGOU']);

    $page->call('$refresh')->assertSee('D-CHEGOU');
});

it('counts only the orders in status novo for the menu badge', function () {
    Order::factory()->count(2)->create(['restaurant_id' => $this->restaurant->id]);
    Order::factory()->status('em_preparo')->create(['restaurant_id' => $this->restaurant->id]);
    Order::factory()->create();

    $this->actingAs($this->dono);

    $badge = collect(PanelNavigation::groups())->flatMap(fn ($group) => $group['items'])->firstWhere('route', 'panel.orders')['badge'];

    expect($badge)->toBe(2);

    $this->get(route('panel.home'))->assertOk()->assertSeeHtml('data-nav-badge="orders"')->assertSeeHtml('count: 2');
});

it('tells the browser how many new orders there are on every render', function () {
    Order::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertDispatched('orders-count', count: 1);
});

it('refreshes the counter when an order leaves "novo"', function () {
    $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->assertDispatched('orders-count', count: 1)
        ->call('accept', $order->id)
        ->assertDispatched('orders-count', count: 0);
});

it('offers the sound toggle and the desktop WhatsApp option, off until the dono turns them on', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->assertSeeHtml('data-sound-toggle')
        ->assertSeeHtml('data-wa-desktop')
        ->assertSee('Abrir no aplicativo WhatsApp deste computador')
        ->assertSeeHtml('orderAlerts');
});

it('builds a ready message for each stage with the tracking link', function () {
    $builder = new CustomerNoticeBuilder;
    $order = Order::factory()->delivery()->status('em_preparo')->create(['restaurant_id' => $this->restaurant->id, 'code' => 'D-X6ECG', 'customer_name' => 'Ana Souza']);

    expect($builder->message($order))->toContain('Olá, Ana!')->toContain('D-X6ECG')->toContain('sendo preparado')->toContain(route('order.track', $order->tracking_token));

    $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
    expect($builder->message($order->fresh()))->toContain('saiu para entrega');

    $order->update(['fulfillment' => 'retirada']);
    expect($builder->message($order->fresh()))->toContain('pronto para retirada');

    $order->update(['status_id' => OrderStatus::idFor('recusado')]);
    expect($builder->message($order->fresh()))->toContain('não conseguimos atender');

    $order->update(['status_id' => OrderStatus::idFor('novo')]);
    expect($builder->message($order->fresh()))->toBeNull()->and($builder->link($order->fresh()))->toBeNull();
});

it('puts the notice link on the card with the customer number encoded', function () {
    Order::factory()->status('em_preparo')->create(['restaurant_id' => $this->restaurant->id, 'customer_phone' => '5569999990000', 'customer_name' => 'Ana']);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->assertSeeHtml('data-notify')
        ->assertSeeHtml('phone=5569999990000');
});

it('has no notice link without a customer number', function () {
    Order::factory()->status('em_preparo')->create(['restaurant_id' => $this->restaurant->id, 'customer_phone' => null]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertDontSeeHtml('data-notify');
});
