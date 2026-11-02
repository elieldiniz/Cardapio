<?php

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-06b, RF-06c, RF-06d, UI-02 — what the checkout gets from the page.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
});

it('hands the checkout the zones, the free delivery threshold, the minimum and the Pix flag', function () {
    RestaurantOrderSetting::factory()->create([
        'restaurant_id' => $this->restaurant->id, 'delivery_mode' => 'bairro', 'accepts_other_neighborhood' => true,
        'free_delivery_over' => '80.00', 'min_order_delivery' => '25.00', 'pix_key' => 'chave',
    ]);
    DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);
    DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Inativo', 'is_active' => false]);
    feedDish($this->restaurant, [], $this->category);

    $config = orderConfigOf($this->get(route('feed.show', $this->restaurant)));

    expect($config['delivery']['zones'])->toHaveCount(1)
        ->and($config['delivery']['zones'][0])->toMatchArray(['name' => 'Centro', 'fee' => '5.00'])
        ->and($config['delivery']['accepts_other'])->toBeTrue()
        ->and($config['pix'])->toBeTrue()
        ->and($config['privacy_url'])->toBe(route('legal.privacy', ['loja' => $this->restaurant->slug]))
        ->and($config['whatsapp_url'])->toContain('__TOKEN__');
});

it('tells the checkout the store is taking orders off hours when it allows it', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'hours_enabled' => true, 'accepts_when_closed' => true]);
    feedDish($this->restaurant, [], $this->category);

    $config = orderConfigOf($this->get(route('feed.show', $this->restaurant)));

    expect($config['accepting'])->toBeTrue()->and($config['off_hours'])->toBeTrue();
});

it('has no Pix flag when the store has no key', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'pix_key' => null]);
    feedDish($this->restaurant, [], $this->category);

    expect(orderConfigOf($this->get(route('feed.show', $this->restaurant)))['pix'])->toBeFalse();
});
