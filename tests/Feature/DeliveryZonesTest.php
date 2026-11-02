<?php

use App\Actions\Settings\SaveDeliveryZones;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-06b, RNF-01, RNF-02 — delivery neighborhoods, fees and rules.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
});

it('saves and reads zones and rules through Money::parse', function () {
    app(SaveDeliveryZones::class)->handle($this->restaurant, [
        ['name' => 'Centro', 'fee' => 'R$ 5,00'],
        ['name' => 'Jardim América', 'fee' => '7'],
        ['name' => 'Vila Nova', 'fee' => '8,50', 'is_active' => false],
    ], ['delivery_mode' => 'bairro', 'free_delivery_over' => '80,00', 'min_order_delivery' => '25', 'accepts_other_neighborhood' => false]);

    $zones = $this->restaurant->deliveryZones()->get();

    expect($zones->pluck('name')->all())->toBe(['Centro', 'Jardim América', 'Vila Nova'])
        ->and($zones->pluck('fee')->all())->toBe(['5.00', '7.00', '8.50'])
        ->and($zones[2]->is_active)->toBeFalse();

    expect($this->restaurant->fresh()->orderSettings())
        ->delivery_mode->toBe('bairro')
        ->free_delivery_over->toBe('80.00')
        ->min_order_delivery->toBe('25.00')
        ->accepts_other_neighborhood->toBeFalse();
});

it('keeps the zone ids when editing and deletes the ones left out', function () {
    $centro = DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Centro']);
    $gone = DeliveryZone::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Industrial']);

    app(SaveDeliveryZones::class)->handle($this->restaurant, [
        ['id' => $centro->id, 'name' => 'Centro histórico', 'fee' => '6,00'],
    ], ['delivery_mode' => 'bairro']);

    expect($centro->fresh())->name->toBe('Centro histórico')->fee->toBe('6.00')
        ->and(DeliveryZone::query()->whereKey($gone->id)->exists())->toBeFalse();
});

it('rejects a duplicate neighborhood, an invalid fee and a foreign zone id', function () {
    expect(fn () => app(SaveDeliveryZones::class)->handle($this->restaurant, [
        ['name' => 'Centro', 'fee' => '5'], ['name' => 'centro', 'fee' => '6'],
    ], ['delivery_mode' => 'bairro']))->toThrow(ValidationException::class);

    expect(fn () => app(SaveDeliveryZones::class)->handle($this->restaurant, [
        ['name' => 'Centro', 'fee' => 'cinco'],
    ], ['delivery_mode' => 'bairro']))->toThrow(ValidationException::class);

    $foreign = DeliveryZone::factory()->create();

    app(SaveDeliveryZones::class)->handle($this->restaurant, [
        ['id' => $foreign->id, 'name' => 'Intruso', 'fee' => '1'],
    ], ['delivery_mode' => 'bairro']);

    expect($foreign->fresh()->name)->not->toBe('Intruso')
        ->and($this->restaurant->deliveryZones()->where('name', 'Intruso')->exists())->toBeTrue();
});

it('saves the delivery section from the settings screen and reopens with it', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->call('addZone')
        ->set('zones.0.name', 'Centro')
        ->set('zones.0.fee', '5,00')
        ->set('free_delivery_over', '80,00')
        ->call('saveDelivery')
        ->assertHasNoErrors();

    Livewire::actingAs($this->dono->fresh())->test('pages::panel.settings')
        ->assertSet('delivery_mode', 'bairro')
        ->assertSet('zones.0.name', 'Centro')
        ->assertSet('zones.0.fee', '5,00')
        ->assertSet('free_delivery_over', '80,00');
});

it('shows the zone error on the settings screen', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->call('addZone')
        ->set('zones.0.name', 'Centro')
        ->set('zones.0.fee', 'abc')
        ->call('saveDelivery')
        ->assertHasErrors('zones.0.fee');
});
