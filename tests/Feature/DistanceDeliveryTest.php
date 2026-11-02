<?php

use App\Models\Category;
use App\Models\DeliveryDistanceTier;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-24a, RF-05 — delivery fee by distance, calculated on the server.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447', 'delivery_mode' => 'distancia', 'lat' => -8.76, 'lng' => -63.9]);
    DeliveryDistanceTier::create(['restaurant_id' => $this->store->id, 'from_km' => 0, 'to_km' => 3, 'fee' => '5.00']);
    DeliveryDistanceTier::create(['restaurant_id' => $this->store->id, 'from_km' => 3, 'to_km' => 6, 'fee' => '8.00']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    // ~0.009° of latitude per km.
    $this->order = fn (?float $km) => $this->postJson(route('feed.orders.store', $this->store), array_filter([
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'entrega',
        'address' => array_merge(['street' => 'Rua A', 'number' => '1', 'neighborhood' => 'Centro'], $km === null ? [] : ['lat' => -8.76 - $km * 0.009, 'lng' => -63.9]),
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ]));
});

it('prices the delivery by the range of the distance', function (float $km, string $fee) {
    ($this->order)($km)->assertCreated()->assertJsonPath('delivery_fee', $fee)->assertJsonPath('delivery_fee_pending', false);

    expect((float) Order::sole()->distance_km)->toEqualWithDelta($km, 0.1);
})->with([[2.0, '5.00'], [4.0, '8.00']]);

it('leaves the fee "a combinar" beyond the ranges or without a location', function (?float $km) {
    ($this->order)($km)->assertCreated()->assertJsonPath('delivery_fee_pending', true)->assertJsonPath('delivery_fee', null);
})->with([[7.0], [null]]);

it('ignores a location sent by the client when the mode is by neighborhood', function () {
    RestaurantOrderSetting::where('restaurant_id', $this->store->id)->update(['delivery_mode' => 'a_combinar']);

    ($this->order)(2.0)->assertCreated()->assertJsonPath('delivery_fee_pending', true);
});

it('saves the mode, the store location and the ranges from the settings screen', function () {
    DeliveryDistanceTier::query()->delete();

    $page = Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'distancia')->set('store_lat', '-8.76194')->set('store_lng', '-63.90389')
        ->call('addTier')->set('tiers.0.to_km', '3')->set('tiers.0.fee', '5,00')
        ->call('addTier')->set('tiers.1.to_km', '6')->set('tiers.1.fee', '8,00')
        ->call('saveDelivery')->assertHasNoErrors();

    expect($this->store->distanceTiers()->get()->map(fn ($t) => [(float) $t->from_km, (float) $t->to_km, $t->fee])->all())->toBe([[0.0, 3.0, '5.00'], [3.0, 6.0, '8.00']]);

    $page->set('tiers.1.from_km', '2')->call('saveDelivery')->assertHasErrors('tiers');
});

it('shows the ranges and the location in the checkout catalog only in distance mode', function () {
    $config = orderConfigOf($this->get(route('feed.show', $this->store)));

    expect($config['delivery']['mode'])->toBe('distancia')->and($config['delivery']['tiers'])->toHaveCount(2)->and($config['delivery']['store_lat'])->not->toBeNull();
});
