<?php

use App\Models\Category;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-22, CT-05 — the courier's own page.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->joao = Courier::create(['restaurant_id' => $this->store->id, 'name' => 'João Silva', 'fee_per_delivery' => '6.00']);
    $this->maria = Courier::create(['restaurant_id' => $this->store->id, 'name' => 'Maria Souza']);
    $this->delivery = function (Courier $courier) {
        $this->postJson(route('feed.orders.store', $this->store), [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'entrega',
            'address' => ['street' => 'Rua das Flores', 'number' => '12', 'neighborhood' => 'Centro'],
            'customer' => ['name' => 'Ana Souza', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
        ])->assertCreated();
        $order = Order::latest('id')->first();
        $order->update(['status_id' => OrderStatus::idFor('em_rota'), 'courier_id' => $courier->id, 'dispatched_at' => now()]);

        return $order;
    };
});

it('shows only the first name and the address, never the WhatsApp or the surname', function () {
    ($this->delivery)($this->joao);

    $this->get(route('courier.show', $this->joao->token))->assertOk()->assertSee('Ana')->assertSee('Rua das Flores')
        ->assertDontSee('Souza')->assertDontSee('99999-0000')->assertDontSee('5569999990000');
});

it('does not show the orders of another courier', function () {
    ($this->delivery)($this->maria);

    $this->get(route('courier.show', $this->joao->token))->assertOk()->assertDontSee('Rua das Flores');
});

it('answers 404 for an unknown, revoked or paused link', function () {
    $this->get(route('courier.show', 'nao-existe'))->assertNotFound();

    $this->joao->update(['revoked_at' => now()]);
    $this->get(route('courier.show', $this->joao->token))->assertNotFound();

    $this->joao->update(['revoked_at' => null, 'is_active' => false]);
    $this->get(route('courier.show', $this->joao->token))->assertNotFound();
});

it('marks only its own order as delivered', function () {
    $mine = ($this->delivery)($this->joao);
    $theirs = ($this->delivery)($this->maria);

    $this->post(route('courier.deliver', [$this->joao->token, $mine->id]))->assertRedirect();
    $this->post(route('courier.deliver', [$this->joao->token, $theirs->id]))->assertNotFound();

    expect($mine->fresh()->delivered_at)->not->toBeNull()->and($theirs->fresh()->delivered_at)->toBeNull();
});
