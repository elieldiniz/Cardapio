{{--
    The table display card (A6). One source for the print page and the live preview in the panel.
    Expects: $restaurant, $svg (the QR), $settings (RestaurantQr::settings), $table (string).
--}}
@php
    $accent = \App\Support\RestaurantQr::accent($restaurant, $settings);
    // Card background and letter color: the dono's choices, or the model's own. The letters always stay readable.
    $bg = \App\Support\RestaurantQr::background($restaurant, $settings);
    $fg = \App\Support\RestaurantQr::textColor($restaurant, $settings);
    $fontFamily = $settings['display_font'] ? "'".$settings['display_font']."'" : null;
    // The table tag uses the main color, unless the card itself is that color (then it is white).
    $tagOnAccent = strcasecmp($bg, $accent) !== 0;
    // Our orange symbol would vanish on a similar background: it turns monochrome there.
    $brandFilter = \App\Support\QrStyle::contrast($bg, '#F26A21') < 2.2
        ? ($fg === '#FFFFFF' ? 'brightness(0) invert(1)' : 'brightness(0)')
        : 'none';
    $logoUrl = $restaurant->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($restaurant->logo_path) : null;
@endphp
<div class="td td--{{ $settings['template'] }}" style="--td-accent: {{ $accent }}; --td-on-accent: {{ \App\Support\QrStyle::onColor($accent) }}; --td-bg: {{ $bg }}; --td-fg: {{ $fg }}; --td-tag-bg: {{ $tagOnAccent ? $accent : '#FFFFFF' }}; --td-tag-fg: {{ $tagOnAccent ? \App\Support\QrStyle::onColor($accent) : '#1A1815' }}; --td-brand-filter: {{ $brandFilter }}@if ($fontFamily); --td-font: {!! $fontFamily !!}@endif" data-table-display data-template="{{ $settings['template'] }}">
    @if ($settings['show_logo'] && $logoUrl)
        <img class="td-logo" src="{{ $logoUrl }}" alt="">
    @endif
    @if ($settings['show_name'])
        <h1 class="td-name">{{ $restaurant->name }}</h1>
    @endif
    <div class="td-qr" data-qr>{!! $svg !!}</div>
    <p class="td-cta">{{ $settings['headline'] }}</p>
    @if ($settings['subtitle'] !== '')
        <p class="td-sub">{{ $settings['subtitle'] }}</p>
    @endif
    @if ($table !== '')
        <span class="td-table">Mesa {{ $table }}</span>
    @endif
    {{-- Our logo and name, small and quiet, like in the site header. On every plan. --}}
    <div class="td-brand" data-td-brand>
        <img src="{{ asset('images/brand/symbol.png') }}" alt="">
        <span>{{ config('app.name') }}</span>
    </div>
</div>
