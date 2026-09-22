<?php

use App\Support\MuxUrls;

it('renders the feed shell as plain server-side html without livewire', function () {
    $html = $this->get(route('dev.feed'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-feed')
        ->toContain('data-feed-list')
        ->not->toContain('wire:')
        ->not->toContain('livewire');
});

it('embeds the first dish server side with an autoplaying muted looping inline video', function () {
    $html = $this->get(route('dev.feed'))->getContent();

    expect($html)->toContain('Burger Clássico')
        ->and(preg_match('/<video[^>]*\bmuted\b[^>]*\bloop\b[^>]*\bplaysinline\b/s', $html))->toBe(1)
        ->and($html)->toContain('data-sound-toggle')
        ->toContain('R$ 32,90');
});

it('renders the fixed category bar, detail sheet, and dish template', function () {
    $this->get(route('dev.feed'))
        ->assertSee('data-category-bar', false)
        ->assertSeeInOrder(['Burgers', 'Fumados', 'Bebidas'])
        ->assertSee('data-sheet', false)
        ->assertSee('id="dish-template"', false);
});

it('preconnects to the mux cdn and registers the feed service worker', function () {
    $this->get(route('dev.feed'))
        ->assertSee('<link rel="preconnect" href="'.MuxUrls::STREAM_ORIGIN.'" crossorigin>', false)
        ->assertSee('data-sw-url="'.asset('feed-sw.js').'"', false);

    expect(file_exists(public_path('feed-sw.js')))->toBeTrue();
});

it('points feed videos at mux mp4 renditions', function () {
    expect(MuxUrls::mp4('abc123'))->toBe('https://stream.mux.com/abc123/480p.mp4')
        ->and(MuxUrls::mp4('abc123', '720p'))->toBe('https://stream.mux.com/abc123/720p.mp4');
});
