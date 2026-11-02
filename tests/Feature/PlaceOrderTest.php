<?php

use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RestaurantOrderSetting;
use App\Models\RestaurantStatus;
use App\Models\User;

/**
 * RF-02a, RF-05, RF-06, RF-06b, RF-06c, RF-06e, CT-01, RNF-04, RNF-05 — the public checkout.
 */
beforeEach(function () {
    seedReferenceData();
    $this->restaurant = User::factory()->create()->restaurant;
    $this->settings = RestaurantOrderSetting::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'whatsapp_number' => '5569996041447',
        'pix_key' => 'teste@docin.com.br',
        'pix_holder' => 'Docin Teste',
        'pix_city' => 'Porto Velho',
    ]);
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->dish = Dish::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'category_id' => $this->category->id,
        'status_id' => DishStatus::idFor('ativo'),
        'name' => 'X-Bacon',
        'price' => '28.90',
    ]);
});

function checkout(array $overrides = []): array
{
    return array_replace_recursive([
        'items' => [['dish_id' => test()->dish->id, 'quantity' => 2, 'notes' => 'Sem cebola']],
        'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana Teste', 'phone' => '(69) 99999-0000'],
        'payment_method' => 'pix',
    ], $overrides);
}

it('creates the order, the snapshot, the customer and the first history row, and answers 201', function () {
    $response = $this->postJson(route('feed.orders.store', $this->restaurant), checkout())
        ->assertCreated()
        ->assertJsonStructure(['code', 'tracking_token', 'tracking_url', 'status', 'total', 'message', 'whatsapp_url', 'pix_payload']);

    $order = Order::query()->where('code', $response->json('code'))->firstOrFail();

    expect($order->code)->toMatch('/^[A-Z]-[A-Z0-9]{5}$/')
        ->and($order->statusSlug())->toBe('novo')
        ->and($order->subtotal)->toBe('57.80')
        ->and($order->total)->toBe('57.80')
        ->and($order->customer_phone)->toBe('5569999990000')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items[0])->name->toBe('X-Bacon')->unit_price->toBe('28.90')->notes->toBe('Sem cebola')
        ->and($order->history)->toHaveCount(1)
        ->and($order->whatsapp_opened_at)->toBeNull()
        ->and($order->customer_confirmed_at)->toBeNull();

    expect($response->json('message'))->toContain($order->code)->toContain(route('order.track', $order->tracking_token))
        ->and($response->json('whatsapp_url'))->toStartWith('https://api.whatsapp.com/send?phone=5569996041447&text=')
        ->and($response->json('tracking_url'))->toBe(route('order.track', $order->tracking_token))
        ->and($response->json('total'))->toBe('57.80');

    expect(Customer::query()->where('restaurant_id', $this->restaurant->id)->count())->toBe(1);
});

it('recalculates a tampered price on the server', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout([
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 2, 'price' => '0.01', 'total' => '0.02', 'unit_price' => '0.01']],
        'total' => '0.02',
    ]))->assertCreated()->assertJsonPath('total', '57.80');

    expect(Order::query()->latest('id')->first()->total)->toBe('57.80');
});

it('returns the Pix copy and paste with the server total and registers no payment', function () {
    $response = $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();

    expect($response->json('pix_payload'))->toContain('5405')->toContain('57.80')->toContain('teste@docin.com.br')
        ->and(OrderPayment::count())->toBe(0);
});

it('does not send a Pix payload for other payment methods', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['payment_method' => 'credito']))
        ->assertCreated()->assertJsonPath('pix_payload', null);
});

it('rejects change smaller than the total and accepts enough change', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['payment_method' => 'dinheiro', 'change_for' => '20,00']))
        ->assertUnprocessable()->assertJsonValidationErrors('change_for');

    expect(Order::count())->toBe(0);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['payment_method' => 'dinheiro', 'change_for' => '100,00']))->assertCreated();

    expect(Order::query()->first()->change_for)->toBe('100.00');
});

it('refuses with 422 when the store is switched off manually, even if it accepts off hours orders', function () {
    $this->settings->update(['accepts_orders' => false, 'accepts_when_closed' => true]);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())
        ->assertUnprocessable()
        ->assertJsonPath('issues.0.type', 'store_closed');

    expect(Order::count())->toBe(0);
});

it('refuses with 422 outside the hours and accepts off hours orders when allowed', function () {
    $this->settings->update(['hours_enabled' => true]);
    BusinessHour::factory()->create(['restaurant_id' => $this->restaurant->id, 'weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']);

    $this->travelTo('2026-11-02 16:00:00'); // Monday 12:00 in Porto Velho

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())
        ->assertUnprocessable()
        ->assertJsonPath('issues.0.type', 'store_closed');

    expect(Order::count())->toBe(0);

    $this->settings->update(['accepts_when_closed' => true]);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())
        ->assertCreated()->assertJsonPath('placed_off_hours', true);

    expect(Order::query()->first()->placed_off_hours)->toBeTrue();
});

