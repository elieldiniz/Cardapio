<?php

use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-02, RF-02b, RF-25, RNF-06 — the feed shows the store state and honours category windows.
 * 2026-11-02 is a Monday; America/Porto_Velho is UTC-4.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
});

it('shows "Aberto agora" when the store has no automatic hours', function () {
    feedDish($this->restaurant);

    $this->get(route('feed.show', $this->restaurant))
        ->assertOk()
        ->assertSee('Aberto agora')
        ->assertDontSee('Ver horários');
});

it('shows the closed state with the next opening and the week', function () {
    feedDish($this->restaurant);
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'hours_enabled' => true]);
    BusinessHour::factory()->create(['restaurant_id' => $this->restaurant->id, 'weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']);

    $this->travelTo('2026-11-02 16:00:00'); // Monday 12:00 in Porto Velho

    $this->get(route('feed.show', $this->restaurant))
        ->assertOk()
        ->assertSee('Fechado · abre às 18:00')
        ->assertSee('Ver horários')
        ->assertSee('18:00 às 23:00');
});

it('shows open inside the hours', function () {
    feedDish($this->restaurant);
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'hours_enabled' => true]);
    BusinessHour::factory()->create(['restaurant_id' => $this->restaurant->id, 'weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']);

    $this->travelTo('2026-11-02 22:30:00'); // 18:30 local

    $this->get(route('feed.show', $this->restaurant))->assertSee('Aberto agora');
});

it('hides a category outside its own window and shows it inside', function () {
    $lunch = Category::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'name' => 'Almoço do dia',
        'shows_from' => '11:00',
        'shows_until' => '15:00',
    ]);
    feedDish($this->restaurant, ['name' => 'Prato Executivo'], $lunch);
    feedDish($this->restaurant, ['name' => 'Sobremesa Sempre']);

    $this->travelTo('2026-11-02 20:00:00'); // 16:00 local — after the lunch window

    $this->get(route('feed.show', $this->restaurant))
        ->assertOk()
        ->assertDontSee('Prato Executivo')
        ->assertDontSee('Almoço do dia')
        ->assertSee('Sobremesa Sempre');

    $this->getJson(route('feed.category', [$this->restaurant, $lunch]))->assertNotFound();

    $this->travelTo('2026-11-02 16:00:00'); // 12:00 local — inside

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Prato Executivo')
        ->assertSee('Almoço do dia');

    $this->getJson(route('feed.category', [$this->restaurant, $lunch]))->assertOk();
});

it('shows the category icon in the bars', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Pizzas', 'icon' => '🍕']);
    feedDish($this->restaurant, [], $category);

    $this->get(route('feed.show', $this->restaurant))->assertSee('🍕', false);
});
