<?php

use App\Models\User;
use App\Support\PanelNavigation;

/**
 * RF-02d, RF-25, UI-01 — grouped menu, "Produtos" label and plan gating.
 */
beforeEach(function () {
    seedReferenceData();
});

it('defines exactly five groups', function () {
    expect(array_keys(PanelNavigation::groupDefinitions()))->toBe(['Geral', 'Cardápio', 'Pedidos', 'Crescimento', 'Conta']);
});

it('renders the groups in the panel and only entries whose screen exists', function () {
    $dono = User::factory()->create();

    $response = $this->actingAs($dono)->get(route('panel.home'))->assertOk();

    $response->assertSeeHtml('data-nav-group')
        ->assertSee('Geral')
        ->assertSee('Cardápio')
        ->assertSee('Produtos')
        ->assertSee('Configurações');
});

it('never shows the word Pratos in a panel screen', function () {
    $dono = User::factory()->create();

    foreach (['panel.home', 'panel.dishes', 'panel.dishes.create', 'panel.categories', 'panel.views', 'panel.subscription', 'panel.settings'] as $name) {
        $this->actingAs($dono)->get(route($name))->assertOk()->assertDontSee('Pratos');
    }
});

it('keeps the dish routes working', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)->get('/painel/pratos')->assertOk();
    $this->actingAs($dono)->get('/painel/pratos/novo')->assertOk();
});

it('keeps entries whose feature the plan switched off, locked and pointing to the upgrade page', function () {
    $dono = User::factory()->create();

    $before = collect(PanelNavigation::groups())->flatMap(fn ($group) => $group['items'])->pluck('route');

    $dono->restaurant->plan->update(['features' => ['cash' => false]]);

    endTrial($dono->restaurant);
    $this->actingAs($dono->fresh());

    $after = collect(PanelNavigation::groups())->flatMap(fn ($group) => $group['items'])->pluck('route');

    $cash = collect(PanelNavigation::groups())->flatMap(fn ($group) => $group['items'])->firstWhere('route', 'panel.cash');

    expect($after->contains('panel.dishes'))->toBeTrue()
        ->and($cash['locked'])->toBeTrue()
        ->and($cash['url'])->toBe(route('panel.locked', 'cash'))
        ->and($before->count())->toBe($after->count());
});
