<?php

use App\Models\Category;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use App\Services\Panel\OnboardingProgress;
use Livewire\Livewire;

/**
 * RF-24 — the 9-step setup guide.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
});

it('lists nine steps and starts at the beginning', function () {
    $guide = app(OnboardingProgress::class)->for($this->store);

    expect($guide['steps'])->toHaveCount(9)->and($guide['total'])->toBe(9)->and($guide['done'])->toBe(0)->and($guide['percent'])->toBe(0)->and($guide['next']['key'])->toBe('whatsapp');
});

it('computes the percentage as completed steps over nine and moves to the next step', function () {
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447', 'pix_key' => 'loja@pix.com']);
    $category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], $category);

    $guide = app(OnboardingProgress::class)->for($this->store->fresh());
    $done = collect($guide['steps'])->where('done', true)->pluck('key')->all();

    expect($done)->toContain('whatsapp', 'pix', 'category', 'dish')
        ->and($guide['percent'])->toBe((int) round(count($done) / 9 * 100))
        ->and($guide['next']['done'])->toBeFalse();
});

it('shows the guide with its percentage on the home screen', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.home')->assertSeeHtml('data-guide')->assertSeeHtml('data-guide-step="whatsapp"')->assertSee('0% · 0 de 9');
});
