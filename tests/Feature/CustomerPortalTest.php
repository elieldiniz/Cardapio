<?php

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationLedgerType;
use App\Models\User;
use Livewire\Livewire;
use Tests\Fakes\FakeStripe;

afterEach(fn () => FakeStripe::uninstall());

it('shows the current plan and month to date usage and opens the stripe customer portal', function () {
    seedReferenceData();
    $stripe = FakeStripe::install();
    $dono = User::factory()->create();
    $restaurant = $dono->restaurant;
    $restaurant->update(['stripe_id' => 'cus_123']);
    GenerationBalance::create(['restaurant_id' => $restaurant->id, 'monthly_balance' => 0, 'addon_balance' => 3]);

    foreach ([-1, -1] as $quantity) {
        GenerationLedger::create(['restaurant_id' => $restaurant->id, 'type_id' => GenerationLedgerType::idFor('uso'), 'quantity' => $quantity]);
    }
    // Last month's usage does not count.
    GenerationLedger::create(['restaurant_id' => $restaurant->id, 'type_id' => GenerationLedgerType::idFor('uso'), 'quantity' => -1])
        ->forceFill(['created_at' => now()->subMonth()])->save();

    Livewire::actingAs($dono)
        ->test('pages::panel.subscription')
        ->assertSeeInOrder(['Plano atual', 'Grátis', 'Gerações usadas no mês', '2'])
        ->call('openPortal')
        ->assertRedirect('https://billing.stripe.test/session');

    expect($stripe->requestsTo('/v1/billing_portal/sessions'))->toHaveCount(1);
});
