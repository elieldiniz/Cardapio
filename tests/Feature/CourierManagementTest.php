<?php

use App\Models\Courier;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-22 — courier registry and link revocation.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('creates and edits a courier with a link of its own', function () {
    $page = Livewire::actingAs($this->dono)->test('pages::panel.couriers')
        ->call('create')->set('name', 'João Silva')->set('phone', '(69) 99999-1111')->set('fee', '6,00')->call('save')->assertHasNoErrors();

    $courier = Courier::sole();

    expect($courier)->name->toBe('João Silva')->phone->toBe('5569999991111')->fee_per_delivery->toBe('6.00')->and(strlen($courier->token))->toBe(32);

    $page->call('edit', $courier->id)->set('fee', '7,50')->call('save')->assertHasNoErrors();

    expect($courier->fresh()->fee_per_delivery)->toBe('7.50');
});

it('refuses an empty name and a broken phone', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.couriers')->call('create')->set('name', '')->set('phone', '123')->call('save')->assertHasErrors(['name', 'phone']);

    expect(Courier::count())->toBe(0);
});

it('revokes the link and makes a new one that replaces the old', function () {
    $courier = Courier::create(['restaurant_id' => $this->store->id, 'name' => 'João']);
    $old = $courier->token;
    $page = Livewire::actingAs($this->dono)->test('pages::panel.couriers');

    $page->call('revoke', $courier->id);
    expect($courier->fresh()->linkIsValid())->toBeFalse();

    $page->call('regenerate', $courier->id);
    expect($courier->fresh())->token->not->toBe($old)->and($courier->fresh()->linkIsValid())->toBeTrue();
});

it('does not touch the couriers of another store', function () {
    $other = Courier::create(['restaurant_id' => User::factory()->create()->restaurant_id, 'name' => 'Alheio']);

    Livewire::actingAs($this->dono)->test('pages::panel.couriers')->call('revoke', $other->id)->assertNotFound();

    expect($other->fresh()->revoked_at)->toBeNull();
});

it('is reachable from the menu', function () {
    $this->actingAs($this->dono)->get(route('panel.couriers'))->assertOk()->assertSee('Motoboys');
});

it('renders the copy-link button with a clipboard fallback for pages that are not served over HTTPS', function () {
    $courier = Courier::create(['restaurant_id' => $this->store->id, 'name' => 'João']);

    Livewire::actingAs($this->dono)->test('pages::panel.couriers')
        ->assertSeeHtml('data-copy-link')
        ->assertSeeHtml('copyText(')
        ->assertDontSeeHtml('navigator.clipboard.writeText');
});
