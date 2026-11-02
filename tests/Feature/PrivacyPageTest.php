<?php

use App\Models\RestaurantOrderSetting;
use App\Models\RestaurantStatus;
use App\Models\User;

/**
 * RF-06f, RNF-08 — the general policy and the per-store block.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
    $this->restaurant->update(['name' => 'Docin', 'slug' => 'docin']);
});

it('keeps the general page without the store parameter', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Política de Privacidade')
        ->assertDontSeeHtml('data-store-privacy')
        ->assertSee('Clientes que fazem pedido')
        ->assertSee('30 dias');
});

it('shows the store block for a valid store', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'whatsapp_number' => '5569996041447']);

    $this->get(route('legal.privacy', ['loja' => 'docin']))
        ->assertOk()
        ->assertSeeHtml('data-store-privacy')
        ->assertSee('Seu pedido em Docin')
        ->assertSee('apagados em até 30 dias')
        ->assertSeeHtml('phone=5569996041447');
});

it('shows the store block without a link when the store has no WhatsApp', function () {
    $this->get(route('legal.privacy', ['loja' => 'docin']))->assertOk()->assertSeeHtml('data-store-privacy')->assertDontSeeHtml('api.whatsapp.com');
});

it('falls back to the general page for an unknown or suspended store', function () {
    $this->get(route('legal.privacy', ['loja' => 'nao-existe']))->assertOk()->assertDontSeeHtml('data-store-privacy');

    $this->restaurant->update(['status_id' => RestaurantStatus::idFor('suspenso')]);

    $this->get(route('legal.privacy', ['loja' => 'docin']))->assertOk()->assertDontSeeHtml('data-store-privacy');
});

it('is still the same named route', function () {
    expect(route('legal.privacy', absolute: false))->toBe('/privacidade');
});
