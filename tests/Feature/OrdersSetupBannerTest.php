<?php

use App\Models\Category;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-04 — the cardápio only takes orders once the store has a WhatsApp number; the panel says so.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('warns the owner that the cardápio does not take orders yet while there is no WhatsApp', function () {
    $this->actingAs($this->dono)->get(route('panel.home'))->assertOk()
        ->assertSee('data-orders-setup-banner', false)
        ->assertSee('Seu cardápio ainda não recebe pedidos')
        ->assertSee(route('panel.settings'), false);
});

it('stops warning once the WhatsApp is informed', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);

    $this->actingAs($this->dono->fresh())->get(route('panel.home'))->assertOk()->assertDontSee('data-orders-setup-banner', false);
});

it('really keeps the public cardápio without a cart until the WhatsApp is set, then turns ordering on', function () {
    feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));

    $this->get(route('feed.show', $this->store))->assertOk()->assertDontSee('data-order-config', false);

    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);

    $this->get(route('feed.show', $this->store->fresh()))->assertOk()->assertSee('data-order-config', false);
});
