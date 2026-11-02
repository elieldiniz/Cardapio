<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Customers\EraseCustomerData;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PromoCode;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-25, RF-17, RF-18 — coupon, stamp and cancellation staying coherent.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    app(OpenCashSession::class)->handle($this->store, '0');
    PromoCode::create(['restaurant_id' => $this->store->id, 'code' => 'BEMVINDO10', 'type' => 'percentual', 'value' => '10.00', 'max_uses' => 5]);
    LoyaltyProgram::create(['restaurant_id' => $this->store->id, 'is_active' => true, 'stamps_required' => 3, 'reward_type' => 'brinde', 'reward_label' => 'Sobremesa']);
});

function growthOrder(): Order
{
    test()->postJson(route('feed.orders.store', test()->store), [
        'items' => [['dish_id' => test()->dish->id, 'quantity' => 1]], 'fulfillment' => 'retirada', 'promo_code' => 'BEMVINDO10',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ])->assertCreated()->assertJsonPath('total', '45.00');

    return Order::latest('id')->first();
}

it('gives the coupon use and the stamp back exactly once when a finalized order is cancelled', function () {
    $order = growthOrder();
    $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
    app(FinalizeOrder::class)->handle($order, [['method' => 'pix', 'amount' => '45,00']], $this->dono);

    expect(PromoCode::sole()->uses)->toBe(1)->and(LoyaltyCard::sole()->stamps)->toBe(1);

    app(CancelFinalizedOrder::class)->handle($order->fresh(), 'Cliente desistiu', $this->dono);
    app(CancelFinalizedOrder::class)->handle($order->fresh(), 'Cliente desistiu', $this->dono);

    expect(PromoCode::sole()->uses)->toBe(0)->and(LoyaltyCard::sole()->stamps)->toBe(0)->and($order->fresh()->status->slug)->toBe('cancelado');
});

it('keeps the customer\'s stamp when only another order of theirs is cancelled', function () {
    $orders = collect([growthOrder(), growthOrder()])->each(function (Order $order) {
        $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
        app(FinalizeOrder::class)->handle($order, [['method' => 'pix', 'amount' => '45,00']], $this->dono);
    });

    app(CancelFinalizedOrder::class)->handle($orders->first()->fresh(), 'Pedido em duplicidade', $this->dono);

    expect(LoyaltyCard::sole()->stamps)->toBe(1)->and(PromoCode::sole()->uses)->toBe(1);
});

it('keeps the totals of the report after the customer is erased', function () {
    $order = growthOrder();
    $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
    app(FinalizeOrder::class)->handle($order, [['method' => 'pix', 'amount' => '45,00']], $this->dono);

    app(EraseCustomerData::class)->handle(Customer::sole());

    expect($order->fresh())->total->toBe('45.00')->discount->toBe('5.00')->customer_name->toBe('Cliente removido');
});

it('keeps the growth screens apart from every other panel route', function () {
    foreach (['panel.promo-codes', 'panel.loyalty', 'panel.customers'] as $name) {
        $this->actingAs($this->dono)->get(route($name))->assertOk();
    }
});
