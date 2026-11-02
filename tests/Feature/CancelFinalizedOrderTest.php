<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordMovement;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Cash\CashSummary;
use Illuminate\Validation\ValidationException;

/**
 * RF-08c, RNF-04 — cancelling a finalized order: reason, refund, fiado.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->session = app(OpenCashSession::class)->handle($this->store, '100,00', $this->dono);
    $this->finalized = function (array $payments) {
        $order = Order::factory()->delivery()->status('em_rota')->create(['restaurant_id' => $this->store->id, 'total' => '45.00', 'customer_name' => 'Ana', 'customer_phone' => '5569999990000']);
        app(FinalizeOrder::class)->handle($order, $payments, $this->dono);

        return $order->fresh();
    };
    $this->cancel = fn (Order $order, ?string $reason = 'Cliente desistiu') => app(CancelFinalizedOrder::class)->handle($order, $reason, $this->dono);
});

it('refuses to cancel without a reason', function () {
    $order = ($this->finalized)([['method' => 'pix', 'amount' => '45,00']]);

    foreach ([null, '', '  ', 'ab'] as $reason) {
        expect(fn () => ($this->cancel)($order, $reason))->toThrow(ValidationException::class);
    }

    expect($order->fresh()->status->slug)->toBe('finalizado')->and(CashMovement::count())->toBe(1);
});

it('refunds the sale without deleting it and puts the refund in the open caixa', function () {
    $order = ($this->finalized)([['method' => 'dinheiro', 'amount' => '45,00']]);

    $cancelled = ($this->cancel)($order);

    $summary = app(CashSummary::class)->for($this->session);

    expect($cancelled)->status->slug->toBe('cancelado')
        ->and($cancelled->cancel_reason)->toBe('Cliente desistiu')
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and(CashMovement::count())->toBe(2)
        ->and(CashMovement::query()->where('order_id', $order->id)->with('type')->get()->pluck('type.slug')->sort()->values()->all())->toBe(['estorno', 'venda'])
        ->and($summary['sales']['dinheiro'])->toBe('0.00')
        ->and($summary['refunds_cash'])->toBe('45.00')
        ->and($summary['expected_cash'])->toBe('100.00');
});

it('refunds each part of a split payment through the same method it was taken', function () {
    $order = ($this->finalized)([['method' => 'pix', 'amount' => '20,00'], ['method' => 'dinheiro', 'amount' => '25,00']]);

    ($this->cancel)($order);

    $summary = app(CashSummary::class)->for($this->session);

    expect(CashMovement::query()->where('order_id', $order->id)->count())->toBe(4)
        ->and($summary['sales']['pix'])->toBe('0.00')
        ->and($summary['sales']['dinheiro'])->toBe('0.00');
});

it('cancelling twice writes one refund only', function () {
    $order = ($this->finalized)([['method' => 'pix', 'amount' => '45,00']]);

    ($this->cancel)($order);
    ($this->cancel)($order->fresh());

    expect(CashMovement::query()->where('order_id', $order->id)->count())->toBe(2)->and($order->history()->count())->toBe(2);
});

it('cancels an unpaid fiado', function () {
    $order = ($this->finalized)([['method' => 'fiado', 'amount' => '45,00']]);

    ($this->cancel)($order);

    expect(Receivable::query()->where('order_id', $order->id)->sole()->cancelled_at)->not->toBeNull()
        ->and(CashMovement::count())->toBe(0)
        ->and(app(CashSummary::class)->for($this->session)['fiado_sold'])->toBe('0.00');
});

it('keeps a paid fiado and refunds its payment', function () {
    $order = ($this->finalized)([['method' => 'fiado', 'amount' => '45,00']]);
    $receivable = Receivable::query()->where('order_id', $order->id)->sole();
    $receivable->update(['paid_at' => now()]);
    app(RecordMovement::class)->handle($this->store, 'recebimento_fiado', '45,00', 'pix', ['order' => $order, 'reference' => 'receivable_paid:'.$receivable->id, 'session' => $this->session]);

    ($this->cancel)($order);

    expect($receivable->fresh())->cancelled_at->toBeNull()->paid_at->not->toBeNull()
        ->and(CashMovement::query()->where('order_id', $order->id)->with('type')->get()->pluck('type.slug')->sort()->values()->all())->toBe(['estorno', 'recebimento_fiado'])
        ->and(app(CashSummary::class)->for($this->session)['sales']['pix'])->toBe('0.00');
});

it('needs an open caixa to refund money taken in the register', function () {
    $order = ($this->finalized)([['method' => 'pix', 'amount' => '45,00']]);
    app(CloseCashSession::class)->handle($this->session, '100,00');

    expect(fn () => ($this->cancel)($order))->toThrow(ValidationException::class);

    expect($order->fresh()->status->slug)->toBe('finalizado')->and(CashMovement::count())->toBe(1);
});

it('cancels without a refund when no money went through the register', function () {
    app(CloseCashSession::class)->handle($this->session, '100,00');
    $order = Order::factory()->delivery()->status('em_rota')->create(['restaurant_id' => $this->store->id, 'total' => '45.00']);
    app(FinalizeOrder::class)->handle($order, [['method' => 'dinheiro', 'amount' => '45,00']], $this->dono);

    expect(($this->cancel)($order->fresh())->status->slug)->toBe('cancelado')->and(CashMovement::count())->toBe(0);
});

it('only cancels finalized orders', function () {
    foreach (['novo', 'em_preparo', 'em_rota', 'recusado'] as $status) {
        $order = Order::factory()->status($status)->create(['restaurant_id' => $this->store->id]);

        expect(fn () => ($this->cancel)($order))->toThrow(ValidationException::class);
    }
});

it('keeps the sale in its own day: the refund goes to today, the old sale is not edited', function () {
    $order = ($this->finalized)([['method' => 'dinheiro', 'amount' => '45,00']]);
    $sale = CashMovement::query()->where('order_id', $order->id)->sole();

    ($this->cancel)($order);

    expect($sale->fresh())->amount->toBe('45.00')->and($sale->fresh()->cash_session_id)->toBe($this->session->id);
});
