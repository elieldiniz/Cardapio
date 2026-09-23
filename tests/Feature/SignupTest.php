<?php

use App\Models\User;
use Database\Seeders\MetricsLevelSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RestaurantStatusSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, RestaurantStatusSeeder::class, MetricsLevelSeeder::class, PlanSeeder::class]);
});

test('signing up creates a restaurant on the free plan with five one time generations', function () {
    Livewire::test('pages::auth.register')
        ->set('name', 'Ana Souza')
        ->set('restaurant_name', 'Fumaça')
        ->set('email', 'ana@fumaca.com')
        ->set('password', 'segredo123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'ana@fumaca.com')->firstOrFail();
    $restaurant = $user->restaurant;

    expect($user->isOwner())->toBeTrue()
        ->and($restaurant->name)->toBe('Fumaça')
        ->and($restaurant->slug)->toBe('fumaca')
        ->and($restaurant->status->slug)->toBe('ativo')
        ->and($restaurant->plan->name)->toBe('Grátis')
        ->and($restaurant->generationBalance->addon_balance)->toBe(5)
        ->and($restaurant->generationBalance->monthly_balance)->toBe(0);

    $this->assertAuthenticatedAs($user);
});

test('signup fails with a duplicate email', function () {
    User::factory()->create(['email' => 'ana@fumaca.com']);

    Livewire::test('pages::auth.register')
        ->set('name', 'Ana Souza')
        ->set('restaurant_name', 'Fumaça')
        ->set('email', 'ana@fumaca.com')
        ->set('password', 'segredo123')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::where('email', 'ana@fumaca.com')->count())->toBe(1);
});

test('signup requires a password of at least 8 characters', function () {
    Livewire::test('pages::auth.register')
        ->set('name', 'Ana Souza')
        ->set('restaurant_name', 'Fumaça')
        ->set('email', 'ana@fumaca.com')
        ->set('password', 'curta')
        ->call('register')
        ->assertHasErrors('password');

    $this->get(route('register'))->assertSee('minlength="8"', false);
});
