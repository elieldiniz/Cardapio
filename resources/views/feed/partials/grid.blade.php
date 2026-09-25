{{--
    Opening grid (mockup "Cardápio Fumaça - Feed"): restaurant header, category
    filter and every dish; tapping one opens the video feed on that dish.
--}}
<section class="grid-view" data-grid aria-label="Cardápio">
    <header class="grid-hero">
        <div class="grid-hero__cover" data-brand-cover @if ($restaurant['cover_url']) style="background-image: url('{{ $restaurant['cover_url'] }}')" @endif></div>
        <div class="grid-hero__shade" aria-hidden="true"></div>
    </header>

    {{-- Profile header: logo over the cover, name, accent rule and description. --}}
    <div class="grid-intro">
        <img class="grid-hero__logo" data-brand-logo src="{{ $restaurant['logo_url'] }}" alt="" width="88" height="88" @unless ($restaurant['logo_url']) hidden @endunless>
        <span class="grid-hero__logo grid-hero__logo--initial" data-brand-initial @if ($restaurant['logo_url']) hidden @endif>{{ mb_substr($restaurant['name'], 0, 1) }}</span>

        <h1 class="grid-intro__name">{{ $restaurant['name'] }}</h1>
        <span class="grid-intro__rule" aria-hidden="true"></span>

        <div class="grid-intro__about" data-brand-about @unless ($restaurant['description']) hidden @endunless>
            <p class="grid-intro__description" data-brand-description>{{ $restaurant['description'] }}</p>
            <button type="button" class="grid-intro__more" data-description-toggle hidden>ver mais</button>
        </div>
    </div>

    <div class="grid-divider" aria-hidden="true"></div>

    <nav class="grid-filters" data-grid-filters aria-label="Filtrar por categoria">
        <button type="button" class="grid-filter" data-grid-filter="all" aria-pressed="true">Todos</button>
        @foreach ($categories as $category)
            <button type="button" class="grid-filter" data-grid-filter="{{ $category['id'] }}" aria-pressed="false">{{ $category['name'] }}</button>
        @endforeach
    </nav>

    @if ($grid === [])
        <p class="grid-empty">O cardápio ainda está sendo preparado.</p>
    @else
        <ul class="grid-items">
            @foreach ($grid as $item)
                <li class="grid-item" data-grid-item data-category="{{ $item['category_id'] }}">
                    <button type="button" class="grid-item__button" data-open-dish="{{ $item['id'] }}" data-category="{{ $item['category_id'] }}">
                        @if ($item['thumb_url'])
                            <img class="grid-item__image" src="{{ $item['thumb_url'] }}" alt="" loading="{{ $loop->index < 6 ? 'eager' : 'lazy' }}" @if ($loop->first) fetchpriority="high" @endif>
                        @endif
                        @if ($item['sold_out'])
                            <span class="grid-item__sold-out">Esgotado</span>
                        @endif
                        <span class="grid-item__name">{{ $item['name'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($restaurant['show_branding'])
        <a class="grid-branding" href="{{ url('/') }}" target="_blank" rel="noopener">feito com <strong>{{ config('app.name') }}</strong></a>
    @endif
</section>
