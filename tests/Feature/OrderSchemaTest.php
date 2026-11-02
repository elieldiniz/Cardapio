<?php

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use Illuminate\Database\QueryException;

/**
 * RF-06, RF-02a, RF-07a, RNF-01, RNF-02 — orders schema.
 */
beforeEach(function () {
    seedReferenceData();
});

it('never lets two orders of a store share a code, but other stores may reuse it', function () {
    $store = Restaurant::factory()->create();
    Order::factory()->create(['restaurant_id' => $store->id, 'code' => 'D-AAAAA']);

    expect(fn () => Order::factory()->create(['restaurant_id' => $store->id, 'code' => 'D-AAAAA']))
        ->toThrow(QueryException::class);

    expect(Order::factory()->create(['code' => 'D-AAAAA'])->exists)->toBeTrue();
});

it('has a unique tracking token', function () {
    Order::factory()->create(['tracking_token' => 'token-1']);

    expect(fn () => Order::factory()->create(['tracking_token' => 'token-1']))->toThrow(QueryException::class);
});

it('keeps the item snapshot when the product is edited or deleted', function () {
    $dish = Dish::factory()->create(['name' => 'X-Bacon', 'price' => '28.90']);
    $order = Order::factory()->create(['restaurant_id' => $dish->restaurant_id]);
    $item = $order->items()->create([
        'dish_id' => $dish->id, 'name' => 'X-Bacon', 'quantity' => 2, 'unit_price' => '28.90', 'total' => '57.80',
        'selections' => ['complements' => [['name' => 'Bacon', 'quantity' => 2]]],
    ]);

    $dish->update(['name' => 'X-Bacon Premium', 'price' => '99.00']);

    expect($item->fresh()->name)->toBe('X-Bacon')->and($item->fresh()->unit_price)->toBe('28.90');

    $dish->delete();

    expect($item->fresh())->name->toBe('X-Bacon')->dish_id->toBeNull()
        ->and($item->fresh()->selections['complements'][0]['name'])->toBe('Bacon');
});

it('stores money with two decimals and "a combinar" as a null fee', function () {
    $order = Order::factory()->create(['subtotal' => '129.9', 'delivery_fee' => null, 'delivery_fee_pending' => true, 'total' => '129.9']);

    expect($order->fresh())->subtotal->toBe('129.90')->total->toBe('129.90')->delivery_fee->toBeNull()
        ->and($order->fresh()->delivery_fee_pending)->toBeTrue();
});

it('labels quantities for units and weighed products', function () {
    $unit = new OrderItem(['sale_type' => 'unit', 'quantity' => 2]);
    $kg = new OrderItem(['sale_type' => 'kg', 'quantity' => 0.5]);

    expect($unit->quantityLabel())->toBe('2')->and($kg->quantityLabel())->toBe('0,5 kg');
});

it('records the status history and the payments of an order', function () {
    $order = Order::factory()->create();
    $order->history()->create(['status_id' => $order->status_id, 'changed_at' => now()]);
    $order->payments()->create(['payment_method_id' => PaymentMethod::idFor('dinheiro'), 'amount' => '40.00', 'reference' => 'order_finalize:1']);

    expect($order->history()->count())->toBe(1)->and($order->payments()->count())->toBe(1);

    expect(fn () => $order->payments()->create(['payment_method_id' => PaymentMethod::idFor('dinheiro'), 'amount' => '40.00', 'reference' => 'order_finalize:1']))
        ->toThrow(QueryException::class);
});

it('builds the one line address', function () {
    $order = Order::factory()->delivery()->make();

    expect($order->addressLine())->toBe('Rua das Flores, 120 · Centro')
        ->and(Order::factory()->make()->addressLine())->toBeNull();
});
