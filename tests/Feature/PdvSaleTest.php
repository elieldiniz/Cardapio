<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\CreatePdvSale;
use App\Actions\Orders\FinalizeOrder;
use App\Exceptions\OrderRejectedException;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Cash\CashSummary;
use Illuminate\Validation\ValidationException;

/**
 * RF-13, RNF-04 — the counter sale.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->store->id, 'category_id' => $category->id, 'status_id' => DishStatus::idFor('ativo'), 'name' => 'X-Bacon', 'price' => '28.90']);
    $this->sale = fn (?array $items = null, ?array $payments = null, array $options = []) => app(CreatePdvSale::class)->handle(
        $this->store,
        $items ?? [['dish_id' => $this->dish->id, 'quantity' => 2]],
        $payments ?? [['method' => 'dinheiro', 'amount' => '57,80', 'received' => '60,00']],
        $options,
        $this->dono,
    );
});

it('blocks the sale without an open caixa and creates nothing', function () {
    expect(fn () => ($this->sale)())->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0)->and(CashMovement::count())->toBe(0);
});

it('sells at the counter: priced by the server, paid, finalized and in the caixa', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '100,00');

    $order = ($this->sale)(null, null, ['customer_name' => 'Seu João']);

    expect($order)
        ->channel->toBe('pdv')
        ->fulfillment->toBe('balcao')
        ->customer_name->toBe('Seu João')
        ->subtotal->toBe('57.80')
        ->total->toBe('57.80')
        ->and($order->status->slug)->toBe('finalizado')
        ->and($order->finished_at)->not->toBeNull()
        ->and($order->cash_session_id)->toBe($session->id)
        ->and($order->payments()->sole())->change->toBe('2.20')
        ->and(app(CashSummary::class)->for($session)['expected_cash'])->toBe('157.80');
});

it('applies a discount and requires the payments to match the discounted total', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    expect(fn () => ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']], ['discount' => '7,80']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);

    $order = ($this->sale)(null, [['method' => 'pix', 'amount' => '50,00']], ['discount' => '7,80']);

    expect($order)->subtotal->toBe('57.80')->discount->toBe('7.80')->total->toBe('50.00');
});

it('refuses a discount above the sale or an invalid one', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    foreach (['100,00', 'abc', '-5'] as $discount) {
        expect(fn () => ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']], ['discount' => $discount]))->toThrow(ValidationException::class);
    }

    expect(Order::count())->toBe(0);
});

it('ignores any price sent by the client', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    $order = ($this->sale)([['dish_id' => $this->dish->id, 'quantity' => 1, 'price' => '0.01']], [['method' => 'pix', 'amount' => '28,90']]);

    expect($order->total)->toBe('28.90');
});

it('rejects an unavailable product without writing anything', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $this->dish->update(['status_id' => DishStatus::idFor('oculto')]);

    expect(fn () => ($this->sale)())->toThrow(OrderRejectedException::class);

    expect(Order::count())->toBe(0)->and(CashMovement::count())->toBe(0);
});

it('sends the sale to the kitchen: it enters "Em preparo", already paid in the caixa', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '0');

    $order = ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']], ['send_to_kitchen' => true]);

    expect($order->status->slug)->toBe('em_preparo')->and($order->finished_at)->toBeNull()->and($order->payments()->count())->toBe(1)
        ->and(app(CashSummary::class)->for($session)['sales']['pix'])->toBe('57.80');
});

it('finishing a paid counter sale from the kitchen never registers the payment twice', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $order = ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']], ['send_to_kitchen' => true]);

    app(AdvanceOrder::class)->handle($order, 'em_rota', $this->dono);
    $result = app(FinalizeOrder::class)->handle($order->fresh(), [['method' => 'pix', 'amount' => '57,80']], $this->dono);

    expect($result['order']->status->slug)->toBe('finalizado')->and($order->payments()->count())->toBe(1)->and(CashMovement::count())->toBe(1);
});

it('records a sale on credit without touching the cash', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '0');

    ($this->sale)(null, [['method' => 'fiado', 'amount' => '57,80', 'customer_name' => 'Dona Rosa']]);

    expect(Receivable::sole())->customer_name->toBe('Dona Rosa')->and(CashMovement::count())->toBe(0)
        ->and(app(CashSummary::class)->for($session)['fiado_sold'])->toBe('57.80');
});

it('can no longer sell once the caixa is closed', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '0');
    app(CloseCashSession::class)->handle($session, '0');

    expect(fn () => ($this->sale)())->toThrow(ValidationException::class);
});

it('gives each sale its own code and no delivery fee', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    $a = ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']]);
    $b = ($this->sale)(null, [['method' => 'pix', 'amount' => '57,80']]);

    expect($a->code)->not->toBe($b->code)->and($a->delivery_fee)->toBeNull()->and($a->delivery_fee_pending)->toBeFalse();
});
