<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * RF-23 — the 58/80 mm kitchen ticket.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 2, 'notes' => 'sem cebola']], 'fulfillment' => 'entrega',
        'address' => ['street' => 'Rua das Flores', 'number' => '120', 'neighborhood' => 'Centro', 'reference' => 'portão azul'],
        'customer' => ['name' => 'Ana Souza', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix', 'notes' => 'tocar a campainha',
    ])->assertCreated();
    $this->order = Order::sole();
});

it('prints the code, items, notes, address and totals', function () {
    $this->actingAs($this->dono)->get(route('panel.ticket', $this->order))->assertOk()
        ->assertSee($this->order->code)->assertSee('X-Bacon')->assertSee('OBS: sem cebola')->assertSee('tocar a campainha')
        ->assertSee('Rua das Flores')->assertSee('portão azul')->assertSee('ENTREGA')->assertSee('TOTAL');
});

it('does not print the order of another store', function () {
    $this->actingAs(User::factory()->create())->get(route('panel.ticket', $this->order))->assertForbidden();
});

it('offers a test ticket and keeps the printer options out of the database', function () {
    $this->actingAs($this->dono)->get(route('panel.ticket.sample'))->assertOk()->assertSee('COMANDA DE TESTE')->assertSee('data-ticket-setting="width"', false);

    foreach (['restaurants', 'restaurant_order_settings', 'users'] as $table) {
        expect(collect(Schema::getColumnListing($table))->filter(fn ($column) => str_contains($column, 'ticket') || str_contains($column, 'print')))->toBeEmpty();
    }
});
