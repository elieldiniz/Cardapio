<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordMovement;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-10, UI-01 — the Caixa screen.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->page = fn () => Livewire::actingAs($this->dono)->test('pages::panel.cash');
});

it('requires an owner, is reachable from the menu and respects the plan switch', function () {
    $this->get(route('panel.cash'))->assertRedirect(route('login'));

    $this->actingAs($this->dono)->get(route('panel.cash'))->assertOk()->assertSee('Caixa fechado');
    $this->actingAs($this->dono)->get(route('panel.home'))->assertSee('Caixa');

    $this->store->plan->update(['features' => ['cash' => false]]);

    endTrial($this->store);

    $this->actingAs($this->dono->fresh())->get(route('panel.cash'))->assertRedirect(route('panel.locked', 'cash'));
});

it('opens the caixa with the change in the drawer', function () {
    ($this->page)()->set('openingFloat', '100,00')->call('openCash')->assertHasNoErrors()->assertSeeHtml('data-cash-open')->assertSee('R$ 100,00');

    expect($this->store->openCashSession()->opening_float)->toBe('100.00');
});

it('refuses an invalid opening value and a second opening', function () {
    ($this->page)()->set('openingFloat', 'abc')->call('openCash')->assertHasErrors('opening_float');

    app(OpenCashSession::class)->handle($this->store, '0');

    expect($this->store->cashSessions()->count())->toBe(1);
});

it('shows the cash expected in the drawer and the sales by method', function () {
    app(OpenCashSession::class)->handle($this->store, '100,00');
    $record = fn (...$arguments) => app(RecordMovement::class)->handle($this->store, ...$arguments);
    $order = Order::factory()->create(['restaurant_id' => $this->store->id, 'code' => 'D-AAAAA']);

    $record('venda', '50,00', 'dinheiro', ['order' => $order, 'reference' => 'a']);
    $record('venda', '30,00', 'pix', ['reference' => 'b']);
    $record('venda', '20,00', 'credito', ['reference' => 'c']);

    ($this->page)()
        ->assertSeeHtml('data-expected-value')
        ->assertSee('R$ 150,00')
        ->assertSee('R$ 100,00')
        ->assertSee('1 venda');

    $page = ($this->page)();

    expect($page->instance()->summary()['sales'])->toBe(['dinheiro' => '50.00', 'pix' => '30.00', 'cartao' => '20.00']);
});

it('lists the movements, newest first, with sign and label', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $order = Order::factory()->create(['restaurant_id' => $this->store->id, 'code' => 'D-AAAAA']);
    app(RecordMovement::class)->handle($this->store, 'venda', '40,00', 'pix', ['order' => $order, 'reference' => 'v1']);
    app(RecordMovement::class)->handle($this->store, 'despesa', '15,00', 'dinheiro', ['category' => 'Gás', 'description' => 'botijão']);

    ($this->page)()
        ->assertSeeHtml('data-movement-row="venda"')
        ->assertSeeHtml('data-movement-row="despesa"')
        ->assertSee('D-AAAAA · Pix')
        ->assertSee('Despesa · Gás — botijão')
        ->assertSee('+R$ 40,00')
        ->assertSee('−R$ 15,00')
        ->assertSeeInOrder(['Despesa · Gás', 'D-AAAAA · Pix']);
});

it('launches an expense, a withdrawal and a top-up from the modal', function () {
    app(OpenCashSession::class)->handle($this->store, '100,00');
    $page = ($this->page)();

    $page->call('startMovement', 'despesa')->assertSet('showMovement', true)->set('movementAmount', '12,50')->set('movementCategory', 'Gelo')->set('movementMethod', 'pix')->call('saveMovement')->assertHasNoErrors()->assertSet('showMovement', false);
    $page->call('startMovement', 'retirada')->set('movementAmount', '30,00')->call('saveMovement')->assertHasNoErrors();
    $page->call('startMovement', 'reforco')->set('movementAmount', '10,00')->call('saveMovement')->assertHasNoErrors();

    expect(CashMovement::query()->with('type')->get()->pluck('type.slug')->all())->toBe(['despesa', 'retirada', 'reforco']);

    // 100 − 30 + 10 = 80 (the Pix expense does not touch the drawer).
    expect(($this->page)()->instance()->summary()['expected_cash'])->toBe('80.00');
});

it('shows validation errors in the movement modal', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    ($this->page)()->call('startMovement', 'despesa')->set('movementAmount', '')->call('saveMovement')->assertHasErrors('movementAmount');
    ($this->page)()->call('startMovement', 'despesa')->set('movementAmount', '10')->set('movementCategory', 'Cassino')->call('saveMovement')->assertHasErrors('category');
});

it('rejects an unknown movement type', function () {
    ($this->page)()->call('startMovement', 'roubo')->assertNotFound();
});

it('closes counting the cash and shows the difference of the last closing', function () {
    app(OpenCashSession::class)->handle($this->store, '100,00');
    app(RecordMovement::class)->handle($this->store, 'venda', '50,00', 'dinheiro', ['reference' => 'v']);

    $page = ($this->page)();

    $page->call('startClose')->assertSet('showClose', true)->assertSee('R$ 150,00')->call('closeCash')->assertHasErrors('counted_cash');

    $page->set('countedCash', '145,00')->call('closeCash')->assertHasNoErrors()->assertSet('showClose', false)->assertSeeHtml('data-last-closed')->assertSee('Caixa fechado');

    $closed = $this->store->cashSessions()->first();

    expect($closed->difference)->toBe('-5.00')->and($this->store->openCashSession())->toBeNull();
});

it('celebrates a perfect count', function () {
    app(OpenCashSession::class)->handle($this->store, '100,00');

    ($this->page)()->call('startClose')->set('countedCash', '100,00')->call('closeCash')->assertDispatched('toast', message: 'Caixa fechado. O dinheiro bateu certinho!');
});

it('never shows the cash of another store', function () {
    $other = User::factory()->create();
    app(OpenCashSession::class)->handle($other->restaurant, '999,00');

    ($this->page)()->assertSee('Caixa fechado')->assertDontSee('999,00');
});
