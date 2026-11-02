<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Entregas · {{ $courier->restaurant->name }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f1ec; color: #1c1b19; }
        main { max-width: 520px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        h1 { font-size: 20px; margin: 4px 0 0; } p { margin: 0; }
        .card { background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08); display: flex; flex-direction: column; gap: 6px; }
        .done { opacity: .6; } .muted { color: #6b665f; font-size: 13px; }
        .total { font-weight: 800; font-size: 17px; }
        .addr { display: flex; gap: 6px; align-items: flex-start; } .addr svg { flex: none; margin-top: 2px; }
        button, .map { display: block; width: 100%; box-sizing: border-box; text-align: center; border: 0; border-radius: 12px; padding: 13px; font-size: 15px; font-weight: 700; text-decoration: none; }
        button { background: #1c1b19; color: #fff; } .map { background: #e8e3da; color: #1c1b19; }
    </style>
</head>
<body>
<main>
    <h1>Olá, {{ $courier->firstName() }}!</h1>
    <p class="muted">{{ $courier->restaurant->name }} · suas entregas de hoje</p>

    @forelse ($orders as $order)
        @php
            $first = \Illuminate\Support\Str::before(trim((string) $order->customer_name), ' ');
            $address = $order->addressLine();
            $pay = $order->payments->isNotEmpty() ? 'Já pago' : match ($order->paymentMethod?->slug) {
                'dinheiro' => 'Cobrar em dinheiro'.($order->change_for ? ' · troco para R$ '.number_format((float) $order->change_for, 2, ',', '.') : ''),
                'pix' => 'Pix · não cobrar, a loja confirma',
                default => 'Cobrar na maquininha',
            };
        @endphp
        <article class="card {{ $order->delivered_at ? 'done' : '' }}" data-courier-order="{{ $order->code }}">
            <p class="muted">Pedido {{ $order->code }}</p>
            <p><strong>{{ $first ?: 'Cliente' }}</strong></p>
            @if ($address)
                <p class="addr"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> {{ $address }}</p>
            @endif
            @if ($order->address_reference) <p class="muted">{{ $order->address_reference }}</p> @endif
            <p class="total">R$ {{ number_format((float) $order->total, 2, ',', '.') }} <span class="muted">· {{ $pay }}</span></p>
            @if ($address)
                <a class="map" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($address) }}" target="_blank" rel="noopener">Abrir no mapa</a>
            @endif
            @if ($order->delivered_at)
                <p class="muted">✓ Entregue</p>
            @else
                <form method="post" action="{{ route('courier.deliver', [$token, $order->id]) }}" onsubmit="return confirm('Confirmar que o pedido {{ $order->code }} foi entregue?')">@csrf<button type="submit">Marcar como entregue</button></form>
            @endif
        </article>
    @empty
        <div class="card"><p>Nenhuma entrega com você agora.</p><p class="muted">Quando a loja passar um pedido para você, ele aparece aqui. Atualize a página.</p></div>
    @endforelse
</main>
</body>
</html>
