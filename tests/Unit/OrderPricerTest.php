<?php

use App\Exceptions\OrderRejectedException;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Services\Orders\OrderPricer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * RF-05, RF-06b, RF-06e, RNF-02 — the server prices the cart, never the browser.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create();
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->pricer = new OrderPricer;
});

function pricedDish(array $attributes = []): Dish
{
    return Dish::factory()->create(array_merge([
        'restaurant_id' => test()->restaurant->id,
        'category_id' => test()->category->id,
        'status_id' => DishStatus::idFor('ativo'),
        'price' => '28.90',
    ], $attributes));
}

function rejection(Closure $callback): OrderRejectedException
{
    try {
        $callback();
    } catch (OrderRejectedException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected the order to be rejected.');
}

it('prices from the database and ignores any price sent by the browser', function () {
    $dish = pricedDish();

    $priced = $this->pricer->price($this->restaurant, [
        ['dish_id' => $dish->id, 'quantity' => 2, 'price' => '0.01', 'total' => '0.02'],
    ]);

    expect($priced->subtotal)->toBe('57.80')
        ->and($priced->total)->toBe('57.80')
        ->and($priced->lines[0])->toMatchArray(['unit_price' => '28.90', 'total' => '57.80', 'quantity' => '2.000']);
});

it('adds complements, repeated options and charges the variation price', function () {
    $dish = pricedDish(['price' => '10.00']);
    $dish->variants()->create(['name' => 'Grande', 'price' => '40.00']);
    $variantId = $dish->variants()->first()->id;

    $extras = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id, 'mode' => 'many', 'max_choices' => 5, 'allow_repeat' => true]);
    $bacon = Option::factory()->create(['option_group_id' => $extras->id, 'name' => 'Bacon', 'price' => '4.00']);
    $egg = Option::factory()->create(['option_group_id' => $extras->id, 'name' => 'Ovo', 'price' => '2.00']);
    $dish->optionGroups()->attach($extras->id, ['display_order' => 1]);

    $priced = $this->pricer->price($this->restaurant, [[
        'dish_id' => $dish->id, 'quantity' => 1, 'variant_id' => $variantId,
        'options' => [['id' => $bacon->id, 'quantity' => 2], ['id' => $egg->id, 'quantity' => 1]],
    ]]);

    expect($priced->subtotal)->toBe('50.00')
        ->and($priced->lines[0]['selections']['variant']['name'])->toBe('Grande')
        ->and(collect($priced->lines[0]['selections']['complements'])->pluck('name')->all())->toBe(['Bacon', 'Ovo']);
});

it('requires a variation when the product has them', function () {
    $dish = pricedDish();
    $dish->variants()->create(['name' => 'P', 'price' => '10.00']);

    $exception = rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]]));

    expect($exception->hasType('missing_choice'))->toBeTrue();
});

it('rejects a missing required choice, too many choices and a repeat that is not allowed', function () {
    $dish = pricedDish();
    $size = OptionGroup::factory()->required()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Tamanho']);
    $small = Option::factory()->create(['option_group_id' => $size->id, 'name' => 'Pequeno']);
    $medium = Option::factory()->create(['option_group_id' => $size->id, 'name' => 'Médio']);
    $dish->optionGroups()->attach($size->id, ['display_order' => 1]);

    $missing = rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]]));
    expect($missing->getMessage())->toContain('Tamanho');

    $tooMany = rejection(fn () => $this->pricer->price($this->restaurant, [[
        'dish_id' => $dish->id, 'quantity' => 1, 'options' => [['id' => $small->id, 'quantity' => 1], ['id' => $medium->id, 'quantity' => 1]],
    ]]));
    expect($tooMany->hasType('invalid_choice'))->toBeTrue();

    $repeat = rejection(fn () => $this->pricer->price($this->restaurant, [[
        'dish_id' => $dish->id, 'quantity' => 1, 'options' => [['id' => $small->id, 'quantity' => 2]],
    ]]));
    expect($repeat->hasType('invalid_choice'))->toBeTrue();
});

it('rejects an option that is unavailable or does not belong to the product', function () {
    $dish = pricedDish();
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $off = Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Bacon', 'is_available' => false]);
    $dish->optionGroups()->attach($group->id, ['display_order' => 1]);
    $foreign = Option::factory()->create();

    expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1, 'options' => [['id' => $off->id, 'quantity' => 1]]]]))->hasType('item_unavailable'))->toBeTrue();
    expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1, 'options' => [['id' => $foreign->id, 'quantity' => 1]]]]))->hasType('invalid_choice'))->toBeTrue();
});

