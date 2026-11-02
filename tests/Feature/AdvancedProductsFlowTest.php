<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Menu\SavePizza;
use App\Models\Category;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-25, RF-14, RF-15 — a half-and-half pizza and a weighed product, from the feed to the manager.
 */
it('closes the right value for a half-and-half pizza and a weighed product, in the feed and in the manager', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $store = $dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $store->id, 'whatsapp_number' => '5569996041447']);
    $category = Category::factory()->create(['restaurant_id' => $store->id]);
    $pizza = feedDish($store, ['name' => 'Pizza'], $category);
    app(SavePizza::class)->handle($pizza, [['name' => 'Grande', 'slices' => 8, 'max_flavors' => 2]], [
        ['name' => 'Mussarela', 'prices' => ['40,00']],
        ['name' => 'Calabresa', 'prices' => ['60,00']],
    ]);
    $pizza = $pizza->fresh(['pizzaSizes', 'pizzaFlavors']);
    $torta = feedDish($store, ['name' => 'Torta', 'price' => '60.00', 'sale_type' => 'kg'], $category);
    app(OpenCashSession::class)->handle($store, '0', $dono);

    $created = $this->postJson(route('feed.orders.store', $store), [
        'items' => [
            ['dish_id' => $pizza->id, 'quantity' => 1, 'size_id' => $pizza->pizzaSizes->first()->id, 'flavor_ids' => $pizza->pizzaFlavors->pluck('id')->all()],
            ['dish_id' => $torta->id, 'quantity' => 1.5],
        ],
        'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'],
        'payment_method' => 'pix',
    ])->assertCreated();

    // Pizza by the most expensive flavor (60) + 1.5 kg of torta (90) = 150.
    expect($created->json('total'))->toBe('150.00')
        ->and($created->json('message'))->toContain('Sabores: ½ Mussarela, ½ Calabresa')->toContain('1,5 kg');

    $order = Order::query()->where('code', $created->json('code'))->firstOrFail();
    $page = Livewire::actingAs($dono)->test('pages::panel.orders');

    $page->assertSee('Tamanho: Grande (8 fatias)')->assertSee('Sabores: ½ Mussarela, ½ Calabresa');

    // After weighing, the torta came to 1.42 kg: the dono adjusts the product value (60 + 85,20).
    $page->call('accept', $order->id)->call('openAdjust', $order->id)->set('adjustProducts', '145,20')->call('confirmAdjust')->assertHasNoErrors();

    expect($order->fresh())->total->toBe('145.20')->original_total->toBe('150.00');

    $page->call('advance', $order->id, 'em_rota')->call('openFinalize', $order->id)->set('payMode', 'pix')->assertSee('R$ 145,20')->call('confirmPayment')->assertHasNoErrors();

    expect($order->fresh()->status->slug)->toBe('finalizado')->and($order->payments()->sole()->amount)->toBe('145.20');
});
