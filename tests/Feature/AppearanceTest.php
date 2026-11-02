<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $this->actingAs($this->dono);
});

it('saves logo accent color and font that the public feed then renders', function () {
    Storage::fake('public');
    feedDish($this->restaurant);

    Livewire::test('pages::panel.appearance')
        ->set('logo', fakeImage('logo.png'))
        ->set('accent_color', '#3e7bfa')
        ->set('font', 'Playfair Display')
        ->call('save')
        ->assertHasNoErrors();

    $restaurant = $this->restaurant->fresh();
    Storage::disk('public')->assertExists($restaurant->logo_path);

    $this->get(route('feed.show', $restaurant))
        ->assertSee('--accent: #3E7BFA', false)
        ->assertSee("--feed-display-font: 'Playfair Display'", false)
        ->assertSee('fonts.bunny.net/css?family=playfair-display', false)
        ->assertSee(Storage::disk('public')->url($restaurant->logo_path), false);
});

it('rejects an unknown font or a malformed color', function () {
    Livewire::test('pages::panel.appearance')
        ->set('accent_color', 'red')
        ->set('font', 'Comic Sans')
        ->call('save')
        ->assertHasErrors(['accent_color', 'font']);
});

it('renders the real feed shell as a live preview without tracking', function () {
    $response = $this->get(route('panel.appearance.preview'))->assertOk();

    expect($response->getContent())
        ->toContain('data-preview')
        ->toContain('data-feed-list')
        ->not->toContain('data-track-url')
        ->not->toContain('data-sw-url');

    Livewire::test('pages::panel.appearance')->assertSeeHtml('data-appearance-preview');
});

it('defaults to the glass style and applies the chosen style to the feed and the tracking page', function () {
    feedDish($this->restaurant);

    expect($this->restaurant->menuStyle())->toBe('vidro');
    $this->get(route('feed.show', $this->restaurant))->assertSee('data-menu-style="vidro"', false);

    Livewire::test('pages::panel.appearance')->assertSet('menu_style', 'vidro')->set('menu_style', 'rustico')->call('save')->assertHasNoErrors();

    expect($this->restaurant->fresh()->menu_style)->toBe('rustico');
    $this->get(route('feed.show', $this->restaurant->fresh()))->assertSee('data-menu-style="rustico"', false);
});

it('rejects an unknown menu style and falls back to glass for a stored one nobody knows', function () {
    Livewire::test('pages::panel.appearance')->set('menu_style', 'neon')->call('save')->assertHasErrors('menu_style');

    $this->restaurant->forceFill(['menu_style' => 'neon'])->save();

    expect($this->restaurant->fresh()->menuStyle())->toBe('vidro');
});

it('offers the three styles and sends the choice to the live preview', function () {
    Livewire::test('pages::panel.appearance')->assertSeeHtml('data-menu-style="vidro"')->assertSeeHtml('data-menu-style="claro"')->assertSeeHtml('data-menu-style="rustico"')->assertSeeHtml('menuStyle: $wire.menu_style');
});