it('refuses a hidden product listing it, creates nothing, and accepts the cart once it is removed', function () {
    $hidden = Dish::factory()->create([
        'restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id,
        'status_id' => DishStatus::idFor('oculto'), 'name' => 'Prato Oculto', 'price' => '10.00',
    ]);

    $cart = checkout(['items' => [
        ['dish_id' => $this->dish->id, 'quantity' => 1],
        ['dish_id' => $hidden->id, 'quantity' => 1],
    ]]);

    $this->postJson(route('feed.orders.store', $this->restaurant), $cart)
        ->assertUnprocessable()
        ->assertJsonPath('items.0.dish_id', $hidden->id)
        ->assertJsonPath('items.0.name', 'Prato Oculto');

    expect(Order::count())->toBe(0);

    $cart['items'] = [['dish_id' => $this->dish->id, 'quantity' => 1]];

    $this->postJson(route('feed.orders.store', $this->restaurant), $cart)->assertCreated();
});

it('refuses a product of another store', function () {
    $foreign = Dish::factory()->create();

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['items' => [['dish_id' => $foreign->id, 'quantity' => 1]]]))
        ->assertUnprocessable();

    expect(Order::count())->toBe(0);
});

it('requires the address on delivery and applies the delivery minimum', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['fulfillment' => 'entrega']))
        ->assertUnprocessable()->assertJsonValidationErrors(['address']);

    $this->settings->update(['min_order_delivery' => '100.00']);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['fulfillment' => 'entrega', 'address' => ['street' => 'Rua A', 'number' => '1', 'neighborhood' => 'Centro']]))
        ->assertUnprocessable()->assertJsonPath('issues.0.type', 'min_order_delivery');

    // Pickup is still allowed below the delivery minimum.
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();
});

it('creates a delivery order with the neighborhood fee, and "a combinar" for a neighborhood outside the list', function () {
    $this->settings->update(['delivery_mode' => 'bairro', 'accepts_other_neighborhood' => true]);
    $centro = DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);
    $address = ['street' => 'Rua das Flores', 'number' => '120'];

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['fulfillment' => 'entrega', 'address' => $address + ['zone_id' => $centro->id]]))
        ->assertCreated()->assertJsonPath('delivery_fee', '5.00')->assertJsonPath('total', '62.80')->assertJsonPath('delivery_fee_pending', false);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['fulfillment' => 'entrega', 'address' => $address + ['neighborhood' => 'Bairro Novo']]))
        ->assertCreated()->assertJsonPath('delivery_fee', null)->assertJsonPath('delivery_fee_pending', true)->assertJsonPath('total', '57.80');

    expect(Order::query()->where('neighborhood', 'Bairro Novo')->exists())->toBeTrue();
});

it('answers 403 for a suspended store and for a plan without orders', function () {
    $this->restaurant->update(['status_id' => RestaurantStatus::idFor('suspenso')]);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertForbidden();

    $this->restaurant->update(['status_id' => RestaurantStatus::idFor('ativo')]);
    $this->restaurant->plan->update(['features' => ['orders' => false]]);
    endTrial($this->restaurant);

    $this->postJson(route('feed.orders.store', $this->restaurant->fresh()), checkout())->assertForbidden();

    expect(Order::count())->toBe(0);
});

it('refuses with 422 when the store has not configured its WhatsApp', function () {
    $this->settings->update(['whatsapp_number' => null]);

    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())
        ->assertUnprocessable()->assertJsonPath('issues.0.type', 'store_not_ready');
});

it('validates the payload shape', function (array $override, string $field) {
    $this->postJson(route('feed.orders.store', $this->restaurant), array_replace_recursive(checkout(), $override))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'sem nome' => [['customer' => ['name' => '']], 'customer.name'],
    'whatsapp inválido' => [['customer' => ['phone' => '123']], 'customer.phone'],
    'pagamento inválido' => [['payment_method' => 'bitcoin'], 'payment_method'],
    'recebimento inválido' => [['fulfillment' => 'drone'], 'fulfillment'],
]);

it('counts repeat customers by WhatsApp', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['customer' => ['phone' => '69999990000']]))->assertCreated();

    $customer = Customer::query()->where('restaurant_id', $this->restaurant->id)->sole();

    expect($customer->orders_count)->toBe(2)->and(Order::count())->toBe(2);
});

it('normalises the origin to the closed list', function () {
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['origin' => 'instagram']))->assertCreated();
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout(['origin' => 'tiktok']))->assertCreated();
    $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();

    expect(Order::query()->orderBy('id')->pluck('origin')->all())->toBe(['instagram', 'outro', 'direto']);
});

it('is exempt from CSRF like the other anonymous feed endpoints', function () {
    $this->withMiddleware()->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();
});

it('never shares an order code between two orders of the same store', function () {
    foreach (range(1, 8) as $i) {
        $this->postJson(route('feed.orders.store', $this->restaurant), checkout())->assertCreated();
    }

    expect(Order::query()->pluck('code')->unique())->toHaveCount(8);
});
