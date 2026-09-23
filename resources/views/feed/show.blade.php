{{--
    Client feed (US-1.1–US-1.4). Plain Blade + vanilla JS — deliberately no
    Livewire on this route so scrolling stays fluid on low-end phones.

    The page opens on the grid of every dish; tapping one opens the vertical
    video feed on it, and "voltar" returns to the grid.

    Expects:
      $restaurant       array{name, description, slug, logo_url, cover_url, accent, font, show_branding}
      $categories       array<array{id, name, url}>  (url = category-switch endpoint)
      $activeCategoryId int|null
      $dishes           array<FeedDish>  dishes of the first category, pre-rendered so opening one needs no request
      $grid             array<array{id, category_id, name, sold_out, thumb_url}>  every feed-eligible dish
      $trackUrl         string|null      watch-time endpoint (Phase 14.3)
      $preview          bool             appearance live preview inside the panel (Phase 16)
--}}
@php
    $firstThumb = $restaurant['cover_url'] ?? ($grid[0]['thumb_url'] ?? null);
    $preview ??= false;
    $fonts = config('feed.fonts');
    $font = array_key_exists($restaurant['font'] ?? '', $fonts) ? $restaurant['font'] : config('feed.default_font');
@endphp
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $restaurant['accent'] }}; --feed-display-font: '{{ $font }}';">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101010">
    <title>{{ $restaurant['name'] }} · Cardápio</title>

    {{-- Warm up the video CDN so a tapped dish starts playing fast (US-1.1: video < 2s on 4G). --}}
    <link rel="preconnect" href="{{ \App\Support\MuxUrls::STREAM_ORIGIN }}" crossorigin>
    <link rel="preconnect" href="{{ \App\Support\MuxUrls::IMAGE_ORIGIN }}" crossorigin>
    @if ($firstThumb)
        <link rel="preload" as="image" href="{{ $firstThumb }}" fetchpriority="high">
    @endif

    @if ($preview)
        {{-- Every selectable font, so the live preview can switch instantly. --}}
        @foreach (array_filter($fonts) as $bunnyFamily)
            <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $bunnyFamily }}&display=swap">
        @endforeach
    @elseif ($fonts[$font] ?? null)
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $fonts[$font] }}&display=swap">
    @endif

    @fonts
    @vite(['resources/css/feed.css', 'resources/js/feed.js'])
</head>
<body>
    <div
        class="feed-app"
        data-feed
        data-view="grid"
        @if ($preview)
            data-preview
        @else
            data-sw-url="{{ asset('feed-sw.js') }}"
            data-sw-scope="{{ url('/r').'/' }}"
            @if ($trackUrl ?? null) data-track-url="{{ $trackUrl }}" @endif
        @endif
    >
        @include('feed.partials.grid')

        <header class="feed-header">
            <div class="feed-brand">
                <button type="button" class="feed-back" data-back-to-grid aria-label="Voltar para o cardápio">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <span class="feed-brand__name">{{ $restaurant['name'] }}</span>
            </div>

            @include('feed.partials.category-bar', ['categories' => $categories, 'activeCategoryId' => $activeCategoryId])
        </header>

        <main class="feed" data-feed-list data-category="{{ $activeCategoryId }}" aria-label="Pratos">
            {{-- Nothing plays until a dish is tapped in the grid, so no video is fetched eagerly. --}}
            @foreach ($dishes as $dish)
                @include('feed.partials.dish', ['dish' => $dish, 'eager' => false])
            @endforeach
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