it('rejects hidden, sold out and foreign products listing every unavailable item', function () {
    $ok = pricedDish(['name' => 'Disponível']);
    $hidden = pricedDish(['name' => 'Oculto', 'status_id' => DishStatus::idFor('oculto')]);
    $soldOut = pricedDish(['name' => 'Esgotado', 'status_id' => DishStatus::idFor('esgotado')]);
    $foreign = Dish::factory()->create(['name' => 'De outra loja']);

    $exception = rejection(fn () => $this->pricer->price($this->restaurant, [
        ['dish_id' => $ok->id, 'quantity' => 1],
        ['dish_id' => $hidden->id, 'quantity' => 1],
        ['dish_id' => $soldOut->id, 'quantity' => 1],
        ['dish_id' => $foreign->id, 'quantity' => 1],
    ]));

    expect(collect($exception->items())->pluck('dish_id')->sort()->values()->all())->toBe(collect([$hidden->id, $soldOut->id, $foreign->id])->sort()->values()->all())
        ->and($exception->items())->toHaveCount(3)
        ->and(collect($exception->items())->pluck('dish_id')->contains($ok->id))->toBeFalse()
        ->and($exception->hasType('item_unavailable'))->toBeTrue();
});

it('rejects a product whose category is outside its own time window', function () {
    $lunch = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'shows_from' => '11:00', 'shows_until' => '15:00']);
    $dish = pricedDish(['category_id' => $lunch->id, 'name' => 'Prato Executivo']);

    $this->travelTo('2026-11-02 20:00:00'); // 16:00 in Porto Velho

    expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]]))->hasType('item_unavailable'))->toBeTrue();

    $this->travelTo('2026-11-02 16:00:00'); // 12:00

    expect($this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]])->subtotal)->toBe('28.90');
});

it('validates quantities, the weighed step and the notes limit', function () {
    $dish = pricedDish();

    foreach ([0, -1, 1.5, 100, 'abc'] as $bad) {
        expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => $bad]]))->hasType('invalid_quantity'))->toBeTrue();
    }

    expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1, 'notes' => str_repeat('a', 141)]]))->hasType('notes_too_long'))->toBeTrue();

    expect($this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1, 'notes' => str_repeat('a', 140)]])->lines[0]['notes'])->toHaveLength(140);
});

it('rejects an empty cart', function () {
    expect(rejection(fn () => $this->pricer->price($this->restaurant, []))->hasType('empty_cart'))->toBeTrue();
});

it('charges the neighborhood fee, and "a combinar" outside the list when allowed', function () {
    $dish = pricedDish(['price' => '40.00']);
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'delivery_mode' => 'bairro', 'accepts_other_neighborhood' => true]);
    $centro = DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);

    $inside = $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'entrega', 'zone_id' => $centro->id]);
    expect($inside->deliveryFee)->toBe('5.00')->and($inside->total)->toBe('45.00')->and($inside->deliveryFeePending)->toBeFalse();

    $outside = $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'entrega', 'neighborhood' => 'Bairro Novo']);
    expect($outside->deliveryFee)->toBeNull()->and($outside->deliveryFeePending)->toBeTrue()->and($outside->total)->toBe('40.00');
});

it('rejects a neighborhood outside the list when the store does not accept others', function () {
    $dish = pricedDish();
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'delivery_mode' => 'bairro', 'accepts_other_neighborhood' => false]);
    DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro']);

    expect(rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'entrega', 'neighborhood' => 'Longe']))->hasType('zone_not_served'))->toBeTrue();
});

it('makes delivery free above the threshold and says how much is missing below it', function () {
    $dish = pricedDish(['price' => '50.00']);
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'delivery_mode' => 'bairro', 'free_delivery_over' => '80.00']);
    $centro = DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);

    $below = $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'entrega', 'zone_id' => $centro->id]);
    expect($below->deliveryFee)->toBe('5.00')->and($below->freeDelivery)->toBeFalse()->and($below->missingForFreeDelivery)->toBe('30.00');

    $above = $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 2]], ['fulfillment' => 'entrega', 'zone_id' => $centro->id]);
    expect($above->deliveryFee)->toBe('0.00')->and($above->freeDelivery)->toBeTrue()->and($above->total)->toBe('100.00');
});

it('applies the delivery minimum to the subtotal and lets pickup through', function () {
    $dish = pricedDish(['price' => '20.00']);
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->restaurant->id, 'min_order_delivery' => '25.00']);

    $exception = rejection(fn () => $this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'entrega']));
    expect($exception->hasType('min_order_delivery'))->toBeTrue()->and($exception->getMessage())->toContain('R$ 25,00');

    expect($this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], ['fulfillment' => 'retirada'])->total)->toBe('20.00');
});

it('never uses floats for money and rounds half up', function () {
    $dish = pricedDish(['price' => '0.10']);

    expect($this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 3]])->total)->toBe('0.30');

    $dish->update(['price' => '33.33']);

    expect($this->pricer->price($this->restaurant, [['dish_id' => $dish->id, 'quantity' => 3]])->total)->toBe('99.99');
});
