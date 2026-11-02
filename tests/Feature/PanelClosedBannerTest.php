<?php

use App\Models\BusinessHour;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * UI-04, RF-02 — the closed-store banner appears only while the store is closed.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
});

it('does not show the banner while the store is open', function () {
    $this->actingAs($this->dono)->get(route('panel.home'))
        ->assertOk()
        ->assertDontSeeHtml('data-store-closed-banner');
});

it('shows the banner when the store is switched off manually', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'accepts_orders' => false]);

    $this->actingAs($this->dono)->get(route('panel.home'))
        ->assertOk()
        ->assertSeeHtml('data-store-closed-banner')
        ->assertSee('fechada para pedidos');
});

it('shows the banner with the next opening when outside the hours', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'hours_enabled' => true]);
    BusinessHour::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']);

    $this->travelTo('2026-11-02 16:00:00'); // Monday 12:00 in Porto Velho

    $this->actingAs($this->dono)->get(route('panel.home'))
        ->assertSeeHtml('data-store-closed-banner')
        ->assertSee('Fechado · abre às 18:00');

    $this->travelTo('2026-11-02 22:30:00'); // 18:30 local — open

    $this->actingAs($this->dono)->get(route('panel.home'))->assertDontSeeHtml('data-store-closed-banner');
});
