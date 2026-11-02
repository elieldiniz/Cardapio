<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-08, RF-08b, RF-02a, RF-06a, UI-01 — the order manager screen.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
});

function placeOrder(string $status, array $attributes = []): Order
{
    $order = Order::factory()->status($status)->create(array_merge(['restaurant_id' => test()->restaurant->id], $attributes));
    $order->items()->create(['name' => 'X-Bacon', 'quantity' => 1, 'unit_price' => '40.00', 'total' => '40.00']);

    return $order;
}

it('requires a logged in owner and shows in the menu', function () {
    $this->get(route('panel.orders'))->assertRedirect(route('login'));

    $this->actingAs($this->dono)->get(route('panel.orders'))->assertOk()->assertSee('Pedidos')->assertSee('Gestor de pedidos');
});

it('puts each order in the right column', function () {
    placeOrder('novo', ['code' => 'D-NOVO1']);
    placeOrder('em_preparo', ['code' => 'D-PREP1']);
    placeOrder('em_rota', ['code' => 'D-ROTA1']);
    placeOrder('finalizado', ['code' => 'D-FIM01']);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');
    $columns = $page->instance()->columns();

    expect($columns['novo']->pluck('code')->all())->toBe(['D-NOVO1'])
        ->and($columns['em_preparo']->pluck('code')->all())->toBe(['D-PREP1'])
        ->and($columns['em_rota']->pluck('code')->all())->toBe(['D-ROTA1'])
        ->and($columns['finalizado']->pluck('code')->all())->toBe(['D-FIM01']);
});

it('keeps refused and cancelled orders visible in Finalizados but out of the period value', function () {
    placeOrder('finalizado', ['code' => 'D-OK001', 'total' => '40.00']);
    placeOrder('recusado', ['code' => 'D-NO001', 'total' => '99.00']);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    expect($page->instance()->columns()['finalizado']->pluck('code')->all())->toContain('D-OK001', 'D-NO001');

    $page->assertSee('Recusado')->assertSeeHtml('R$ 40,00');
    expect($page->instance()->periodTotal())->toBe('R$ 40,00');
});

it('shows the badges for a pending fee, an off hours order and the WhatsApp events', function () {
    placeOrder('novo', ['code' => 'D-FORA1', 'placed_off_hours' => true, 'whatsapp_opened_at' => now(), 'customer_confirmed_at' => now()]);
    placeOrder('novo', ['code' => 'D-TAXA1', 'fulfillment' => 'entrega', 'delivery_fee_pending' => true, 'delivery_fee' => null]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->assertSee('Feito fora do horário')
        ->assertSee('WhatsApp abriu')
        ->assertSee('Cliente confirmou envio')
        ->assertSee('Taxa a combinar')
        ->assertSeeHtml('data-fee-input');
});

it('marks a first time customer', function () {
    $customer = Customer::query()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Ana', 'whatsapp' => '5569999990000', 'orders_count' => 1]);
    placeOrder('novo', ['customer_id' => $customer->id]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertSee('Cliente novo');
});

it('accepts, advances and refuses from the buttons', function () {
    $order = placeOrder('novo');
    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->call('accept', $order->id)->assertHasNoErrors();
    expect($order->fresh()->status->slug)->toBe('em_preparo');

    $page->call('advance', $order->id, 'em_rota');
    expect($order->fresh()->status->slug)->toBe('em_rota');

    // Finishing asks how the order was paid, so the button opens the payment modal first.
    $page->call('advance', $order->id, 'finalizado')->assertSet('showPay', true)->assertSet('payingId', $order->id);
    expect($order->fresh()->status->slug)->toBe('em_rota');

    $page->call('confirmPayment', false);
    expect($order->fresh()->status->slug)->toBe('finalizado');

    $other = placeOrder('novo');
    $page->call('refuse', $other->id);
    expect($other->fresh()->status->slug)->toBe('recusado');
});

it('requires the fee to accept a delivery "a combinar" and then saves it', function () {
    $order = placeOrder('novo', ['fulfillment' => 'entrega', 'delivery_fee' => null, 'delivery_fee_pending' => true, 'subtotal' => '40.00', 'total' => '40.00']);
    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->call('accept', $order->id);
    expect($order->fresh()->status->slug)->toBe('novo');

    $page->set("fees.{$order->id}", '6,00')->call('accept', $order->id);
    expect($order->fresh())->delivery_fee->toBe('6.00')->total->toBe('46.00')->and($order->fresh()->status->slug)->toBe('em_preparo');
});

it('moves a card by dropping it on another column', function () {
    $order = placeOrder('novo');

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->call('sortOrder', $order->id, 0, 'em_preparo');

    expect($order->fresh()->status->slug)->toBe('em_preparo');
});

it('ignores a drop on an unknown column and an invalid drop', function () {
    $order = placeOrder('novo');
    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->call('sortOrder', $order->id, 0, 'lixo');
    $page->call('sortOrder', $order->id, 0, 'finalizado')->assertSet('showPay', false);

    expect($order->fresh()->status->slug)->toBe('novo');
});

it('filters by period and searches by code, customer and product', function () {
    placeOrder('novo', ['code' => 'D-HOJE1', 'customer_name' => 'Maria']);
    placeOrder('novo', ['code' => 'D-ANTES', 'customer_name' => 'João', 'created_at' => now()->subDays(3)]);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders');

    $page->assertSee('D-HOJE1')->assertDontSee('D-ANTES');
    $page->set('period', '7dias')->assertSee('D-HOJE1')->assertSee('D-ANTES');
    $page->set('search', 'João')->assertSee('D-ANTES')->assertDontSee('D-HOJE1');
    $page->set('search', 'X-Bacon')->assertSee('D-HOJE1');
    $page->set('search', 'D-HOJE')->assertSee('D-HOJE1')->assertDontSee('D-ANTES');
});

it('never shows or touches the orders of another store', function () {
    $foreign = Order::factory()->create(['code' => 'D-OUTRA']);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->assertDontSee('D-OUTRA')
        ->call('accept', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->status->slug)->toBe('novo');
});

it('opens and closes the store from the header', function () {
    $page = Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertSee('Loja aberta');

    $page->call('toggleStore')->assertSee('Loja fechada');
    expect($this->restaurant->fresh()->orderSettings()->accepts_orders)->toBeFalse();

    $page->call('toggleStore')->assertSee('Loja aberta');
    expect($this->restaurant->fresh()->orderSettings()->accepts_orders)->toBeTrue();
});
