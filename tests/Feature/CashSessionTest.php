<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordMovement;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Cash\CashSummary;
use Illuminate\Validation\ValidationException;

/**
 * RF-10, RNF-04 — opening, recording and closing the cash register.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->open = fn (string $float = '100,00') => app(OpenCashSession::class)->handle($this->store, $float, $this->dono);
    $this->record = fn (string $type, string $amount, ?string $method = null, array $meta = []) => app(RecordMovement::class)->handle($this->store, $type, $amount, $method, $meta);
});

it('opens with the change in the drawer and refuses a second open session', function () {
    $session = ($this->open)('100,00');

    expect($session->opening_float)->toBe('100.00')->and($session->isOpen())->toBeTrue()->and($session->opened_by)->toBe($this->dono->id);

    expect(fn () => ($this->open)())->toThrow(ValidationException::class);
    expect($this->store->cashSessions()->count())->toBe(1);
});

it('opens without change when the value is empty, and rejects a bad value', function () {
    expect(app(OpenCashSession::class)->handle($this->store, '')->opening_float)->toBe('0.00');
});

it('rejects an invalid opening value', function () {
    expect(fn () => app(OpenCashSession::class)->handle($this->store, 'abc'))->toThrow(ValidationException::class);
});

it('computes the expected cash as change + cash sales + top-ups − withdrawals − cash expenses − cash refunds, and keeps Pix and card out', function () {
    $session = ($this->open)('100,00');

    ($this->record)('venda', '50,00', 'dinheiro');
    ($this->record)('venda', '80,00', 'pix');
    ($this->record)('venda', '30,00', 'credito');
    ($this->record)('recebimento_fiado', '20,00', 'dinheiro');
    ($this->record)('reforco', '40,00');
    ($this->record)('retirada', '60,00');
    ($this->record)('despesa', '15,00', 'dinheiro', ['category' => 'Gás']);
    ($this->record)('despesa', '25,00', 'pix', ['category' => 'Gelo']);
    ($this->record)('estorno', '10,00', 'dinheiro');

    $summary = app(CashSummary::class)->for($session);

    // 100 + (50 + 20 - 10) + 40 - 60 - 15 = 125
    expect($summary['expected_cash'])->toBe('125.00')
        ->and($summary['sales']['dinheiro'])->toBe('60.00')
        ->and($summary['sales']['pix'])->toBe('80.00')
        ->and($summary['sales']['cartao'])->toBe('30.00')
        ->and($summary['sales_total'])->toBe('170.00')
        ->and($summary['expenses_total'])->toBe('40.00')
        ->and($summary['expenses_cash'])->toBe('15.00')
        ->and($summary['withdrawals'])->toBe('60.00')
        ->and($summary['top_ups'])->toBe('40.00')
        ->and($summary['refunds_cash'])->toBe('10.00');
});

it('counts the cash sold on credit only as "vendido no fiado"', function () {
    $session = ($this->open)('0');
    $order = Order::factory()->create(['restaurant_id' => $this->store->id]);
    Receivable::query()->create(['restaurant_id' => $this->store->id, 'order_id' => $order->id, 'cash_session_id' => $session->id, 'customer_name' => 'Ana', 'amount' => '45.00', 'reference' => 'order_fiado:'.$order->id]);

    $summary = app(CashSummary::class)->for($session);

    expect($summary['fiado_sold'])->toBe('45.00')->and($summary['expected_cash'])->toBe('0.00')->and($summary['sales_total'])->toBe('0.00');
});

it('counts the distinct orders of the sales', function () {
    $session = ($this->open)('0');
    $order = Order::factory()->create(['restaurant_id' => $this->store->id]);

    ($this->record)('venda', '20,00', 'pix', ['order' => $order, 'reference' => 'a']);
    ($this->record)('venda', '20,00', 'dinheiro', ['order' => $order, 'reference' => 'b']);

    expect(app(CashSummary::class)->for($session)['sales_count'])->toBe(1);
});

it('records the same reference only once', function () {
    ($this->open)();

    $first = ($this->record)('venda', '50,00', 'dinheiro', ['reference' => 'order_sale:1:0']);
    $second = ($this->record)('venda', '50,00', 'dinheiro', ['reference' => 'order_sale:1:0']);

    expect($second->id)->toBe($first->id)->and($this->store->openCashSession()->movements()->count())->toBe(1);
});

it('refuses to record without an open cash register', function () {
    expect(fn () => ($this->record)('venda', '10,00', 'pix'))->toThrow(ValidationException::class);
});

it('validates the amount, the method and the expense category', function () {
    ($this->open)();

    foreach ([
        fn () => ($this->record)('venda', '0', 'pix'),
        fn () => ($this->record)('venda', 'abc', 'pix'),
        fn () => ($this->record)('venda', '10,00', 'cheque'),
        fn () => ($this->record)('venda', '10,00', null),
        fn () => ($this->record)('despesa', '10,00', 'credito'),
        fn () => ($this->record)('despesa', '10,00', 'dinheiro', ['category' => 'Cassino']),
        fn () => ($this->record)('lixo', '10,00', 'pix'),
    ] as $attempt) {
        expect($attempt)->toThrow(ValidationException::class);
    }

    expect($this->store->openCashSession()->movements()->count())->toBe(0);
});

it('forces withdrawals and top-ups to cash', function () {
    ($this->open)();

    expect(($this->record)('retirada', '20,00', 'pix')->paymentMethod->slug)->toBe('dinheiro')
        ->and(($this->record)('reforco', '5,00')->paymentMethod->slug)->toBe('dinheiro');
});

it('defaults an expense category to "Outros"', function () {
    ($this->open)();

    expect(($this->record)('despesa', '12,00', 'dinheiro')->category)->toBe('Outros');
});

it('closes comparing the counted cash with the expected, keeping both on the session', function () {
    $session = ($this->open)('100,00');
    ($this->record)('venda', '50,00', 'dinheiro');

    $closed = app(CloseCashSession::class)->handle($session, '145,00', 'Faltou troco', $this->dono);

    expect($closed->isOpen())->toBeFalse()
        ->and($closed->expected_cash)->toBe('150.00')
        ->and($closed->counted_cash)->toBe('145.00')
        ->and($closed->difference)->toBe('-5.00')
        ->and($closed->notes)->toBe('Faltou troco')
        ->and($closed->closed_by)->toBe($this->dono->id);
});

it('refuses to close twice, to close without a count and to record into a closed session', function () {
    $session = ($this->open)();

    expect(fn () => app(CloseCashSession::class)->handle($session, ''))->toThrow(ValidationException::class);

    app(CloseCashSession::class)->handle($session, '100,00');

    expect(fn () => app(CloseCashSession::class)->handle($session, '100,00'))->toThrow(ValidationException::class);
    expect(fn () => ($this->record)('venda', '10,00', 'pix'))->toThrow(ValidationException::class);
    expect(fn () => app(RecordMovement::class)->handle($this->store, 'venda', '10,00', 'pix', ['session' => $session]))->toThrow(ValidationException::class);
});

it('lets the store open a new session after closing', function () {
    $session = ($this->open)();
    app(CloseCashSession::class)->handle($session, '100,00');

    expect(($this->open)('50,00')->id)->not->toBe($session->id);
});
