<?php

use App\Models\Category;
use App\Models\DishStatus;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use App\Services\Feed\FeedBuilder;

/**
 * RF-16 — promotional price, "destaque" and the Ofertas filter.
 */
beforeEach(function () {
    seedReferenceData();
    $this->store = User::factory()->create()->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '40.00', 'promo_price' => '30.00'], $this->category);
    $this->order = fn () => $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ]);
});

it('charges the promotional price while it is valid and the normal price after it ends', function () {
    ($this->order)()->assertCreated()->assertJsonPath('total', '30.00');

    $this->dish->update(['promo_ends_at' => now()->addDay()]);
    ($this->order)()->assertCreated()->assertJsonPath('total', '30.00');

    $this->dish->update(['promo_ends_at' => now()->subMinute()]);
    ($this->order)()->assertCreated()->assertJsonPath('total', '40.00');

    expect(Order::query()->orderBy('id')->get()->pluck('total')->all())->toBe(['30.00', '30.00', '40.00']);
});

it('ignores a promotion that is not lower than the price', function () {
    $this->dish->update(['promo_price' => '45.00']);

    ($this->order)()->assertCreated()->assertJsonPath('total', '40.00');
});

it('keeps the promotional price of a past order after the promotion ends', function () {
    $response = ($this->order)()->assertCreated();
    $this->dish->update(['promo_ends_at' => now()->subMinute()]);

    expect(Order::query()->where('code', $response->json('code'))->first()->items->sole())->unit_price->toBe('30.00');
});

it('shows "de R$ X por R$ Y" on the dish and an Ofertas filter with the offer tag on the grid', function () {
    $this->get(route('feed.show', $this->store))
        ->assertOk()
        ->assertSee('R$ 30,00')
        ->assertSee('R$ 40,00')
        ->assertSeeHtml('data-field="old_price"')
        ->assertSee('Ofertas')
        ->assertSee('Oferta')
        ->assertSeeHtml('data-sale="1"');
});

it('shows no offers filter and no old price when nothing is on sale', function () {
    $this->dish->update(['promo_price' => null]);

    $this->get(route('feed.show', $this->store))->assertOk()->assertDontSee('Ofertas')->assertDontSeeHtml('data-sale="1"');
});

it('puts featured products first on the grid', function () {
    $normal = feedDish($this->store, ['name' => 'Comum', 'display_order' => 1], $this->category);
    $featured = feedDish($this->store, ['name' => 'Destacado', 'is_featured' => true, 'display_order' => 9], $this->category);

    $grid = app(FeedBuilder::class)->gridFor($this->store);

    expect(collect($grid)->pluck('name')->first())->toBe('Destacado')->and(collect($grid)->firstWhere('name', 'Destacado')['featured'])->toBeTrue();
});

it('marks weighed products with "/ kg" next to the price', function () {
    $torta = feedDish($this->store, ['name' => 'Torta', 'price' => '59.90', 'sale_type' => 'kg'], $this->category);

    $present = app(FeedBuilder::class)->present($torta->load('variants', 'badges', 'photos', 'optionGroups'));

    expect($present['price'])->toBe('R$ 59,90 / kg');
});

it('carries the old price to the cart catalog', function () {
    $entry = orderConfigOf($this->get(route('feed.show', $this->store)))['dishes'][(string) $this->dish->id];

    expect($entry['price'])->toBe('30.00')->and($entry['old_price'])->toBe('40.00');
});

it('never sells a hidden promotional product', function () {
    $this->dish->update(['status_id' => DishStatus::idFor('oculto')]);

    ($this->order)()->assertUnprocessable();
});
