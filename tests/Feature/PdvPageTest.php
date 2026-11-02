<?php

use App\Actions\Cash\OpenCashSession;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-13, UI-01 — the counter (PDV) screen.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->store->id, 'name' => 'Lanches']);
    $this->make = fn (string $name, string $price, array $extra = []) => Dish::factory()->create(array_merge([
        'restaurant_id' => $this->store->id, 'category_id' => $this->category->id, 'status_id' => DishStatus::idFor('ativo'), 'name' => $name, 'price' => $price,
    ], $extra));
    $this->bacon = ($this->make)('X-Bacon', '28.90');
    $this->coca = ($this->make)('Coca-Cola', '7.00');
    $this->page = fn () => Livewire::actingAs($this->dono)->test('pages::panel.pdv');
});

it('requires an owner, is reachable from the menu and respects the plan switch', function () {
    $this->get(route('panel.pdv'))->assertRedirect(route('login'));
    $this->actingAs($this->dono)->get(route('panel.pdv'))->assertOk()->assertSee('PDV Balcão')->assertSee('X-Bacon');
    $this->actingAs($this->dono)->get(route('panel.home'))->assertSee('PDV Balcão');

    $this->store->plan->update(['features' => ['pdv' => false]]);

    endTrial($this->store);

    $this->actingAs($this->dono->fresh())->get(route('panel.pdv'))->assertRedirect(route('panel.locked', 'pdv'));
});

it('tells the dono to open the caixa and blocks charging while it is closed', function () {
    ($this->page)()->assertSeeHtml('data-pdv-closed')->call('pick', $this->bacon->id)->call('charge')->assertHasErrors('cash')->assertSet('showPay', false);
});

it('adds simple products straight to the sale and merges the same product', function () {
    $page = ($this->page)()->call('pick', $this->coca->id)->call('pick', $this->coca->id);

    expect($page->get('cart'))->toHaveCount(1)->and($page->get('cart')[0]['qty'])->toBe(2.0);
    $page->assertSee('R$ 14,00');
});

it('lists only active products, filters by category and searches', function () {
    ($this->make)('Oculto', '9.00', ['status_id' => DishStatus::idFor('oculto')]);
    ($this->make)('Esgotado', '9.00', ['status_id' => DishStatus::idFor('esgotado')]);
    $drinks = Category::factory()->create(['restaurant_id' => $this->store->id, 'name' => 'Bebidas']);
    ($this->make)('Suco', '8.00', ['category_id' => $drinks->id]);

    ($this->page)()->assertSee('X-Bacon')->assertSee('Suco')->assertDontSee('Oculto')->assertDontSee('Esgotado')
        ->set('category', (string) $drinks->id)->assertSee('Suco')->assertDontSee('X-Bacon')
        ->set('category', 'all')->set('search', 'coca')->assertSee('Coca-Cola')->assertDontSee('X-Bacon');
});

it('asks the choices of a product with options and prices them', function () {
    $size = OptionGroup::factory()->required()->create(['restaurant_id' => $this->store->id, 'name' => 'Tamanho']);
    $medium = Option::factory()->create(['option_group_id' => $size->id, 'name' => 'Médio', 'price' => '5.00']);
    $this->bacon->optionGroups()->attach($size->id, ['display_order' => 1]);

    $page = ($this->page)()->call('pick', $this->bacon->id)->assertSet('showChoose', true)->assertSee('Tamanho')->assertSee('Obrigatório');

    $page->call('confirmChoose')->assertHasErrors('choose')->assertSet('showChoose', true);
    expect($page->get('cart'))->toBe([]);

    $page->call('toggleOption', $size->id, $medium->id)->call('confirmChoose')->assertHasNoErrors()->assertSet('showChoose', false)->assertSee('R$ 33,90');
});

it('charges: pays in cash, finishes the sale into the caixa and clears the screen', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '100,00');

    ($this->page)()
        ->call('pick', $this->bacon->id)->call('pick', $this->coca->id)
        ->set('customerName', 'Seu João')
        ->call('charge')->assertSet('showPay', true)->assertSee('R$ 35,90')
        ->set('payMode', 'dinheiro')->set('received', '50,00')->assertSee('Troco: R$ 14,10')
        ->call('finishSale')->assertHasNoErrors()
        ->assertDispatched('pdv-sale-done')
        ->assertSet('cart', [])
        ->assertSet('showPay', false);

    $order = Order::sole();

    expect($order)->channel->toBe('pdv')->total->toBe('35.90')->customer_name->toBe('Seu João')->and($order->status->slug)->toBe('finalizado')
        ->and(CashMovement::query()->where('cash_session_id', $session->id)->sole())->amount->toBe('35.90');
});

it('applies a discount and refuses one above the sale', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    ($this->page)()->call('pick', $this->coca->id)->set('discount', '2,00')->assertSee('R$ 5,00')->set('discount', '99,00')->assertSee('Desconto inválido');

    $page = ($this->page)()->call('pick', $this->coca->id)->set('discount', '99,00')->call('charge')->assertHasErrors('cart')->assertSet('showPay', false);

    expect(Order::count())->toBe(0);
});

it('sends to the kitchen and splits the payment', function () {
    app(OpenCashSession::class)->handle($this->store, '0');

    $page = ($this->page)()->call('pick', $this->bacon->id)->set('sendToKitchen', true)->call('charge')->set('payMode', 'dividir')->set('split.pix', '10,00');

    $page->assertSee('Falta R$ 18,90')->call('finishSale')->assertHasErrors('payments');
    expect(Order::count())->toBe(0);

    $page->set('split.dinheiro', '18,90')->assertSee('Soma certa.')->call('finishSale')->assertHasNoErrors();

    expect(Order::sole()->status->slug)->toBe('em_preparo')->and(Order::sole()->payments()->count())->toBe(2);
});

it('edits and removes lines, and clears the sale', function () {
    $size = OptionGroup::factory()->create(['restaurant_id' => $this->store->id, 'name' => 'Extras', 'mode' => 'many', 'max_choices' => 3]);
    $cheese = Option::factory()->create(['option_group_id' => $size->id, 'name' => 'Queijo', 'price' => '3.00']);
    $this->bacon->optionGroups()->attach($size->id, ['display_order' => 1]);

    $page = ($this->page)()->call('pick', $this->bacon->id)->call('toggleOption', $size->id, $cheese->id)->call('confirmChoose');
    $key = $page->get('cart')[0]['key'];

    $page->call('stepLine', $key, 1)->assertSee('R$ 63,80');
    $page->call('editLine', $key)->assertSet('showChoose', true)->call('toggleOption', $size->id, $cheese->id)->call('confirmChoose')->assertSee('R$ 57,80');
    $page->call('stepLine', $key, -1)->call('stepLine', $key, -1)->assertSet('cart', []);

    $page->call('pick', $this->coca->id)->call('removeLine', $page->get('cart')[0]['key'])->assertSet('cart', []);
    $page->call('pick', $this->coca->id)->set('discount', '1')->call('clear')->assertSet('cart', [])->assertSet('discount', '');
});

it('shows a problem instead of a price when a product stops being sellable', function () {
    $page = ($this->page)()->call('pick', $this->coca->id);
    $this->coca->update(['status_id' => DishStatus::idFor('oculto')]);

    $page->call('$refresh')->assertSee('não está disponível');
});

it('never sells a product of another store', function () {
    $foreign = Dish::factory()->create();

    ($this->page)()->call('pick', $foreign->id)->assertNotFound();
});
