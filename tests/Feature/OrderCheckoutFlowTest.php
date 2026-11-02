<?php

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;

/**
 * RF-25, RNF-05, RNF-06, RNF-07 — the whole customer journey, from the feed to the tracking page.
 */
it('takes an order from the feed to the tracking page', function () {
    seedReferenceData();
    $restaurant = User::factory()->create()->restaurant;
    RestaurantOrderSetting::factory()->create([
        'restaurant_id' => $restaurant->id, 'whatsapp_number' => '5569996041447', 'pix_key' => 'chave-pix',
        'delivery_mode' => 'bairro', 'free_delivery_over' => '80.00', 'min_order_delivery' => '25.00',
    ]);
    $centro = DeliveryZone::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Centro', 'fee' => '5.00']);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id, 'icon' => '🍔']);
    $dish = feedDish($restaurant, ['name' => 'X-Bacon', 'price' => '28.90'], $category);
    $size = OptionGroup::factory()->required()->create(['restaurant_id' => $restaurant->id, 'name' => 'Tamanho']);
    $medium = Option::factory()->create(['option_group_id' => $size->id, 'name' => 'Médio', 'price' => '5.00']);
    $extras = OptionGroup::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Adicionais', 'allow_repeat' => true, 'max_choices' => 5]);
    $bacon = Option::factory()->create(['option_group_id' => $extras->id, 'name' => 'Bacon', 'price' => '4.00']);
    $dish->optionGroups()->attach([$size->id => ['display_order' => 1], $extras->id => ['display_order' => 2]]);

    // 1. The feed carries the catalog the cart prices from.
    $config = orderConfigOf($this->get(route('feed.show', $restaurant))->assertOk());
    expect($config['dishes'][(string) $dish->id]['groups'])->toHaveCount(2);

    // 2. The checkout creates the order, priced by the server.
    $created = $this->postJson($config['orders_url'], [
        'items' => [['dish_id' => $dish->id, 'quantity' => 2, 'notes' => 'Sem cebola', 'options' => [['id' => $medium->id, 'quantity' => 1], ['id' => $bacon->id, 'quantity' => 2]]]],
        'fulfillment' => 'entrega',
        'customer' => ['name' => 'Ana Souza', 'phone' => '(69) 99999-0000'],
        'address' => ['street' => 'Rua das Flores', 'number' => '120', 'zone_id' => $centro->id],
        'payment_method' => 'dinheiro',
        'change_for' => '200,00',
        'origin' => 'instagram',
    ])->assertCreated();

    // (28.90 + 5.00 + 2×4.00) × 2 = 83.80, above R$ 80 → free delivery.
    expect($created->json('total'))->toBe('83.80')->and($created->json('delivery_fee'))->toBe('0.00');

    $order = Order::query()->where('code', $created->json('code'))->firstOrFail();

    expect($order->origin)->toBe('instagram')->and($order->change_for)->toBe('200.00');

    // 3. The WhatsApp events.
    $eventUrl = str_replace('__TOKEN__', $order->tracking_token, $config['whatsapp_url']);
    $this->postJson($eventUrl, ['event' => 'opened'])->assertNoContent();
    $this->postJson($eventUrl, ['event' => 'confirmed'])->assertNoContent();

    expect($order->fresh()->whatsapp_opened_at)->not->toBeNull()->and($order->fresh()->customer_confirmed_at)->not->toBeNull();

    // 4. The customer's tracking page, without personal data.
    $this->get($created->json('tracking_url'))->assertOk()->assertSee($order->code)->assertSee('Olá, Ana!')->assertDontSee('Souza')->assertDontSee('99999-0000');
    $this->getJson(route('order.track.status', $order->tracking_token))->assertOk()->assertJsonPath('status', 'novo');

    // 5. The WhatsApp message the customer sends carries the same link and the choices.
    expect($created->json('message'))->toContain($created->json('tracking_url'))->toContain('Adicionais: 2× Bacon')->toContain('troco para R$ 200,00');
});

it('keeps the cardápio free of the ordering code when the store takes no orders', function () {
    seedReferenceData();
    $restaurant = User::factory()->create()->restaurant;
    feedDish($restaurant);

    $html = $this->get(route('feed.show', $restaurant))->assertOk()->getContent();

    expect($html)->not->toContain('data-order-config')->and($html)->not->toContain('cart.js');
});
