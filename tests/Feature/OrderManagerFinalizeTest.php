<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-09, RF-08c, UI-01 — the finalize, edit-payment and cancel modals of the order manager.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->order = Order::factory()->delivery()->status('em_rota')->create(['restaurant_id' => $this->store->id, 'code' => 'D-FIM01', 'total' => '45.00', 'customer_name' => 'Ana Teste', 'customer_phone' => '5569999990000']);
    $this->page = fn () => Livewire::actingAs($this->dono)->test('pages::panel.orders');
});

it('opens the payment modal from the Finalizar button, never finishing before payment', function () {
    ($this->page)()->call('openFinalize', $this->order->id)->assertSet('showPay', true)->assertSet('payingId', $this->order->id)->assertSee('Como o cliente pagou?')->assertSee('D-FIM01');

    expect($this->order->fresh()->status->slug)->toBe('em_rota');
});

it('warns when the caixa is closed and hides the warning when it is open', function () {
    ($this->page)()->call('openFinalize', $this->order->id)->assertSeeHtml('data-cash-closed-notice')->assertSee('O caixa está fechado');

    app(OpenCashSession::class)->handle($this->store, '100,00');

    ($this->page)()->call('openFinalize', $this->order->id)->assertDontSeeHtml('data-cash-closed-notice');
});

it('finalizes paying in cash, showing the change', function () {
    app(OpenCashSession::class)->handle($this->store, '100,00');

    ($this->page)()
        ->call('openFinalize', $this->order->id)
        ->set('payMode', 'dinheiro')
        ->set('received', '50,00')
        ->assertSeeHtml('data-change')
        ->assertSee('Troco: R$ 5,00')
        ->call('confirmPayment')
        ->assertHasNoErrors()
        ->assertSet('showPay', false);

    expect($this->order->fresh()->status->slug)->toBe('finalizado')
        ->and($this->order->payments()->sole())->change->toBe('5.00')
        ->and(CashMovement::count())->toBe(1);
});

it('says the payment stayed out of the caixa when it is closed', function () {
    ($this->page)()
        ->call('openFinalize', $this->order->id)
        ->set('payMode', 'pix')
        ->call('confirmPayment')
        ->assertDispatched('toast', message: 'Pedido finalizado. O caixa está fechado: o pagamento ficou no pedido, mas não entrou no caixa.');

    expect($this->order->fresh()->status->slug)->toBe('finalizado')->and(CashMovement::count())->toBe(0);
});

it('splits a payment and shows what is missing until it adds up', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    $page = ($this->page)()->call('openFinalize', $this->order->id)->set('payMode', 'dividir')->set('split.pix', '20,00');

    $page->assertSee('Falta R$ 25,00')->call('confirmPayment')->assertHasErrors('payments');
    expect($this->order->fresh()->status->slug)->toBe('em_rota');

    $page->set('split.dinheiro', '25,00')->assertSee('Soma certa.')->call('confirmPayment')->assertHasNoErrors();

    expect($this->order->fresh()->status->slug)->toBe('finalizado')->and($this->order->payments()->count())->toBe(2);
});

it('finalizes on credit with the customer name', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    ($this->page)()->call('openFinalize', $this->order->id)->set('payMode', 'fiado')->set('fiadoName', 'Dona Rosa')->call('confirmPayment')->assertHasNoErrors();

    expect(Receivable::query()->where('order_id', $this->order->id)->sole()->customer_name)->toBe('Dona Rosa');
});

it('finalizes without registering a payment', function () {
    ($this->page)()->call('openFinalize', $this->order->id)->call('confirmPayment', true)->assertDispatched('toast', message: 'Pedido finalizado sem registrar pagamento.');

    expect($this->order->fresh()->status->slug)->toBe('finalizado')->and($this->order->payments()->count())->toBe(0);
});

it('opens the payment modal when a card is dropped on the Finalizados column', function () {
    ($this->page)()->call('sortOrder', $this->order->id, 0, 'finalizado')->assertSet('showPay', true);

    expect($this->order->fresh()->status->slug)->toBe('em_rota');
});

it('edits the payment of a finalized order from its card', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $page = ($this->page)();
    $page->call('openFinalize', $this->order->id)->set('payMode', 'dinheiro')->call('confirmPayment');

    $page->call('openEditPayment', $this->order->id)->assertSet('payingEdit', true)->assertSee('Editar pagamento')->set('payMode', 'pix')->call('confirmPayment')->assertHasNoErrors();

    expect($this->order->payments()->sole()->method->slug)->toBe('pix');
});

it('requires a reason to cancel a finalized order, then refunds it', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $page = ($this->page)();
    $page->call('openFinalize', $this->order->id)->set('payMode', 'pix')->call('confirmPayment');

    $page->call('openCancel', $this->order->id)->assertSet('showCancel', true)->call('confirmCancel')->assertHasErrors('reason');
    expect($this->order->fresh()->status->slug)->toBe('finalizado');

    $page->set('cancelReason', 'Cliente desistiu')->call('confirmCancel')->assertHasNoErrors()->assertSet('showCancel', false);

    expect($this->order->fresh())->cancel_reason->toBe('Cliente desistiu')->and($this->order->fresh()->status->slug)->toBe('cancelado')
        ->and(CashMovement::count())->toBe(2);
});

it('shows a clear error when cancelling needs an open caixa', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '0');
    $page = ($this->page)();
    $page->call('openFinalize', $this->order->id)->set('payMode', 'pix')->call('confirmPayment');
    app(CloseCashSession::class)->handle($session, '0');

    $page->call('openCancel', $this->order->id)->set('cancelReason', 'Erro de digitação')->call('confirmCancel')->assertHasErrors('cash')->assertSee('Abra o caixa para registrar o estorno');

    expect($this->order->fresh()->status->slug)->toBe('finalizado');
});

it('never opens the modals for an order of another store', function () {
    $foreign = Order::factory()->status('em_rota')->create();

    ($this->page)()->call('openFinalize', $foreign->id)->assertNotFound();
    ($this->page)()->call('openCancel', $foreign->id)->assertNotFound();
});

it('shows the payment summary and the edit and cancel buttons on a finalized card', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $page = ($this->page)();
    $page->call('openFinalize', $this->order->id)->set('payMode', 'pix')->call('confirmPayment');

    $page->assertSeeHtml('data-paid')->assertSeeHtml('data-edit-payment')->assertSeeHtml('data-cancel')->assertSee('R$ 45,00 · Pix');
});
