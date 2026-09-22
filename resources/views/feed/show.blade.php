{{--
    Client feed (US-1.1–US-1.4). Plain Blade + vanilla JS — deliberately no
    Livewire on this route so scrolling stays fluid on low-end phones.

    Expects:
      $restaurant       array{name, slug, logo_url, accent, font, show_branding}
      $categories       array<array{id, name, url}>  (url = category-switch endpoint)
      $activeCategoryId int|null
      $dishes           array<FeedDish>  dishes of the active category, first one embedded for first paint
      $trackUrl         string|null      watch-time endpoint (Phase 14.3)
--}}
@php
    $first = $dishes[0] ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $restaurant['accent'] }};@if ($restaurant['font'] ?? null) --feed-font: {{ $restaurant['font'] }};@endif">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101010">
    <title>{{ $restaurant['name'] }} · Cardápio</title>

    {{-- Warm up the video CDN before the first <video> is parsed (US-1.1: video < 2s on 4G). --}}
    <link rel="preconnect" href="{{ \App\Support\MuxUrls::STREAM_ORIGIN }}" crossorigin>
    <link rel="preconnect" href="{{ \App\Support\MuxUrls::IMAGE_ORIGIN }}" crossorigin>
    @if ($first)
        @if ($first['cover_url'])
            <link rel="preload" as="image" href="{{ $first['cover_url'] }}" fetchpriority="high">
        @endif
    @endif

    @fonts
    @vite(['resources/css/feed.css', 'resources/js/feed.js'])
</head>
<body>
    <div
        class="feed-app"
        data-feed
        data-sw-url="{{ asset('feed-sw.js') }}"
        data-sw-scope="{{ url('/r').'/' }}"
        @if ($trackUrl ?? null) data-track-url="{{ $trackUrl }}" @endif
    >
        <header class="feed-header">
            <div class="feed-brand">
                @if ($restaurant['logo_url'])
                    <img class="feed-brand__logo" src="{{ $restaurant['logo_url'] }}" alt="" width="34" height="34">
                @else
                    <span class="feed-brand__logo feed-brand__logo--initial">{{ mb_substr($restaurant['name'], 0, 1) }}</span>
                @endif
                <span class="feed-brand__name">{{ $restaurant['name'] }}</span>
            </div>

            @include('feed.partials.category-bar', ['categories' => $categories, 'activeCategoryId' => $activeCategoryId])
        </header>

        <main class="feed" data-feed-list aria-label="Pratos">
            @forelse ($dishes as $index => $dish)
                @include('feed.partials.dish', ['dish' => $dish, 'eager' => $index === 0])
            @empty
                <section class="feed-empty">
                    <p>O cardápio ainda está sendo preparado.</p>
                </section>
            @endforelse
        </main>

        <div class="feed-actions">
            <button type="button" class="feed-action" data-sound-toggle aria-pressed="false" aria-label="Ativar som">
                <svg class="icon-muted" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                <svg class="icon-unmuted" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 010 7.07"/><path d="M19.07 4.93a10 10 0 010 14.14"/></svg>
            </button>
            <button type="button" class="feed-action" data-detail-open aria-label="Ver detalhes do prato">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="11"/><circle cx="12" cy="7.5" r="0.6" fill="#fff" stroke="none"/></svg>
            </button>
        </div>

        <div class="feed-hint" data-feed-hint hidden>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
            <span>deslize para navegar</span>
        </div>

        @include('feed.partials.detail-sheet')

        @if ($restaurant['show_branding'])
            <a class="feed-branding" href="{{ url('/') }}" target="_blank" rel="noopener">feito com <strong>{{ config('app.name') }}</strong></a>
        @endif
    </div>

    {{-- Blueprint used by feed.js to render dishes returned by the category-switch endpoint. --}}
    <template id="dish-template">
        @include('feed.partials.dish', ['dish' => null, 'eager' => false])
    </template>
</body>
</html>
