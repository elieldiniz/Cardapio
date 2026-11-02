<?php

use App\Models\User;

/**
 * RF-24 — installable panel and the phone's "Mais" menu.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
});

it('ships a valid manifest scoped to the panel', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')->and($manifest['scope'])->toBe('/painel/')->and($manifest['start_url'])->toStartWith('/painel')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }
});

it('has a service worker that never caches panel data', function () {
    $worker = file_get_contents(public_path('panel-sw.js'));

    expect($worker)->not->toContain('caches.open')->and($worker)->not->toContain('respondWith');
});

it('links the manifest and shows the bottom bar with "Mais" in the panel layout', function () {
    $this->actingAs($this->dono)->get(route('panel.home'))->assertOk()
        ->assertSee('rel="manifest"', false)->assertSee('data-bottom-bar', false)->assertSee('data-more-menu', false)->assertSee('Mais');
});
