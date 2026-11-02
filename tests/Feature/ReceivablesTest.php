<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\SettleReceivable;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Cash\CashSummary;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-11, RNF-04 — the fiado screen and its settlement.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->fiado = fn (string $name, string $amount, array $extra = []) => Receivable::query()->create(array_merge([
        'restaurant_id' => $this->store->id, 'customer_name' => $name, 'customer_phone' => '5569999990000', 'amount' => $amount,
    ], $extra));
    $this->page = fn () => Livewire::actingAs($this->dono)->test('pages::panel.receivables');
});

it('requires an owner and respects the cash switch of the plan', function () {
    $this->get(route('panel.receivables'))->assertRedirect(route('login'));
    $this->actingAs($this->dono)->get(route('panel.receivables'))->assertOk()->assertSee('Fiado')->assertSee('Ninguém devendo');

    $this->store->plan->update(['features' => ['cash' => false]]);

    endTrial($this->store);

    $this->actingAs($this->dono->fresh())->get(route('panel.receivables'))->assertRedirect(route('panel.locked', 'cash'));
});

it('totals what is owed as the sum of the open fiados only', function () {
    ($this->fiado)('Ana', '30.00');
    ($this->fiado)('Bia', '20.50');
    ($this->fiado)('Caio', '99.00', ['paid_at' => now()]);
    ($this->fiado)('Dani', '77.00', ['cancelled_at' => now()]);

    $page = ($this->page)();

    expect($page->instance()->totalOwed())->toBe('50.50');

    $page->assertSee('R$ 50,50')->assertSee('Ana')->assertSee('Bia')->assertDontSee('Caio')->assertDontSee('Dani');
    expect($page->instance()->open()->count())->toBe(2);
});

it('lists the paid ones on their own tab', function () {
    ($this->fiado)('Caio', '99.00', ['paid_at' => now()]);
    ($this->fiado)('Ana', '30.00');

    ($this->page)()->set('tab', 'pagos')->assertSee('Caio')->assertDontSee('Ana');
});

it('gives a charge link with the customer number and the amount', function () {
    ($this->fiado)('Ana Souza', '30.00');

    ($this->page)()->assertSeeHtml('data-charge')->assertSeeHtml('phone=5569999990000')->assertSeeHtml(rawurlencode('R$ 30,00'));
});

it('receives a fiado into the open caixa through the chosen method', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '100,00');
    $order = Order::factory()->create(['restaurant_id' => $this->store->id]);
    $receivable = ($this->fiado)('Ana', '30.00', ['order_id' => $order->id]);

    ($this->page)()->call('startReceive', $receivable->id)->assertSet('showReceive', true)->set('receiveMethod', 'dinheiro')->call('receive')->assertHasNoErrors()->assertSet('showReceive', false);

    $summary = app(CashSummary::class)->for($session);

    expect($receivable->fresh()->paid_at)->not->toBeNull()
        ->and(CashMovement::query()->with('type')->sole()->type->slug)->toBe('recebimento_fiado')
        ->and($summary['sales']['dinheiro'])->toBe('30.00')
        ->and($summary['expected_cash'])->toBe('130.00');
});

it('receiving twice enters the money once', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $receivable = ($this->fiado)('Ana', '30.00');

    app(SettleReceivable::class)->handle($receivable, 'pix', $this->dono);
    app(SettleReceivable::class)->handle($receivable->fresh(), 'pix', $this->dono);

    expect(CashMovement::count())->toBe(1);
});

it('needs an open caixa to receive and refuses a cancelled fiado', function () {
    $receivable = ($this->fiado)('Ana', '30.00');

    expect(fn () => app(SettleReceivable::class)->handle($receivable, 'pix'))->toThrow(ValidationException::class);
    expect($receivable->fresh()->paid_at)->toBeNull();

    app(OpenCashSession::class)->handle($this->store, '0');
    $receivable->update(['cancelled_at' => now()]);

    expect(fn () => app(SettleReceivable::class)->handle($receivable->fresh(), 'pix'))->toThrow(ValidationException::class);
});

it('shows the error in the modal when the caixa is closed', function () {
    $receivable = ($this->fiado)('Ana', '30.00');

    ($this->page)()->call('startReceive', $receivable->id)->call('receive')->assertHasErrors('cash')->assertSee('Abra o caixa para registrar o recebimento');
});

it('never shows or receives the fiado of another store', function () {
    $foreign = Receivable::query()->create(['restaurant_id' => User::factory()->create()->restaurant_id, 'customer_name' => 'Intruso', 'amount' => '10.00']);

    ($this->page)()->assertDontSee('Intruso')->call('startReceive', $foreign->id)->assertNotFound();
});
