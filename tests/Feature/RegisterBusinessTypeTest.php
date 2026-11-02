<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * RF-02c — the business type chosen at signup creates the starter categories.
 */
beforeEach(function () {
    seedReferenceData();
    Notification::fake();
});

function registerOwner(array $overrides = []): void
{
    Livewire::test('pages::auth.register')
        ->set('name', 'Maria Silva')
        ->set('restaurant_name', 'Pizzaria da Maria')
        ->set('email', 'maria@example.com')
        ->set('password', 'senha-segura-123')
        ->set('business_type', $overrides['business_type'] ?? '')
        ->call('register')
        ->assertHasNoErrors();
}

it('creates the starter categories of a pizzaria', function () {
    registerOwner(['business_type' => 'pizzaria']);

    $restaurant = User::query()->where('email', 'maria@example.com')->firstOrFail()->restaurant;

    expect($restaurant->business_type)->toBe('pizzaria')
        ->and($restaurant->categories()->pluck('name')->all())->toContain('Pizzas', 'Bebidas')
        ->and($restaurant->categories()->where('name', 'Pizzas')->value('icon'))->toBe('🍕');
});

it('creates no category when no business type is chosen', function () {
    registerOwner();

    $restaurant = User::query()->where('email', 'maria@example.com')->firstOrFail()->restaurant;

    expect($restaurant->business_type)->toBeNull()
        ->and($restaurant->categories()->count())->toBe(0);
});

it('rejects a business type that is not offered', function () {
    Livewire::test('pages::auth.register')
        ->set('name', 'Maria Silva')
        ->set('restaurant_name', 'Loja')
        ->set('email', 'maria@example.com')
        ->set('password', 'senha-segura-123')
        ->set('business_type', 'cassino')
        ->call('register')
        ->assertHasErrors('business_type');

    expect(User::query()->where('email', 'maria@example.com')->exists())->toBeFalse();
});
