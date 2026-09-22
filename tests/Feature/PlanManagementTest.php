<?php

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\AdminLog;
use App\Models\GenerationBalance;
use App\Models\MetricsLevel;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('editing a plans limits does not retroactively change already granted balances', function () {
    $basico = Plan::where('name', 'Básico')->firstOrFail();
    $restaurant = Restaurant::factory()->create(['plan_id' => $basico->id]);
    GenerationBalance::create(['restaurant_id' => $restaurant->id, 'monthly_balance' => 30, 'addon_balance' => 2]);

    Livewire::test(EditPlan::class, ['record' => $basico->getRouteKey()])
        ->fillForm(['monthly_generations' => 50, 'dish_limit' => 80, 'price_cents' => 7900, 'stripe_price_id' => 'price_basico_v2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($basico->fresh())
        ->monthly_generations->toBe(50)
        ->dish_limit->toBe(80)
        ->stripe_price_id->toBe('price_basico_v2');

    $balance = GenerationBalance::where('restaurant_id', $restaurant->id)->first();
    expect($balance->monthly_balance)->toBe(30)->and($balance->addon_balance)->toBe(2);

    expect(AdminLog::with('action')->sole()->action->slug)->toBe('plano_atualizado');
});

it('creates a plan with limits features and a stripe price', function () {
    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => 'Premium',
            'price_cents' => 19900,
            'stripe_price_id' => 'price_premium',
            'monthly_generations' => 200,
            'initial_generations' => 0,
            'dish_limit' => null,
            'removes_branding' => true,
            'metrics_level_id' => MetricsLevel::idFor('por_prato_com_tempo_assistido'),
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::where('name', 'Premium')->first())
        ->price_cents->toBe(19900)
        ->dish_limit->toBeNull()
        ->removes_branding->toBeTrue();

    Livewire::test(ListPlans::class)->assertSee('Premium')->assertSee('R$ 199,00');
});
