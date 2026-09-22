<?php

use App\Models\Dish;
use App\Models\DishView;
use App\Models\Plan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;

    $this->burger = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Burger Clássico']);
    $this->costela = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Costela 12h']);

    foreach ([[$this->burger, 's1', 10], [$this->burger, 's2', 20], [$this->costela, 's1', 6]] as [$dish, $session, $seconds]) {
        DishView::create(['dish_id' => $dish->id, 'restaurant_id' => $this->restaurant->id, 'session_token' => $session, 'seconds_watched' => $seconds, 'viewed_on' => today()]);
    }
    // Outside the default 30-day window.
    DishView::create(['dish_id' => $this->costela->id, 'restaurant_id' => $this->restaurant->id, 'session_token' => 'old', 'seconds_watched' => 99, 'viewed_on' => today()->subDays(45)]);

    $this->actingAs($this->dono);
});

test('a free plan restaurant only sees the cardapio total metric', function () {
    expect($this->restaurant->plan->metricsLevel->slug)->toBe('cardapio_total');

    Livewire::test('pages::panel.views')
        ->assertSeeHtml('data-metrics-level="cardapio_total"')
        ->assertSeeInOrder(['Visualizações de pratos', '3', 'Visitas ao cardápio', '2'])
        ->assertDontSeeHtml('data-per-dish')
        ->assertDontSee('Burger Clássico')
        ->assertDontSee('Tempo médio');
});

test('a pro plan restaurant sees per dish view count and average watch time', function () {
    $this->restaurant->update(['plan_id' => Plan::where('name', 'Pro')->value('id')]);

    Livewire::test('pages::panel.views')
        ->assertSeeHtml('data-per-dish')
        ->assertSeeInOrder(['Burger Clássico', '2', '15,0s', 'Costela 12h', '1', '6,0s']);
});

it('shows per dish counts without watch time on the basico plan', function () {
    $this->restaurant->update(['plan_id' => Plan::where('name', 'Básico')->value('id')]);

    Livewire::test('pages::panel.views')
        ->assertSee('Burger Clássico')
        ->assertDontSee('Tempo médio');
});
