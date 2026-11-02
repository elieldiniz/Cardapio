<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\ApplyPromoCode;
use App\Actions\RegisterRestaurantOwner;
use App\Exceptions\OrderRejectedException;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\PanelNavigation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * 14-day trial of the free plan: everything on, then the commercial matrix. Nothing is deleted.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('starts a 14-day trial at signup', function () {
    $this->travelTo(now()->startOfDay());

    $user = app(RegisterRestaurantOwner::class)->handle(['name' => 'Ana', 'restaurant_name' => 'Casa da Ana', 'email' => 'ana@example.test', 'password' => 'senha-segura-123']);
    $restaurant = $user->restaurant->fresh();

    expect($restaurant->trial_ends_at->toDateString())->toBe(now()->addDays(14)->toDateString())->and($restaurant->onTrial())->toBeTrue()->and($restaurant->trialDaysLeft())->toBe(14);
});

it('has every feature and the highest metrics level during the trial, whatever the plan switches say', function () {
    foreach (['orders', 'cash', 'pdv', 'promo_codes', 'loyalty', 'couriers'] as $feature) {
        expect($this->store->hasFeature($feature))->toBeTrue();
    }

    expect($this->store->metricsLevelSlug())->toBe('por_prato_com_tempo_assistido');
});

it('falls back to the free plan matrix on day 15', function () {
    $this->store->update(['trial_ends_at' => now()->addDays(14)]);
    $this->travelTo(now()->addDays(14)->addMinute());

    $store = $this->store->fresh();

    expect($store->onTrial())->toBeFalse()->and($store->trialEnded())->toBeTrue()
        ->and($store->hasFeature('orders'))->toBeTrue()
        ->and($store->hasFeature('cash'))->toBeFalse()->and($store->hasFeature('pdv'))->toBeFalse()->and($store->hasFeature('promo_codes'))->toBeFalse()
        ->and($store->hasFeature('loyalty'))->toBeFalse()->and($store->hasFeature('couriers'))->toBeFalse()
        ->and($store->metricsLevelSlug())->toBe('cardapio_total');
});

it('never gives a trial to a paid plan, nor a new one after subscribing and cancelling', function () {
    $this->store->update(['plan_id' => Plan::where('name', 'Básico')->value('id'), 'trial_ends_at' => now()->addDays(10)]);
    $store = $this->store->fresh();

    expect($store->onTrial())->toBeFalse()->and($store->hasFeature('loyalty'))->toBeFalse()->and($store->hasFeature('cash'))->toBeTrue();

    $this->store->update(['plan_id' => Plan::where('name', 'Grátis')->value('id'), 'trial_ends_at' => now()->subDays(30)]);

    expect($this->store->fresh()->onTrial())->toBeFalse();
});

it('shows the trial banner with the days left and the ended notice afterwards', function () {
    $this->store->update(['trial_ends_at' => now()->addDays(5)->addHour()]);
    $this->actingAs($this->dono->fresh())->get(route('panel.home'))->assertSee('data-trial-banner', false)->assertSee('faltam 6 dias');

    endTrial($this->store);
    $this->actingAs($this->dono->fresh())->get(route('panel.home'))->assertSee('data-trial-ended-banner', false)->assertSee('Seu teste de 14 dias terminou');
});

it('sends to the upgrade page, saying which plan has the feature, and keeps the data', function () {
    PromoCode::create(['restaurant_id' => $this->store->id, 'code' => 'GUARDADO', 'type' => 'valor', 'value' => '5.00']);
    endTrial($this->store);

    $this->actingAs($this->dono->fresh())->get(route('panel.promo-codes'))->assertRedirect(route('panel.locked', 'promo_codes'));
    $this->actingAs($this->dono->fresh())->get(route('panel.locked', 'promo_codes'))->assertOk()->assertSee('Cupons de desconto')->assertSee('plano')->assertSee('Básico');
    $this->actingAs($this->dono->fresh())->get(route('panel.locked', 'loyalty'))->assertOk()->assertSee('Pro');

    expect(PromoCode::where('code', 'GUARDADO')->exists())->toBeTrue();
    $this->actingAs($this->dono)->get(route('panel.locked', 'nao-existe'))->assertNotFound();
});

it('keeps orders, products and the cardápio working after the trial', function () {
    endTrial($this->store);

    foreach (['panel.orders', 'panel.dishes', 'panel.customers', 'panel.settings', 'panel.appearance'] as $route) {
        $this->actingAs($this->dono->fresh())->get(route($route))->assertOk();
    }
});

it('stops accepting discount codes, stamps and new caixa after the trial', function () {
    PromoCode::create(['restaurant_id' => $this->store->id, 'code' => 'BEMVINDO10', 'type' => 'percentual', 'value' => '10.00']);
    endTrial($this->store);
    $store = $this->store->fresh();

    expect(fn () => app(ApplyPromoCode::class)->check($store, 'BEMVINDO10', 5000))->toThrow(OrderRejectedException::class);
    expect(fn () => app(OpenCashSession::class)->handle($store, '0'))->toThrow(ValidationException::class);
});

it('still lets the owner close an open caixa after the trial', function () {
    $session = app(OpenCashSession::class)->handle($this->store, '0');
    endTrial($this->store);

    $this->actingAs($this->dono->fresh())->get(route('panel.cash'))->assertOk();
    $this->actingAs($this->dono->fresh())->get(route('panel.pdv'))->assertRedirect(route('panel.locked', 'pdv'));

    expect($session->fresh()->closed_at)->toBeNull();
});

it('lets the admin extend the trial of a free restaurant by 7 days, from today when it already ended', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    endTrial($this->store);

    Livewire::test(ListRestaurants::class)->callAction(TestAction::make('extendTrial')->table($this->store->fresh()));

    $store = $this->store->fresh();

    expect($store->onTrial())->toBeTrue()->and($store->trial_ends_at->toDateString())->toBe(now()->addDays(7)->toDateString());
});

it('keeps the caixa menu entries open while there is a caixa or fiado to wrap up', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    endTrial($this->store);
    $this->actingAs($this->dono->fresh());

    $cash = collect(PanelNavigation::groups())->flatMap(fn ($group) => $group['items'])->firstWhere('route', 'panel.cash');

    expect($cash['locked'])->toBeFalse()->and($cash['url'])->toBe(route('panel.cash'));
});
