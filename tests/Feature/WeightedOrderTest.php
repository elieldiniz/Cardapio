<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\AdjustOrderValues;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-15 — products sold by weight and the value adjustment after weighing.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->torta = Dish::factory()->create([
        'restaurant_id' => $this->store->id, 'category_id' => Category::factory()->create(['restaurant_id' => $this->store->id])->id,
        'status_id' => DishStatus::idFor('ativo'), 'name' => 'Torta por kg', 'price' => '60.00', 'sale_type' => 'kg',
    ]);
    $this->order = fn (array $attributes = []) => Order::factory()->delivery()->create(array_merge([
        'restaurant_id' => $this->store->id, 'subtotal' => '30.00', 'delivery_fee' => '5.00', 'total' => '35.00',
    ], $attributes));
});

it('orders weighed products in steps of 0.5 kg priced per kg', function () {
    $post = fn (float $quantity) => $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->torta->id, 'quantity' => $quantity]], 'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ]);

    $post(0.5)->assertCreated()->assertJsonPath('total', '30.00');
    $post(1.5)->assertCreated()->assertJsonPath('total', '90.00');
    $post(0.3)->assertUnprocessable();
    $post(1)->assertCreated()->assertJsonPath('total', '60.00');

    expect(Order::query()->orderBy('id')->first()->items->sole())->sale_type->toBe('kg')->quantity->toBe('0.500');
});

it('shows the weight in the WhatsApp message and on the manager card', function () {
    $response = $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->torta->id, 'quantity' => 1.5]], 'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ])->assertCreated();

    expect($response->json('message'))->toContain('Torta por kg — 1,5 kg (R$ 90,00)');

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertSee('1,5 kg Torta por kg');
});

it('adjusts the products and the fee separately, recalculates the total and keeps the original', function () {
    $order = ($this->order)();

    $adjusted = app(AdjustOrderValues::class)->handle($order, '27,40', '6,00', $this->dono);

    expect($adjusted)->subtotal->toBe('27.40')->delivery_fee->toBe('6.00')->total->toBe('33.40')->original_total->toBe('35.00')->and($adjusted->adjusted_at)->not->toBeNull();
});

it('keeps the very first total as the original after several adjustments', function () {
    $order = ($this->order)();

    app(AdjustOrderValues::class)->handle($order, '27,40', null);
    $second = app(AdjustOrderValues::class)->handle($order->fresh(), '25,00', null);

    expect($second->original_total)->toBe('35.00')->and($second->total)->toBe('30.00');
});

it('keeps the fee when none is sent and respects an existing discount', function () {
    $order = ($this->order)(['discount' => '5.00', 'total' => '30.00']);

    $adjusted = app(AdjustOrderValues::class)->handle($order, '40,00', null);

    expect($adjusted)->delivery_fee->toBe('5.00')->total->toBe('40.00');
});

it('sets the fee of a "a combinar" delivery when adjusting', function () {
    $order = ($this->order)(['delivery_fee' => null, 'delivery_fee_pending' => true, 'total' => '30.00']);

    $adjusted = app(AdjustOrderValues::class)->handle($order, '30,00', '7,00');

    expect($adjusted)->delivery_fee->toBe('7.00')->delivery_fee_pending->toBeFalse()->total->toBe('37.00');
});

it('refuses invalid values, an ended order and a paid order', function () {
    $order = ($this->order)();

    foreach ([['abc', null], ['', null], ['10,00', 'xx']] as [$products, $fee]) {
        expect(fn () => app(AdjustOrderValues::class)->handle($order, $products, $fee))->toThrow(ValidationException::class);
    }

    expect($order->fresh())->subtotal->toBe('30.00')->adjusted_at->toBeNull();

    $ended = Order::factory()->status('finalizado')->create(['restaurant_id' => $this->store->id]);
    expect(fn () => app(AdjustOrderValues::class)->handle($ended, '10,00'))->toThrow(ValidationException::class);

    app(OpenCashSession::class)->handle($this->store, '0');
    $paid = ($this->order)(['total' => '35.00']);
    $paid->update(['status_id' => OrderStatus::idFor('em_rota')]);
    app(FinalizeOrder::class)->handle($paid, [['method' => 'pix', 'amount' => '35,00']], $this->dono);
    expect(fn () => app(AdjustOrderValues::class)->handle($paid->fresh(), '10,00'))->toThrow(ValidationException::class);
});

it('adjusts from the manager screen, shows the original value and the customer sees the new total', function () {
    $order = ($this->order)(['tracking_token' => 'tok-adjust', 'status_id' => OrderStatus::idFor('em_preparo')]);

    Livewire::actingAs($this->dono)->test('pages::panel.orders')
        ->call('openAdjust', $order->id)->assertSet('showAdjust', true)->assertSet('adjustProducts', '30,00')->assertSet('adjustFee', '5,00')
        ->set('adjustProducts', '27,40')->call('confirmAdjust')->assertHasNoErrors()->assertSet('showAdjust', false)
        ->assertSeeHtml('data-adjusted')->assertSee('era R$ 35,00');

    $this->getJson(route('order.track.status', 'tok-adjust'))->assertJsonPath('total', 'R$ 32,40');
});

it('shows the adjustment error and never touches another store order', function () {
    $order = ($this->order)();
    $foreign = Order::factory()->create();

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->call('openAdjust', $order->id)->set('adjustProducts', 'abc')->call('confirmAdjust')->assertHasErrors('products');
    Livewire::actingAs($this->dono)->test('pages::panel.orders')->call('openAdjust', $foreign->id)->assertNotFound();
});
