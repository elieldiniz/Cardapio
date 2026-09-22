<?php

use App\Models\Restaurant;
use App\Models\User;

it('renders the panel shell with the restaurant name and accent color for a dono', function () {
    $restaurant = Restaurant::factory()->create(['name' => 'Fumaça', 'accent_color' => '#2E9E6B']);
    $dono = User::factory()->create(['restaurant_id' => $restaurant->id]);

    $this->actingAs($dono)
        ->get(route('panel.home'))
        ->assertOk()
        ->assertSee('Olá, Fumaça')
        ->assertSee('--accent: #2E9E6B', false)
        ->assertSee('data-panel-nav', false);
});

it('exposes every navigation entry from the mockup and highlights the active one', function () {
    $dono = User::factory()->create();

    $response = $this->actingAs($dono)->get(route('panel.home'))->assertOk();

    foreach (['Início', 'Categorias', 'Pratos', 'Aparência', 'QR Code', 'Visualizações', 'Assinatura'] as $label) {
        $response->assertSee($label);
    }

    $response->assertSeeInOrder(['aria-current="page"', 'Início'], false);
});

it('does not render the panel for a super admin', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('panel.home'))->assertForbidden();
});

it('shows the home summary cards with real counts', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)
        ->get(route('panel.home'))
        ->assertSeeInOrder(['Pratos ativos', 'Esgotados', 'Categorias', 'Visualizações hoje']);
});

it('documents every shared component on the dev preview route', function () {
    $response = $this->get(route('dev.components'))->assertOk();

    foreach (['button', 'card', 'form-field', 'modal', 'toast', 'states'] as $component) {
        $response->assertSee('data-component="'.$component.'"', false);
    }
});
