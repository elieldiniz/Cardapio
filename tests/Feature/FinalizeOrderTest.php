<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\UpdateOrderPayment;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Cash\CashSummary;
use Illuminate\Validation\ValidationException;

/**
 * RF-09, RF-06c, RNF-04 — finalizing an order with its payments.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->order = fn (array $attributes = []) => Order::factory()->delivery()->status('em_rota')->create(array_merge([
        'restaurant_id' => $this->store->id, 'subtotal' => '40.00', 'delivery_fee' => '5.00', 'total' => '45.00', 'customer_name' => 'Ana Teste', 'customer_phone' => '5569999990000',
    ], $attributes));
    $this->openCash = fn () => app(OpenCashSession::class)->handle($this->store, '100,00', $this->dono);
    $this->finalize = fn (Order $order, array $payments, bool $without = false) => app(FinalizeOrder::class)->handle($order, $payments, $this->dono, $without);
});

it('finalizes with cash, works out the change and writes the sale into the open caixa', function () {
    $session = ($this->openCash)();
    $order = ($this->order)();

    $result = ($this->finalize)($order, [['method' => 'dinheiro', 'amount' => '45,00', 'received' => '50,00']]);

    expect($result['cash_recorded'])->toBeTrue()
        ->and($result['order']->status->slug)->toBe('finalizado')
        ->and($result['order']->cash_session_id)->toBe($session->id);

    $payment = $order->payments()->sole();

    expect($payment)->amount->toBe('45.00')->received->toBe('50.00')->change->toBe('5.00')
        ->and(CashMovement::query()->where('order_id', $order->id)->sole())->amount->toBe('45.00')
        ->and(app(CashSummary::class)->for($session)['expected_cash'])->toBe('145.00');
});

it('records a Pix payment only when the dono finalizes and confirms', function () {
    ($this->openCash)();
    $order = ($this->order)(['payment_method_id' => PaymentMethod::idFor('pix')]);

    expect($order->payments()->count())->toBe(0)->and(CashMovement::count())->toBe(0);

    ($this->finalize)($order, [['method' => 'pix', 'amount' => '45,00']]);

    expect($order->payments()->sole()->method->slug)->toBe('pix')->and(CashMovement::count())->toBe(1);
});

it('splits a payment between methods and requires the parts to add up to the total', function () {
    $session = ($this->openCash)();
    $order = ($this->order)();

    expect(fn () => ($this->finalize)($order, [['method' => 'pix', 'amount' => '20,00'], ['method' => 'dinheiro', 'amount' => '20,00']]))->toThrow(ValidationException::class);
    expect($order->fresh()->status->slug)->toBe('em_rota')->and($order->payments()->count())->toBe(0);

    ($this->finalize)($order, [['method' => 'pix', 'amount' => '20,00'], ['method' => 'dinheiro', 'amount' => '25,00', 'received' => '30,00']]);

    $summary = app(CashSummary::class)->for($session);

    expect($order->payments()->count())->toBe(2)
        ->and($summary['sales']['pix'])->toBe('20.00')
        ->and($summary['sales']['dinheiro'])->toBe('25.00')
        ->and($summary['expected_cash'])->toBe('125.00');
});

it('registers fiado as a receivable and never as a cash movement', function () {
    $session = ($this->openCash)();
    $order = ($this->order)();

    ($this->finalize)($order, [['method' => 'fiado', 'amount' => '45,00']]);

    $receivable = Receivable::query()->where('order_id', $order->id)->sole();

    expect($receivable)->customer_name->toBe('Ana Teste')->amount->toBe('45.00')->cash_session_id->toBe($session->id)->and($receivable->isOpen())->toBeTrue()
        ->and(CashMovement::count())->toBe(0)
        ->and(app(CashSummary::class)->for($session)['fiado_sold'])->toBe('45.00');
});

it('accepts a fiado for another name and a part of the total in fiado', function () {
    ($this->openCash)();
    $order = ($this->order)();

    ($this->finalize)($order, [['method' => 'dinheiro', 'amount' => '15,00'], ['method' => 'fiado', 'amount' => '30,00', 'customer_name' => 'Dona Rosa', 'customer_phone' => '(69) 98888-7777']]);

    expect(Receivable::query()->where('order_id', $order->id)->sole())->customer_name->toBe('Dona Rosa')->customer_phone->toBe('69988887777')->amount->toBe('30.00');
});

it('finalizes a delivery with the caixa closed: payment kept, no cash movement, caller warned', function () {
    $order = ($this->order)();

    $result = ($this->finalize)($order, [['method' => 'dinheiro', 'amount' => '45,00']]);

    expect($result['cash_recorded'])->toBeFalse()
        ->and($result['order']->status->slug)->toBe('finalizado')
        ->and($order->payments()->count())->toBe(1)
        ->and(CashMovement::count())->toBe(0)
        ->and($order->fresh()->cash_session_id)->toBeNull();
});

it('finalizes without registering a payment', function () {
    ($this->openCash)();
    $order = ($this->order)();

    $result = ($this->finalize)($order, [], true);

    expect($result['order']->status->slug)->toBe('finalizado')->and($order->payments()->count())->toBe(0)->and(CashMovement::count())->toBe(0);
});

it('finalizing twice never duplicates the payment or the cash movement', function () {
    ($this->openCash)();
    $order = ($this->order)();
    $payments = [['method' => 'pix', 'amount' => '45,00']];

    ($this->finalize)($order, $payments);
    $again = ($this->finalize)($order, $payments);

    expect($order->payments()->count())->toBe(1)->and(CashMovement::count())->toBe(1)->and($order->history()->count())->toBe(1)->and($again['order']->status->slug)->toBe('finalizado');
});

it('only finalizes an order that is on its way and validates every payment', function () {
    ($this->openCash)();

    foreach (['novo', 'em_preparo'] as $status) {
        $early = Order::factory()->status($status)->create(['restaurant_id' => $this->store->id]);

        expect(fn () => ($this->finalize)($early, [['method' => 'pix', 'amount' => '40,00']]))->toThrow(ValidationException::class);
    }

    $order = ($this->order)();

    foreach ([
        [],
        [['method' => 'bitcoin', 'amount' => '45,00']],
        [['method' => 'pix', 'amount' => '0']],
        [['method' => 'dinheiro', 'amount' => '45,00', 'received' => '40,00']],
        [['method' => 'fiado', 'amount' => '20,00'], ['method' => 'fiado', 'amount' => '25,00']],
    ] as $payments) {
        expect(fn () => ($this->finalize)($order, $payments))->toThrow(ValidationException::class);
    }

    expect($order->fresh()->status->slug)->toBe('em_rota')->and(CashMovement::count())->toBe(0);
});

it('edits the payment of a finalized order while its caixa is still open', function () {
    $session = ($this->openCash)();
    $order = ($this->order)();
    ($this->finalize)($order, [['method' => 'dinheiro', 'amount' => '45,00']]);

    $recorded = app(UpdateOrderPayment::class)->handle($order->fresh(), [['method' => 'pix', 'amount' => '45,00']], $this->dono);

    $summary = app(CashSummary::class)->for($session);

    expect($recorded)->toBeTrue()
        ->and($order->payments()->count())->toBe(1)
        ->and($order->payments()->first()->method->slug)->toBe('pix')
        ->and(CashMovement::count())->toBe(1)
        ->and($summary['sales']['dinheiro'])->toBe('0.00')
        ->and($summary['sales']['pix'])->toBe('45.00');
});

it('can change a payment to fiado and back', function () {
    ($this->openCash)();
    $order = ($this->order)();
    ($this->finalize)($order, [['method' => 'pix', 'amount' => '45,00']]);

    app(UpdateOrderPayment::class)->handle($order->fresh(), [['method' => 'fiado', 'amount' => '45,00']], $this->dono);
    expect(Receivable::count())->toBe(1)->and(CashMovement::count())->toBe(0);

    app(UpdateOrderPayment::class)->handle($order->fresh(), [['method' => 'pix', 'amount' => '45,00']], $this->dono);
    expect(Receivable::count())->toBe(0)->and(CashMovement::count())->toBe(1);
});

it('refuses to edit the payment once its caixa is closed or the fiado was paid', function () {
    $session = ($this->openCash)();
    $order = ($this->order)();
    ($this->finalize)($order, [['method' => 'pix', 'amount' => '45,00']]);
    app(CloseCashSession::class)->handle($session, '100,00');

    expect(fn () => app(UpdateOrderPayment::class)->handle($order->fresh(), [['method' => 'dinheiro', 'amount' => '45,00']], $this->dono))->toThrow(ValidationException::class);
    expect($order->payments()->first()->method->slug)->toBe('pix');

    ($this->openCash)();
    $credit = ($this->order)();
    ($this->finalize)($credit, [['method' => 'fiado', 'amount' => '45,00']]);
    Receivable::query()->where('order_id', $credit->id)->update(['paid_at' => now()]);

    expect(fn () => app(UpdateOrderPayment::class)->handle($credit->fresh(), [['method' => 'pix', 'amount' => '45,00']], $this->dono))->toThrow(ValidationException::class);
});

it('refuses to edit the payment of an order that is not finalized', function () {
    $order = ($this->order)();

    expect(fn () => app(UpdateOrderPayment::class)->handle($order, [['method' => 'pix', 'amount' => '45,00']]))->toThrow(ValidationException::class);
});
