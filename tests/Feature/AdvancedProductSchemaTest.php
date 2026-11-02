<?php

use App\Models\Dish;
use App\Models\Order;
use App\Models\PizzaFlavor;
use App\Models\PizzaFlavorPrice;
use App\Models\PizzaSize;
use App\Models\PromoBanner;
use App\Models\Restaurant;
use Illuminate\Database\QueryException;

/**
 * RF-14, RF-15, RF-16 — advanced product schema.
 */
it('keeps existing products as common unit products', function () {
    $dish = Dish::factory()->create();

    expect($dish->fresh())->sale_type->toBe('unit')->kind->toBe('common')->promo_price->toBeNull()->is_featured->toBeFalse()
        ->and($dish->isPizza())->toBeFalse()->and($dish->isWeighed())->toBeFalse();
});

it('links sizes, flavors and a price per flavor and size', function () {
    $pizza = Dish::factory()->create(['kind' => 'pizza']);
    $grande = PizzaSize::create(['dish_id' => $pizza->id, 'name' => 'Grande', 'slices' => 8, 'max_flavors' => 3, 'display_order' => 1]);
    $broto = PizzaSize::create(['dish_id' => $pizza->id, 'name' => 'Broto', 'slices' => 4, 'max_flavors' => 1, 'display_order' => 0]);
    $calabresa = PizzaFlavor::create(['dish_id' => $pizza->id, 'name' => 'Calabresa', 'section' => 'Tradicionais']);
    PizzaFlavorPrice::create(['pizza_flavor_id' => $calabresa->id, 'pizza_size_id' => $grande->id, 'price' => '55.00']);

    expect($pizza->pizzaSizes->pluck('name')->all())->toBe(['Broto', 'Grande'])
        ->and($pizza->pizzaFlavors()->count())->toBe(1)
        ->and($calabresa->prices()->sole()->price)->toBe('55.00')
        ->and($broto->prices()->count())->toBe(0);
});

it('has one price per flavor and size', function () {
    $pizza = Dish::factory()->create(['kind' => 'pizza']);
    $size = PizzaSize::create(['dish_id' => $pizza->id, 'name' => 'Grande']);
    $flavor = PizzaFlavor::create(['dish_id' => $pizza->id, 'name' => 'Calabresa']);
    PizzaFlavorPrice::create(['pizza_flavor_id' => $flavor->id, 'pizza_size_id' => $size->id, 'price' => '55.00']);

    expect(fn () => PizzaFlavorPrice::create(['pizza_flavor_id' => $flavor->id, 'pizza_size_id' => $size->id, 'price' => '60.00']))->toThrow(QueryException::class);
});

it('removes sizes, flavors and prices together with the product', function () {
    $pizza = Dish::factory()->create(['kind' => 'pizza']);
    $size = PizzaSize::create(['dish_id' => $pizza->id, 'name' => 'Grande']);
    $flavor = PizzaFlavor::create(['dish_id' => $pizza->id, 'name' => 'Calabresa']);
    PizzaFlavorPrice::create(['pizza_flavor_id' => $flavor->id, 'pizza_size_id' => $size->id, 'price' => '55.00']);

    $pizza->delete();

    expect(PizzaSize::count())->toBe(0)->and(PizzaFlavor::count())->toBe(0)->and(PizzaFlavorPrice::count())->toBe(0);
});

it('applies a promotional price only while it is valid', function () {
    $dish = Dish::factory()->create(['price' => '40.00', 'promo_price' => '30.00']);

    expect($dish->activePromoPrice())->toBe('30.00')->and($dish->currentPrice())->toBe('30.00');

    $dish->update(['promo_ends_at' => now()->addDay()]);
    expect($dish->fresh()->activePromoPrice())->toBe('30.00');

    $dish->update(['promo_ends_at' => now()->subMinute()]);
    expect($dish->fresh()->activePromoPrice())->toBeNull()->and($dish->fresh()->currentPrice())->toBe('40.00');

    $dish->update(['promo_ends_at' => null, 'promo_price' => '45.00']);
    expect($dish->fresh()->activePromoPrice())->toBeNull();
});

it('has a single promotional banner per store that expires after its last day', function () {
    $store = Restaurant::factory()->create();
    $banner = PromoBanner::create(['restaurant_id' => $store->id, 'title' => 'Sábado é dia de feijoada!', 'ends_on' => '2026-11-07'])->fresh();

    expect(fn () => PromoBanner::create(['restaurant_id' => $store->id, 'title' => 'Outro']))->toThrow(QueryException::class);

    expect($banner->isLive('2026-11-07'))->toBeTrue()->and($banner->isLive('2026-11-08'))->toBeFalse();

    $banner->update(['ends_on' => null]);
    expect($banner->fresh()->isLive('2030-01-01'))->toBeTrue();

    $banner->update(['is_active' => false]);
    expect($banner->fresh()->isLive())->toBeFalse();
});

it('records an order adjustment', function () {
    $order = Order::factory()->create(['total' => '40.00']);
    $order->update(['original_total' => '40.00', 'total' => '37.50', 'adjusted_at' => now()]);

    expect($order->fresh())->original_total->toBe('40.00')->total->toBe('37.50')->and($order->fresh()->adjusted_at)->not->toBeNull();
});
