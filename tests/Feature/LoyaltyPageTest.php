<?php

use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-18, UI-01 — the "Fidelidade" screen.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('saves the program and shows it again on reopening', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.loyalty')
        ->set('is_active', true)->set('stamps_required', '8')->set('reward_type', 'percentual')->set('reward_value', '15')->set('min_order', '30,00')
        ->call('save')->assertHasNoErrors();

    // A fresh user, as in a new request (the relation of the first one is cached as empty).
    Livewire::actingAs(User::find($this->dono->id))->test('pages::panel.loyalty')
        ->assertSet('is_active', true)
        ->assertSet('stamps_required', '8')
        ->assertSet('reward_value', '15')
        ->assertSet('min_order', '30,00')
        ->assertSee('Complete 8 pedidos e ganhe 15% de desconto.');
});

it('rejects an invalid program', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.loyalty')
        ->set('stamps_required', '1')->set('reward_type', 'brinde')->set('reward_label', '')
        ->call('save')->assertHasErrors(['stamps_required', 'reward_label']);

    expect(LoyaltyProgram::count())->toBe(0);
});

it('lists complete cards and redeems one only from its own store', function () {
    $program = LoyaltyProgram::create(['restaurant_id' => $this->store->id, 'is_active' => true, 'stamps_required' => 2, 'reward_type' => 'brinde', 'reward_label' => 'Sobremesa']);
    $customer = Customer::create(['restaurant_id' => $this->store->id, 'name' => 'Ana Souza', 'whatsapp' => '5569999990000']);
    $card = LoyaltyCard::create(['loyalty_program_id' => $program->id, 'customer_id' => $customer->id, 'stamps' => 2, 'completed_at' => now()]);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.loyalty')->assertSee('Ana Souza')->assertSee('Sobremesa');
    $page->call('redeem', $card->id)->assertDontSee('Ana Souza');

    expect($card->fresh()->redeemed_at)->not->toBeNull();
});

it('does not redeem a card of another store', function () {
    $other = User::factory()->create();
    $program = LoyaltyProgram::create(['restaurant_id' => $other->restaurant_id, 'is_active' => true, 'stamps_required' => 2, 'reward_type' => 'brinde', 'reward_label' => 'X']);
    $customer = Customer::create(['restaurant_id' => $other->restaurant_id, 'name' => 'Bia', 'whatsapp' => '5569999991111']);
    $card = LoyaltyCard::create(['loyalty_program_id' => $program->id, 'customer_id' => $customer->id, 'stamps' => 2, 'completed_at' => now()]);

    Livewire::actingAs($this->dono)->test('pages::panel.loyalty')->call('redeem', $card->id)->assertNotFound();

    expect($card->fresh()->redeemed_at)->toBeNull();
});

it('is reachable from the menu', function () {
    $this->actingAs($this->dono)->get(route('panel.loyalty'))->assertOk()->assertSee('Fidelidade');
});
