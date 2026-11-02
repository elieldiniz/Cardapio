<?php

use App\Actions\Menu\SavePizza;
use App\Exceptions\OrderRejectedException;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Services\Orders\OrderPricer;
use App\Services\Orders\SelectionDescriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * RF-14a, RF-05 — pizza pricing on the server.
 */
beforeEach(function () {
    seedReferenceData();
    $this->store = Restaurant::factory()->create();
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id]);
    $category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    $this->pizza = Dish::factory()->create(['restaurant_id' => $this->store->id, 'category_id' => $category->id, 'status_id' => DishStatus::idFor('ativo'), 'name' => 'Pizza']);
    app(SavePizza::class)->handle($this->pizza, [
        ['name' => 'Broto', 'slices' => 4, 'max_flavors' => 1],
        ['name' => 'Grande', 'slices' => 8, 'max_flavors' => 3],
    ], [
        ['name' => 'Mussarela', 'prices' => ['25,00', '40,00']],
        ['name' => 'Calabresa', 'prices' => ['30,00', '60,00']],
        ['name' => 'Portuguesa', 'prices' => ['', '65,00']],
        ['name' => 'Indisponível', 'is_available' => false, 'prices' => ['10,00', '10,00']],
    ]);
    $this->pizza = $this->pizza->fresh(['pizzaSizes', 'pizzaFlavors']);
    $this->size = fn (string $name) => $this->pizza->pizzaSizes->firstWhere('name', $name)->id;
    $this->flavor = fn (string $name) => $this->pizza->pizzaFlavors->firstWhere('name', $name)->id;
    $this->price = fn (string $size, array $flavors, array $extra = []) => (new OrderPricer)->price($this->store, [array_merge([
        'dish_id' => $this->pizza->id, 'quantity' => 1, 'size_id' => ($this->size)($size), 'flavor_ids' => array_map(fn ($name) => ($this->flavor)($name), $flavors),
    ], $extra)]);
});

it('charges the most expensive flavor by default (R$ 40 and R$ 60 → R$ 60)', function () {
    $priced = ($this->price)('Grande', ['Mussarela', 'Calabresa']);

    expect($priced->subtotal)->toBe('60.00')
        ->and($priced->lines[0]['selections']['pizza']['rule'])->toBe('maior')
        ->and($priced->lines[0]['selections']['pizza']['size']['name'])->toBe('Grande');
});

it('charges the average when the store chooses it (R$ 40 and R$ 60 → R$ 50)', function () {
    $this->store->orderSetting->update(['pizza_pricing_rule' => 'media']);

    expect(($this->price)('Grande', ['Mussarela', 'Calabresa'])->subtotal)->toBe('50.00');
});

it('rounds the average half up in cents and never uses floats', function () {
    $this->store->orderSetting->update(['pizza_pricing_rule' => 'media']);

    // (40,00 + 60,00 + 65,00) / 3 = 55,00 ; (60,00 + 65,00) / 2 = 62,50
    expect(($this->price)('Grande', ['Mussarela', 'Calabresa', 'Portuguesa'])->subtotal)->toBe('55.00')
        ->and(($this->price)('Grande', ['Calabresa', 'Portuguesa'])->subtotal)->toBe('62.50');
});

it('prices a single flavor in the chosen size', function () {
    expect(($this->price)('Broto', ['Calabresa'])->subtotal)->toBe('30.00')->and(($this->price)('Grande', ['Calabresa'])->subtotal)->toBe('60.00');
});

it('multiplies by the quantity and adds the complements to the pizza price', function () {
    expect(($this->price)('Grande', ['Calabresa'], ['quantity' => 2])->subtotal)->toBe('120.00');
});

it('refuses more flavors than the size accepts', function () {
    expect(fn () => ($this->price)('Broto', ['Mussarela', 'Calabresa']))->toThrow(OrderRejectedException::class);

    try {
        ($this->price)('Broto', ['Mussarela', 'Calabresa']);
    } catch (OrderRejectedException $exception) {
        expect($exception->getMessage())->toContain('no máximo 1 sabor');
    }
});

it('refuses a missing size or flavor, a flavor that does not exist in the size and an unavailable flavor', function (array $item) {
    expect(fn () => (new OrderPricer)->price($this->store, [array_merge(['dish_id' => $this->pizza->id, 'quantity' => 1], $item)]))->toThrow(OrderRejectedException::class);
})->with([
    'sem tamanho' => [['flavor_ids' => [1]]],
    'sem sabor' => [['size_id' => 1, 'flavor_ids' => []]],
    'tamanho de outra pizza' => [['size_id' => 999999, 'flavor_ids' => [1]]],
]);

it('refuses a flavor that has no price in that size or is switched off', function () {
    expect(fn () => ($this->price)('Broto', ['Portuguesa']))->toThrow(OrderRejectedException::class);
    expect(fn () => ($this->price)('Grande', ['Indisponível']))->toThrow(OrderRejectedException::class);
});

it('refuses a size that was switched off', function () {
    $this->pizza->pizzaSizes->firstWhere('name', 'Grande')->update(['is_available' => false]);

    expect(fn () => ($this->price)('Grande', ['Calabresa']))->toThrow(OrderRejectedException::class);
});

it('describes the choices with fractions for the dono and the customer', function () {
    $selections = ($this->price)('Grande', ['Mussarela', 'Calabresa'])->lines[0]['selections'];

    expect(SelectionDescriber::lines($selections))->toBe(['Tamanho: Grande (8 fatias)', 'Sabores: ½ Mussarela, ½ Calabresa']);

    $single = ($this->price)('Broto', ['Calabresa'])->lines[0]['selections'];

    expect(SelectionDescriber::lines($single))->toBe(['Tamanho: Broto (4 fatias)', 'Sabor: Calabresa']);
});

it('ignores any price sent by the browser for a pizza', function () {
    expect(($this->price)('Grande', ['Calabresa'], ['price' => '0.01', 'unit_price' => '0.01'])->subtotal)->toBe('60.00');
});
