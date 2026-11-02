<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordMovement;
use App\Actions\Cash\SettleReceivable;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use App\Services\Cash\ReportBuilder;
use Livewire\Livewire;

/**
 * RF-12, RNF-09 — the financial report.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id]);
    app(OpenCashSession::class)->handle($this->store, '0', $this->dono);
    $this->sell = function (string $total, array $payments, array $attributes = []) {
        $order = Order::factory()->delivery()->status('em_rota')->create(array_merge(['restaurant_id' => $this->store->id, 'subtotal' => $total, 'delivery_fee' => '0.00', 'total' => $total], $attributes));
        app(FinalizeOrder::class)->handle($order, $payments, $this->dono);

        return $order;
    };
});

it('totals the sales, the ticket, what came in, what went out and the result, matching the movements', function () {
    ($this->sell)('100.00', [['method' => 'pix', 'amount' => '100,00']]);
    ($this->sell)('50.00', [['method' => 'dinheiro', 'amount' => '50,00']]);
    ($this->sell)('30.00', [['method' => 'fiado', 'amount' => '30,00']]);
    app(RecordMovement::class)->handle($this->store, 'despesa', '40,00', 'dinheiro', ['category' => 'Gás']);

    $report = app(ReportBuilder::class)->build($this->store, 'hoje');

    expect($report['sales_total'])->toBe('180.00')
        ->and($report['sales_count'])->toBe(3)
        ->and($report['ticket'])->toBe('60.00')
        ->and($report['received'])->toBe('150.00')
        ->and($report['expenses'])->toBe('40.00')
        ->and($report['result'])->toBe('110.00')
        ->and($report['by_method'])->toBe(['pix' => '100.00', 'dinheiro' => '50.00', 'cartao' => '0.00']);
});

it('result is received minus expenses', function () {
    ($this->sell)('80.00', [['method' => 'pix', 'amount' => '80,00']]);
    app(RecordMovement::class)->handle($this->store, 'despesa', '25,50', 'pix', ['category' => 'Gelo']);

    $report = app(ReportBuilder::class)->build($this->store, 'hoje');

    expect((float) $report['result'])->toBe((float) $report['received'] - (float) $report['expenses'])->and($report['result'])->toBe('54.50');
});

it('counts a fiado as sold but only as received once it is paid', function () {
    $order = ($this->sell)('30.00', [['method' => 'fiado', 'amount' => '30,00']]);

    expect(app(ReportBuilder::class)->build($this->store, 'hoje'))->toMatchArray(['sales_total' => '30.00', 'received' => '0.00']);

    app(SettleReceivable::class)->handle($order->receivable ?? Receivable::query()->where('order_id', $order->id)->first(), 'pix', $this->dono);

    expect(app(ReportBuilder::class)->build($this->store, 'hoje'))->toMatchArray(['sales_total' => '30.00', 'received' => '30.00']);
});

it('subtracts refunds and leaves cancelled orders out of the sales', function () {
    $order = ($this->sell)('100.00', [['method' => 'pix', 'amount' => '100,00']]);
    ($this->sell)('50.00', [['method' => 'pix', 'amount' => '50,00']]);

    app(CancelFinalizedOrder::class)->handle($order->fresh(), 'Erro', $this->dono);

    $report = app(ReportBuilder::class)->build($this->store, 'hoje');

    expect($report['sales_total'])->toBe('50.00')->and($report['sales_count'])->toBe(1)->and($report['received'])->toBe('50.00')->and($report['by_method']['pix'])->toBe('50.00');
});

it('groups the sales by origin', function () {
    ($this->sell)('100.00', [['method' => 'pix', 'amount' => '100,00']], ['origin' => 'instagram']);
    ($this->sell)('40.00', [['method' => 'pix', 'amount' => '40,00']], ['origin' => 'instagram']);
    ($this->sell)('25.00', [['method' => 'pix', 'amount' => '25,00']], ['origin' => 'direto']);

    $origins = collect(app(ReportBuilder::class)->build($this->store, 'hoje')['by_origin'])->keyBy('origin');

    expect($origins['instagram'])->toMatchArray(['orders' => 2, 'total' => '140.00'])->and($origins['direto'])->toMatchArray(['orders' => 1, 'total' => '25.00']);
});

it('uses the store calendar days, not UTC', function () {
    // Tuesday 02:00 UTC is still Monday 22:00 in Porto Velho: this sale belongs to Monday.
    $this->travelTo('2026-11-03 02:00:00');
    ($this->sell)('60.00', [['method' => 'pix', 'amount' => '60,00']]);

    expect(app(ReportBuilder::class)->build($this->store, 'hoje')['sales_total'])->toBe('60.00');

    $this->travelTo('2026-11-03 05:00:00'); // 01:00 Tuesday in Porto Velho: a new day

    expect(app(ReportBuilder::class)->build($this->store, 'hoje')['sales_total'])->toBe('0.00')
        ->and(app(ReportBuilder::class)->build($this->store, 'ontem')['sales_total'])->toBe('60.00');
});

it('covers the week, the month and last month', function () {
    $this->travelTo('2026-11-15 15:00:00');
    ($this->sell)('10.00', [['method' => 'pix', 'amount' => '10,00']]);

    expect(app(ReportBuilder::class)->build($this->store, 'mes')['sales_total'])->toBe('10.00')
        ->and(app(ReportBuilder::class)->build($this->store, '7dias')['sales_total'])->toBe('10.00')
        ->and(app(ReportBuilder::class)->build($this->store, 'mes_passado')['sales_total'])->toBe('0.00');
});

it('renders the screen with the cards and the tabs', function () {
    ($this->sell)('100.00', [['method' => 'pix', 'amount' => '100,00']], ['origin' => 'instagram']);

    $this->actingAs($this->dono)->get(route('panel.report'))->assertOk()->assertSee('Relatório');

    Livewire::actingAs($this->dono)->test('pages::panel.report')
        ->assertSeeHtml('data-report')
        ->assertSee('R$ 100,00')
        ->assertSee('Instagram')
        ->set('period', 'ontem')->assertSee('Nenhuma venda no período.')
        ->set('period', 'mes')->assertSee('R$ 100,00');
});

it('is behind the cash switch of the plan and isolated per store', function () {
    $this->store->plan->update(['features' => ['cash' => false]]);
    endTrial($this->store);
    // With a caixa still open the screens stay reachable so it can be closed; once closed they lock.
    $this->store->cashSessions()->update(['closed_at' => now()]);
    $this->actingAs($this->dono->fresh())->get(route('panel.report'))->assertRedirect(route('panel.locked', 'cash'));

    $this->store->plan->update(['features' => null]);
    $other = User::factory()->create();
    ($this->sell)('100.00', [['method' => 'pix', 'amount' => '100,00']]);

    expect(app(ReportBuilder::class)->build($other->restaurant, 'hoje')['sales_total'])->toBe('0.00');
});
