<?php

use App\Models\PromoCode;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-17 — the "Cupons" screen of the panel.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('creates a code in upper case with its limits', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.promo-codes')
        ->call('create')
        ->set('code', 'bem-vindo 10')->set('type', 'percentual')->set('value', '10')->set('min_order', '40,00')->set('max_uses', '50')->set('ends_on', '2030-12-31')
        ->call('save')->assertHasNoErrors();

    expect(PromoCode::sole())->code->toBe('BEM-VINDO10')->value->toBe('10.00')->min_order->toBe('40.00')->max_uses->toBe(50)->is_active->toBeTrue();
});

it('refuses a repeated code, a percentage above 100 and an end before the start', function () {
    PromoCode::create(['restaurant_id' => $this->store->id, 'code' => 'JAEXISTE', 'type' => 'valor', 'value' => '5.00']);

    Livewire::actingAs($this->dono)->test('pages::panel.promo-codes')->call('create')
        ->set('code', 'jaexiste')->set('value', '150')->set('starts_on', '2030-02-01')->set('ends_on', '2030-01-01')
        ->call('save')->assertHasErrors(['code', 'value', 'ends_on']);

    expect(PromoCode::count())->toBe(1);
});

it('edits, pauses and deletes only the own codes', function () {
    $mine = PromoCode::create(['restaurant_id' => $this->store->id, 'code' => 'MEU', 'type' => 'valor', 'value' => '5.00']);
    $other = PromoCode::create(['restaurant_id' => User::factory()->create()->restaurant_id, 'code' => 'OUTRO', 'type' => 'valor', 'value' => '5.00']);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.promo-codes');

    $page->call('edit', $mine->id)->set('value', '7,50')->call('save')->assertHasNoErrors();
    expect($mine->fresh()->value)->toBe('7.50');

    $page->call('toggle', $mine->id);
    expect($mine->fresh()->is_active)->toBeFalse();

    $page->call('delete', $mine->id);
    expect(PromoCode::pluck('code')->all())->toBe(['OUTRO']);

    Livewire::actingAs($this->dono)->test('pages::panel.promo-codes')->call('delete', $other->id)->assertNotFound();
    expect(PromoCode::find($other->id))->not->toBeNull();
});

it('is reachable from the menu', function () {
    $this->actingAs($this->dono)->get(route('panel.promo-codes'))->assertOk()->assertSee('Cupons');
});
