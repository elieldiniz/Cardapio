<?php

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\DishStatus;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-04, RF-06e, UI-02, RF-25 — the feed carries the ordering UI only when the store takes orders.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'icon' => '🍔']);
});

it('stays view-only when the store has no WhatsApp number', function () {
    feedDish($this->restaurant, [], $this->category);

    $this->get(route('feed.show', $this->restaurant))
        ->assertOk()
        ->assertDontSee('data-order-config', false)
        ->assertDontSee('data-cart-bar', false)
        ->assertDontSee('data-add-to-cart', false);
});

it('renders the add button, the cart bar and the order sheet when the store takes orders', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, [], $this->category);

    $this->get(route('feed.show', $this->restaurant))
        ->assertOk()
        ->assertSee('data-order-config', false)
        ->assertSee('data-cart-bar', false)
        ->assertSee('data-order-sheet', false)
        ->assertSee('data-add-to-cart', false)
        ->assertSee('data-sheet-add', false)
        ->assertSee('+ Adicionar');
});

it('hides the add button of a sold out dish', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, ['status_id' => DishStatus::idFor('esgotado'), 'name' => 'Esgotadinho'], $this->category);

    $html = $this->get(route('feed.show', $this->restaurant))->assertOk()->getContent();

    expect($html)->toMatch('/data-add-to-cart\\s+hidden/');
});

it('embeds the catalog with variations, groups and the store delivery rules', function () {
    RestaurantOrderSetting::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'delivery_mode' => 'bairro',
        'free_delivery_over' => '80.00',
        'min_order_delivery' => '25.00',
    ]);
    DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);
    $dish = feedDish($this->restaurant, ['name' => 'X-Bacon', 'price' => '28.90'], $this->category);
    $dish->variants()->create(['name' => 'Grande', 'price' => '40.00']);
    $group = OptionGroup::factory()->required()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Ponto da carne']);
    Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Ao ponto']);
    Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Indisponível', 'is_available' => false]);
    $dish->optionGroups()->attach($group->id, ['display_order' => 1]);

    $config = orderConfigOf($this->get(route('feed.show', $this->restaurant)));

    expect($config['enabled'])->toBeTrue()
        ->and($config['accepting'])->toBeTrue()
        ->and($config['orders_url'])->toBe(route('feed.orders.store', $this->restaurant))
        ->and($config['delivery']['mode'])->toBe('bairro')
        ->and($config['delivery']['zones'][0]['name'])->toBe('Centro')
        ->and($config['delivery']['free_over'])->toBe('80.00')
        ->and($config['delivery']['min_order'])->toBe('25.00');

    $entry = $config['dishes'][(string) $dish->id];

    expect($entry['name'])->toBe('X-Bacon')
        ->and($entry['price'])->toBe('28.90')
        ->and($entry['orderable'])->toBeTrue()
        ->and($entry['icon'])->toBe('🍔')
        ->and($entry['variants'][0]['name'])->toBe('Grande')
        ->and($entry['groups'][0]['name'])->toBe('Ponto da carne')
        ->and($entry['groups'][0]['required'])->toBeTrue()
        ->and(collect($entry['groups'][0]['options'])->pluck('name')->all())->toBe(['Ao ponto']);
});

it('marks a sold out dish as not orderable in the catalog', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = feedDish($this->restaurant, ['status_id' => DishStatus::idFor('esgotado')], $this->category);

    $config = orderConfigOf($this->get(route('feed.show', $this->restaurant)));

    expect($config['dishes'][(string) $dish->id]['orderable'])->toBeFalse();
});

it('tells the cart the store is not accepting orders when it is closed', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'accepts_orders' => false]);
    feedDish($this->restaurant, [], $this->category);

    $config = orderConfigOf($this->get(route('feed.show', $this->restaurant)));

    expect($config['accepting'])->toBeFalse()->and($config['closed_label'])->toBe('Fechado agora');
});

it('is not injected into the appearance preview', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, [], $this->category);
    $dono = $this->restaurant->users()->first();

    $this->actingAs($dono)->get(route('panel.appearance.preview'))
        ->assertOk()
        ->assertDontSee('data-order-config', false)
        ->assertDontSee('data-cart-bar', false);
});

it('never uses Livewire on the feed route', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, [], $this->category);

    $this->get(route('feed.show', $this->restaurant))->assertDontSee('livewire', false)->assertDontSee('wire:', false);
});
