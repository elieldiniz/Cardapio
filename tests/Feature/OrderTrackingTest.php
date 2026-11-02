<?php

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-06a, RF-07, RF-07a, CT-02, CT-03, UI-02 — the customer tracking page and WhatsApp events.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->order = Order::factory()->delivery()->create([
        'restaurant_id' => $this->restaurant->id,
        'code' => 'D-X6ECG',
        'tracking_token' => 'tok-abc-123',
        'customer_name' => 'Ana Souza Pereira',
        'customer_phone' => '5569999990000',
        'address_street' => 'Rua das Flores',
        'address_number' => '120',
        'address_complement' => 'Apto 301',
        'neighborhood' => 'Centro',
        'notes' => 'Deixar com o porteiro',
    ]);
    $this->order->history()->create(['status_id' => $this->order->status_id, 'changed_at' => now()]);
    $this->order->items()->create(['name' => 'X-Bacon', 'quantity' => 2, 'unit_price' => '20.00', 'total' => '40.00', 'notes' => 'Sem cebola secreta', 'selections' => ['complements' => [['group' => 'Adicionais', 'name' => 'Bacon', 'price' => '4.00', 'quantity' => 1]]]]);
});

it('renders the tracking page without exposing personal data', function () {
    $response = $this->get(route('order.track', 'tok-abc-123'))->assertOk()
        ->assertSee('Pedido D-X6ECG')
        ->assertSee('Olá, Ana!')
        ->assertSee('Rua das Flores · Centro')
        ->assertSee('X-Bacon')
        ->assertSee('Adicionais: Bacon')
        ->assertSee('Falar com a loja')
        ->assertSee('Ver cardápio')
        ->assertHeader('X-Robots-Tag', 'noindex');

    foreach (['Souza', 'Pereira', '5569999990000', '99999-0000', '120', 'Apto 301', 'porteiro', 'cebola secreta'] as $secret) {
        $response->assertDontSee($secret);
    }
});

it('returns the same data as JSON with no personal data and no-store cache', function () {
    $response = $this->getJson(route('order.track.status', 'tok-abc-123'))->assertOk()
        ->assertJsonPath('code', 'D-X6ECG')
        ->assertJsonPath('status', 'novo')
        ->assertJsonPath('first_name', 'Ana')
        ->assertJsonPath('address', 'Rua das Flores · Centro')
        ->assertJsonPath('ended', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $body = json_encode($response->json());

    foreach (['Souza', 'Pereira', '5569999990000', 'Apto', 'porteiro', 'cebola secreta'] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

it('shows the timeline with the time of each reached step', function () {
    $this->order->update(['status_id' => OrderStatus::idFor('em_rota')]);
    $this->order->history()->create(['status_id' => OrderStatus::idFor('em_preparo'), 'changed_at' => now()->subMinutes(10)]);
    $this->order->history()->create(['status_id' => OrderStatus::idFor('em_rota'), 'changed_at' => now()]);

    $steps = $this->getJson(route('order.track.status', 'tok-abc-123'))->json('steps');

    expect($steps)->toHaveCount(4)
        ->and(collect($steps)->pluck('done')->all())->toBe([true, true, true, false])
        ->and($steps[2]['label'])->toBe('Saiu para entrega')
        ->and($steps[2]['current'])->toBeTrue()
        ->and($steps[2]['at'])->not->toBeNull()
        ->and($steps[3]['at'])->toBeNull();
});

it('uses the pickup wording for a pickup order', function () {
    $this->order->update(['fulfillment' => 'retirada']);

    $steps = $this->getJson(route('order.track.status', 'tok-abc-123'))->json('steps');

    expect($steps[2]['label'])->toBe('Pronto para retirada')->and($steps[3]['label'])->toBe('Retirado');
});

it('answers 404 for an unknown token and never leaks another order', function () {
    $this->get(route('order.track', 'nope'))->assertNotFound();
    $this->getJson(route('order.track.status', 'nope'))->assertNotFound();

    $other = Order::factory()->create(['tracking_token' => 'tok-other', 'code' => 'Z-ZZZZZ']);

    $this->get(route('order.track', 'tok-abc-123'))->assertDontSee('Z-ZZZZZ');
});

it('expires 7 days after the order ended: 200 at 6 days, 404 at 8 days', function () {
    $this->order->update(['status_id' => OrderStatus::idFor('finalizado'), 'finished_at' => now()->subDays(6)]);
    $this->get(route('order.track', 'tok-abc-123'))->assertOk()->assertSee('Pedido entregue');

    $this->order->update(['finished_at' => now()->subDays(8)]);
    $this->get(route('order.track', 'tok-abc-123'))->assertNotFound();
    $this->getJson(route('order.track.status', 'tok-abc-123'))->assertNotFound();
});

it('expires a refused or cancelled order the same way', function () {
    $this->order->update(['status_id' => OrderStatus::idFor('recusado'), 'cancelled_at' => now()->subDays(8)]);

    $this->get(route('order.track', 'tok-abc-123'))->assertNotFound();

    $this->order->update(['cancelled_at' => now()->subDay()]);

    $this->get(route('order.track', 'tok-abc-123'))->assertOk()->assertSee('A loja não pôde atender');
});

it('records the WhatsApp events once, with 204', function () {
    expect($this->order->whatsapp_opened_at)->toBeNull()->and($this->order->customer_confirmed_at)->toBeNull();

    $this->postJson(route('feed.orders.whatsapp', [$this->restaurant, 'tok-abc-123']), ['event' => 'opened'])->assertNoContent();

    $opened = $this->order->fresh()->whatsapp_opened_at;
    expect($opened)->not->toBeNull()->and($this->order->fresh()->customer_confirmed_at)->toBeNull();

    $this->travel(5)->minutes();
    $this->postJson(route('feed.orders.whatsapp', [$this->restaurant, 'tok-abc-123']), ['event' => 'opened'])->assertNoContent();

    expect($this->order->fresh()->whatsapp_opened_at->equalTo($opened))->toBeTrue();

    $this->postJson(route('feed.orders.whatsapp', [$this->restaurant, 'tok-abc-123']), ['event' => 'confirmed'])->assertNoContent();

    expect($this->order->fresh()->customer_confirmed_at)->not->toBeNull();
});

it('does not confirm anything when the customer says "Ainda não"', function () {
    $this->postJson(route('feed.orders.whatsapp', [$this->restaurant, 'tok-abc-123']), ['event' => 'not_sent'])->assertNoContent();

    expect($this->order->fresh())->whatsapp_opened_at->toBeNull()->customer_confirmed_at->toBeNull();
});

it('rejects an invalid event and a token of another store', function () {
    $this->postJson(route('feed.orders.whatsapp', [$this->restaurant, 'tok-abc-123']), ['event' => 'hack'])->assertUnprocessable();

    $other = User::factory()->create()->restaurant;

    $this->postJson(route('feed.orders.whatsapp', [$other, 'tok-abc-123']), ['event' => 'opened'])->assertNotFound();
});
