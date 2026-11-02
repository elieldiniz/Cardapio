{{--
    The customer's order tracking page (CT-03, RF-07, RF-07a, UI-02). Plain Blade + vanilla
    JS, like the cardápio: no Livewire. It shows only what the token holder may see — first
    name, partial address, items and total — and refreshes itself by polling the status URL.

    Expects: $tracking (TrackingPresenter), $restaurant {name, accent, font, font_slug, url, whatsapp_url}, $statusUrl.
--}}
<!DOCTYPE html>
<html lang="pt-BR" data-menu-style="{{ $restaurant['style'] ?? 'vidro' }}" style="--accent: {{ $restaurant['accent'] }}; --feed-display-font: '{{ $restaurant['font'] }}';">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#101010">
    <title>Pedido {{ $tracking['code'] }} · {{ $restaurant['name'] }}</title>
    @if ($restaurant['font_slug'])
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $restaurant['font_slug'] }}&display=swap">
    @endif
    @fonts
    @vite(['resources/css/feed.css', 'resources/js/track.js'])
</head>
<body class="track-body">
    <main class="track" data-track data-status-url="{{ $statusUrl }}" data-ended="{{ $tracking['ended'] ? 'true' : 'false' }}">
        <header class="track__header">
            <span class="track__store">{{ $restaurant['name'] }}</span>
            <h1 class="track__code">Pedido {{ $tracking['code'] }}</h1>
            @if ($tracking['first_name'])
                <p class="track__hello">Olá, {{ $tracking['first_name'] }}!</p>
            @endif
        </header>

        <section class="track__card track__headline" data-tone="{{ $tracking['bad'] ? 'bad' : 'ok' }}" data-track-headline>
            {{ $tracking['headline'] }}
        </section>

        @unless ($tracking['bad'])
            <ol class="track__steps" data-track-steps aria-label="Andamento do pedido">
                @foreach ($tracking['steps'] as $step)
                    <li class="track__step{{ $step['done'] ? ' is-done' : '' }}{{ $step['current'] ? ' is-current' : '' }}">
                        <span class="track__dot" aria-hidden="true"></span>
                        <span class="track__step-label">{{ $step['label'] }}</span>
                        <span class="track__step-time">{{ $step['at'] }}</span>
                    </li>
                @endforeach
            </ol>
        @endunless

        <section class="track__card">
            <h2 class="track__title">Seu pedido</h2>
            <ul class="track__items">
                @foreach ($tracking['items'] as $item)
                    <li>
                        <span class="track__qty">{{ $item['quantity'] }}×</span>
                        <span class="track__item-body">
                            <strong>{{ $item['name'] }}</strong>
                            @foreach ($item['choices'] as $choice)
                                <small>{{ $choice }}</small>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>

            <dl class="track__facts">
                @if ($tracking['address'])
                    <div><dt>Entrega em</dt><dd>{{ $tracking['address'] }}</dd></div>
                @elseif ($tracking['fulfillment'] === 'retirada')
                    <div><dt>Recebimento</dt><dd>Retirada na loja</dd></div>
                @endif
                <div><dt>Total</dt><dd>{{ $tracking['total'] }}@if ($tracking['fee_pending']) <small>(entrega a combinar)</small>@endif</dd></div>
            </dl>
        </section>

        <p class="track__refresh" data-track-refresh>Esta página atualiza sozinha.</p>

        <nav class="track__actions">
            @if ($restaurant['whatsapp_url'])
                <a class="track__button" href="{{ $restaurant['whatsapp_url'] }}" target="_blank" rel="noopener">Falar com a loja</a>
            @endif
            <a class="track__button track__button--ghost" href="{{ $restaurant['url'] }}">Ver cardápio</a>
        </nav>
    </main>
</body>
</html>
