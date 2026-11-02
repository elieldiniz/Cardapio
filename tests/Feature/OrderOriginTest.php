<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-20 — where an order came from.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->place = fn (array $extra = []) => $this->postJson(route('feed.orders.store', $this->store), array_merge([
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
    ], $extra))->assertCreated();
});

it('records a known origin and the campaign', function () {
    ($this->place)(['origin' => 'Instagram', 'campaign' => 'sextou']);

    expect(Order::sole())->origin->toBe('instagram')->campaign->toBe('sextou');
});

it('records "direto" when there is no origin and "outro" for an unknown one', function () {
    ($this->place)();
    ($this->place)(['origin' => 'tiktok-bio']);

    expect(Order::orderBy('id')->pluck('origin')->all())->toBe(['direto', 'outro']);
});

it('offers tracked links per channel on the QR code screen', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.qr-code')
        ->set('campaign', 'Dia das Mães')
        ->assertSee('origem=instagram&amp;campanha=dia-das-maes', false)
        ->assertSee('Anúncio pago');
});
