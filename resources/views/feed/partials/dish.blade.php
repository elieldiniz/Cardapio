{{--
    One dish per screen. Rendered server-side for first paint, and cloned by
    feed.js from <template id="dish-template"> (with $dish = null) when the
    category changes — every dynamic node carries a data-field hook.
--}}
@php
    $dish ??= null;
    $soldOut = (bool) ($dish['sold_out'] ?? false);
@endphp
<section
    class="dish{{ $soldOut ? ' dish--sold-out' : '' }}"
    data-dish
    data-dish-id="{{ $dish['id'] ?? '' }}"
    data-video-src="{{ $dish['video_url'] ?? '' }}"
    data-video-src-hd="{{ $dish['video_url_hd'] ?? '' }}"
>
    <video
        class="dish__video"
        data-field="video"
        muted
        loop
        playsinline
        disablepictureinpicture
        @if ($dish['cover_url'] ?? null) poster="{{ $dish['cover_url'] }}" @endif
        @if ($eager && ($dish['video_url'] ?? null))
            src="{{ $dish['video_url'] }}"
            autoplay
            preload="auto"
        @else
            preload="none"
        @endif
    ></video>

    <div class="dish__shade" aria-hidden="true"></div>

    <div class="dish__info">
        <div class="dish__badges" data-field="badges">
            @foreach ($dish['badges'] ?? [] as $badge)
                <span class="badge">{{ $badge }}</span>
            @endforeach
            @if ($soldOut)
                <span class="badge badge--sold-out">Esgotado</span>
            @endif
        </div>
        <div class="dish__title">
            <h2 class="dish__name" data-field="name">{{ $dish['name'] ?? '' }}</h2>
            <span class="dish__price" data-field="price">{{ $dish['price'] ?? '' }}</span>
        </div>
        <p class="dish__short" data-field="short_description">{{ $dish['short_description'] ?? '' }}</p>
        <button type="button" class="dish__more" data-detail-open>mais</button>
    </div>

    {{-- Full detail, copied into the bottom sheet when opened (US-1.4). --}}
    <div class="dish__detail" data-field="detail" hidden>
        <p data-field="description">{{ $dish['description'] ?? '' }}</p>
        <ul data-field="variants">
            @foreach ($dish['variants'] ?? [] as $variant)
                <li><span>{{ $variant['name'] }}</span><span>{{ $variant['price'] }}</span></li>
            @endforeach
        </ul>
    </div>
</section>
