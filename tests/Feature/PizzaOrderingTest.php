<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Menu\SavePizza;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-14, RF-14a, RF-05 — a pizza through the whole ordering flow.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->settings = RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    $this->pizza = feedDish($this->store, ['name' => 'Pizza'], $category);
    app(SavePizza::class)->handle($this->pizza, [
        ['name' => 'Broto', 'slices' => 4, 'max_flavors' => 1],
        ['name' => 'Grande', 'slices' => 8, 'max_flavors' => 3],
    ], [
        ['name' => 'Calabresa', 'prices' => ['30,00', '40,00']],
        ['name' => 'Portuguesa', 'prices' => ['', '60,00']],
    ]);
    $this->pizza = $this->pizza->fresh(['pizzaSizes', 'pizzaFlavors']);
});

function pizzaPayload(Dish $pizza, string $size, array $flavors, array $extra = []): array
{
    return array_merge([
        'items' => [[
            'dish_id' => $pizza->id, 'quantity' => 1,
            'size_id' => $pizza->pizzaSizes->firstWhere('name', $size)->id,
            'flavor_ids' => collect($flavors)->map(fn ($name) => $pizza->pizzaFlavors->firstWhere('name', $name)->id)->all(),
        ]],
        'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'],
        'payment_method' => 'pix',
    ], $extra);
}

it('takes a half-and-half pizza order priced by the most expensive flavor, with the choices in the message', function () {
    $response = $this->postJson(route('feed.orders.store', $this->store), pizzaPayload($this->pizza, 'Grande', ['Calabresa', 'Portuguesa']))->assertCreated();

    expect($response->json('total'))->toBe('60.00')
        ->and($response->json('message'))->toContain('Tamanho: Grande (8 fatias)')->toContain('Sabores: ½ Calabresa, ½ Portuguesa');

    $item = Order::sole()->items->sole();

    expect($item->selections['pizza']['size']['name'])->toBe('Grande')->and($item->selections['pizza']['flavors'])->toHaveCount(2);
});

it('prices by the average when the store chooses it', function () {
    $this->settings->update(['pizza_pricing_rule' => 'media']);

    $this->postJson(route('feed.orders.store', $this->store), pizzaPayload($this->pizza, 'Grande', ['Calabresa', 'Portuguesa']))->assertCreated()->assertJsonPath('total', '50.00');
});

it('rejects too many flavors, a missing size and a flavor missing in the size, creating nothing', function () {
    $this->postJson(route('feed.orders.store', $this->store), pizzaPayload($this->pizza, 'Broto', ['Calabresa', 'Portuguesa']))->assertUnprocessable();
    $this->postJson(route('feed.orders.store', $this->store), pizzaPayload($this->pizza, 'Broto', ['Portuguesa']))->assertUnprocessable();

    $payload = pizzaPayload($this->pizza, 'Grande', ['Calabresa']);
    $payload['items'][0]['size_id'] = null;
    $this->postJson(route('feed.orders.store', $this->store), $payload)->assertUnprocessable();

    expect(Order::count())->toBe(0);
});

it('embeds the pizza in the feed catalog with sizes, flavors, prices per size and the rule', function () {
    $config = orderConfigOf($this->get(route('feed.show', $this->store))->assertOk());
    $entry = $config['dishes'][(string) $this->pizza->id];

    expect($config['pizza_rule'])->toBe('maior')
        ->and($entry['kind'])->toBe('pizza')
        ->and(collect($entry['sizes'])->pluck('name')->all())->toBe(['Broto', 'Grande'])
        ->and(collect($entry['sizes'])->pluck('max')->all())->toBe([1, 3])
        ->and(collect($entry['flavors'])->pluck('name')->all())->toBe(['Calabresa', 'Portuguesa'])
        ->and($entry['orderable'])->toBeTrue();

    $calabresa = collect($entry['flavors'])->firstWhere('name', 'Calabresa');
    $portuguesa = collect($entry['flavors'])->firstWhere('name', 'Portuguesa');
    $broto = collect($entry['sizes'])->firstWhere('name', 'Broto')['id'];

    expect($calabresa['prices'])->toHaveCount(2)->and($portuguesa['prices'])->toHaveCount(1)->and(isset($portuguesa['prices'][$broto]))->toBeFalse();
});

it('saves the pizza rule from the settings screen', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.settings')->assertSet('pizza_pricing_rule', 'maior')->set('pizza_pricing_rule', 'media')->call('savePizzaRule')->assertHasNoErrors();

    expect($this->store->fresh()->orderSettings()->pizza_pricing_rule)->toBe('media');

    Livewire::actingAs($this->dono)->test('pages::panel.settings')->set('pizza_pricing_rule', 'barato')->call('savePizzaRule')->assertHasErrors('pizza_pricing_rule');
});

it('sells a pizza at the counter', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    $size = $this->pizza->pizzaSizes->firstWhere('name', 'Grande');

    $page = Livewire::actingAs($this->dono)->test('pages::panel.pdv')->call('pick', $this->pizza->id)->assertSet('showChoose', true)->assertSee('Tamanho');

    $page->call('confirmChoose')->assertHasErrors('choose');

    $page->call('pickSize', $size->id)->call('toggleFlavor', $this->pizza->pizzaFlavors->firstWhere('name', 'Calabresa')->id)->call('toggleFlavor', $this->pizza->pizzaFlavors->firstWhere('name', 'Portuguesa')->id)
        ->call('confirmChoose')->assertHasNoErrors()->assertSee('R$ 60,00')
        ->call('charge')->set('payMode', 'pix')->call('finishSale')->assertHasNoErrors();

    expect(Order::sole())->total->toBe('60.00')->and(Order::sole()->items->sole()->selections['pizza']['flavors'])->toHaveCount(2);
});

it('drops flavors that do not exist in the new size when the size changes at the counter', function () {
    $broto = $this->pizza->pizzaSizes->firstWhere('name', 'Broto');
    $grande = $this->pizza->pizzaSizes->firstWhere('name', 'Grande');
    $portuguesa = $this->pizza->pizzaFlavors->firstWhere('name', 'Portuguesa');

    Livewire::actingAs($this->dono)->test('pages::panel.pdv')
        ->call('pick', $this->pizza->id)->call('pickSize', $grande->id)->call('toggleFlavor', $portuguesa->id)->assertSet('draftFlavors', [$portuguesa->id])
        ->call('pickSize', $broto->id)->assertSet('draftFlavors', []);
});

it('shows the pizza in the order manager with its choices', function () {
    $this->postJson(route('feed.orders.store', $this->store), pizzaPayload($this->pizza, 'Grande', ['Calabresa', 'Portuguesa']))->assertCreated();

    Livewire::actingAs($this->dono)->test('pages::panel.orders')->assertSee('Tamanho: Grande (8 fatias)')->assertSee('Sabores: ½ Calabresa, ½ Portuguesa');
});
