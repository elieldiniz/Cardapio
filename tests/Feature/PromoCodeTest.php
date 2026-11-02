<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\ReleasePromoCodeUse;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PromoCode;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * RF-17, RF-08c, RNF-04 — the restaurant's discount codes.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->code = fn (array $attributes = []) => PromoCode::create(array_merge(['restaurant_id' => $this->store->id, 'code' => 'BEMVINDO10', 'type' => 'percentual', 'value' => '10.00'], $attributes));
    $this->order = fn (?string $code = 'BEMVINDO10', int $quantity = 1) => $this->postJson(route('feed.orders.store', $this->store), array_filter([
        'items' => [['dish_id' => $this->dish->id, 'quantity' => $quantity]], 'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix', 'promo_code' => $code,
    ], fn ($value) => $value !== null));
});

it('applies a percentage code and records the discount on the order', function () {
    ($this->code)();

    $response = ($this->order)('bemvindo10')->assertCreated();

    expect($response->json('total'))->toBe('45.00');

    $order = Order::sole();

    expect($order)->subtotal->toBe('50.00')->discount->toBe('5.00')->total->toBe('45.00')->and($order->promo_code_id)->not->toBeNull()
        ->and(PromoCode::sole()->uses)->toBe(1)
        ->and($response->json('message'))->toContain('Desconto: -R$ 5,00');
});

it('applies a fixed amount code and never discounts more than the purchase', function () {
    ($this->code)(['type' => 'valor', 'value' => '5.00', 'code' => 'SEXTOU5']);
    ($this->order)('SEXTOU5')->assertCreated()->assertJsonPath('total', '45.00');

    ($this->code)(['type' => 'valor', 'value' => '500.00', 'code' => 'ENORME']);
    ($this->order)('ENORME')->assertCreated()->assertJsonPath('total', '0.00');
});

it('rejects an unknown, expired, not started, inactive, exhausted or below-minimum code and creates nothing', function (array $attributes, string $message) {
    ($this->code)($attributes);

    ($this->order)()->assertUnprocessable()->assertJsonPath('issues.0.type', 'promo_code')->assertJsonPath('issues.0.message', $message);

    expect(Order::count())->toBe(0)->and(PromoCode::sole()->uses)->toBe($attributes['uses'] ?? 0);
})->with([
    'expirado' => [['ends_on' => '2020-01-01'], 'Este cupom expirou.'],
    'ainda não começou' => [['starts_on' => '2099-01-01'], 'Este cupom ainda não começou.'],
    'inativo' => [['is_active' => false], 'Este cupom não está mais ativo.'],
    'esgotado' => [['max_uses' => 2, 'uses' => 2], 'Este cupom atingiu o limite de usos.'],
    'abaixo do mínimo' => [['min_order' => '80.00'], 'Este cupom vale para pedidos a partir de R$ 80,00.'],
]);

it('says "Cupom inválido" for a code that does not exist', function () {
    ($this->order)('NAOEXISTE')->assertUnprocessable()->assertJsonPath('issues.0.message', 'Cupom inválido.');
});

it('does not use a code of another store', function () {
    PromoCode::create(['restaurant_id' => User::factory()->create()->restaurant_id, 'code' => 'BEMVINDO10', 'type' => 'percentual', 'value' => '10.00']);

    ($this->order)()->assertUnprocessable();
});

it('keeps the delivery minimum on the subtotal even when the code lowers the total below it', function () {
    RestaurantOrderSetting::query()->where('restaurant_id', $this->store->id)->update(['min_order_delivery' => '48.00', 'delivery_mode' => 'a_combinar']);
    ($this->code)(['type' => 'valor', 'value' => '10.00']);

    $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'entrega',
        'address' => ['street' => 'Rua A', 'number' => '1', 'neighborhood' => 'Centro'],
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix', 'promo_code' => 'BEMVINDO10',
    ])->assertCreated()->assertJsonPath('total', '40.00');
});

it('lets the last use go to one order only', function () {
    ($this->code)(['max_uses' => 1]);

    ($this->order)()->assertCreated();
    ($this->order)()->assertUnprocessable();

    expect(PromoCode::sole()->uses)->toBe(1)->and(Order::count())->toBe(1);
});

it('gives the use back once when a finalized order is cancelled, never below zero', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    ($this->code)();
    ($this->order)()->assertCreated();
    $order = Order::sole();
    $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
    app(FinalizeOrder::class)->handle($order, [['method' => 'pix', 'amount' => '45,00']], $this->dono);

    app(CancelFinalizedOrder::class)->handle($order->fresh(), 'Cliente desistiu', $this->dono);
    app(CancelFinalizedOrder::class)->handle($order->fresh(), 'Cliente desistiu', $this->dono);
    app(ReleasePromoCodeUse::class)->handle($order->fresh());

    expect(PromoCode::sole()->uses)->toBe(0)->and($order->fresh()->promo_released_at)->not->toBeNull();
});

it('gives the use back when a new order is refused', function () {
    ($this->code)();
    ($this->order)()->assertCreated();

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->call('refuse', Order::sole()->id);

    expect(PromoCode::sole()->uses)->toBe(0);
});

it('leaves the platform billing coupons untouched', function () {
    expect(Coupon::count())->toBe(0);

    ($this->code)();
    ($this->order)()->assertCreated();

    expect(Coupon::count())->toBe(0)->and(Schema::hasTable('promo_codes'))->toBeTrue();
});

it('computes the percentage discount in cents, rounding half up', function () {
    $code = ($this->code)(['value' => '33.33']);

    expect($code->discountCents(1000))->toBe(333)->and($code->discountCents(1))->toBe(0)->and(($this->code)(['code' => 'X', 'type' => 'valor', 'value' => '2.50'])->discountCents(100))->toBe(100);
});

it('previews the discount for the checkout without spending a use', function () {
    ($this->code)(['max_uses' => 1, 'description' => '10% na primeira compra']);

    $this->postJson(route('feed.orders.coupon', $this->store), ['code' => ' bemvindo10 ', 'subtotal' => 50])
        ->assertOk()->assertJsonPath('discount', '5.00')->assertJsonPath('code', 'BEMVINDO10');

    $this->postJson(route('feed.orders.coupon', $this->store), ['code' => 'NOPE', 'subtotal' => 50])
        ->assertUnprocessable()->assertJsonPath('message', 'Cupom inválido.');

    expect(PromoCode::sole()->uses)->toBe(0);
});
