<?php

use App\Actions\Orders\AcceptOrder;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-07, RF-08, RF-25 — a change made in the manager reaches the customer's tracking page.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
    $this->order = Order::factory()->delivery()->create(['restaurant_id' => $this->dono->restaurant_id, 'tracking_token' => 'tok-timeline']);
    $this->order->history()->create(['status_id' => $this->order->status_id, 'changed_at' => now()]);
});

function trackingSteps(): array
{
    return test()->getJson(route('order.track.status', 'tok-timeline'))->assertOk()->json('steps');
}

it('shows each stage on the customer page with its time as the dono moves the order', function () {
    expect(collect(trackingSteps())->pluck('done')->all())->toBe([true, false, false, false]);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->call('accept', $this->order->id);
    $steps = trackingSteps();
    expect(collect($steps)->pluck('done')->all())->toBe([true, true, false, false])->and($steps[1]['at'])->not->toBeNull();

    $page->call('advance', $this->order->id, 'em_rota');
    $steps = trackingSteps();
    expect(collect($steps)->pluck('done')->all())->toBe([true, true, true, false])->and($steps[2]['label'])->toBe('Saiu para entrega')->and($steps[2]['at'])->not->toBeNull();

    $page->call('openFinalize', $this->order->id)->call('confirmPayment', true);
    $response = $this->getJson(route('order.track.status', 'tok-timeline'))->assertJsonPath('ended', true)->assertJsonPath('status', 'finalizado');

    expect(collect($response->json('steps'))->pluck('done')->all())->toBe([true, true, true, true]);
});

it('renders the same progress on the HTML page', function () {
    app(AcceptOrder::class)->handle($this->order, null, $this->dono);

    $this->get(route('order.track', 'tok-timeline'))->assertOk()->assertSee('Estamos preparando o seu pedido.')->assertSee('is-done', false);
});

it('tells the customer when the store refuses the order', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.orders')->call('refuse', $this->order->id);

    $this->getJson(route('order.track.status', 'tok-timeline'))->assertJsonPath('status', 'recusado')->assertJsonPath('bad', true)->assertJsonPath('steps', []);
    $this->get(route('order.track', 'tok-timeline'))->assertSee('não pôde atender');
});

it('shows the fee the dono set when accepting a "a combinar" delivery', function () {
    $order = Order::factory()->delivery()->create([
        'restaurant_id' => $this->dono->restaurant_id, 'tracking_token' => 'tok-fee',
        'delivery_fee' => null, 'delivery_fee_pending' => true, 'subtotal' => '40.00', 'total' => '40.00',
    ]);

    $this->getJson(route('order.track.status', 'tok-fee'))->assertJsonPath('fee_pending', true)->assertJsonPath('total', 'R$ 40,00');

    app(AcceptOrder::class)->handle($order, '6,00', $this->dono);

    $this->getJson(route('order.track.status', 'tok-fee'))->assertJsonPath('fee_pending', false)->assertJsonPath('total', 'R$ 46,00');
});

it('keeps the customer link of one store from reading another store order', function () {
    $other = User::factory()->create();
    $mine = Order::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'code' => 'D-MINE1', 'tracking_token' => 'tok-mine']);

    // Moving someone else's order is impossible: the manager only finds its own store's orders.
    Livewire::actingAs($other)->test('pages::panel.orders')->call('advance', $mine->id, 'em_preparo')->assertNotFound();

    expect($mine->fresh()->status->slug)->toBe('novo');
});
