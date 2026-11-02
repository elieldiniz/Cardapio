@php
    use App\Services\Orders\SelectionDescriber;
    use App\Support\Money;

    $timezone = $restaurant->orderSettings()->timezone;
    $placed = $order->created_at?->copy()->setTimezone($timezone);
    $delivery = $order->isDelivery();
    $method = $order->paymentMethod?->name;
    $line = fn (string $left, string $right) => '<div class="row"><span>'.e($left).'</span><b>'.e($right).'</b></div>';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Comanda {{ $order->code }}</title>
    @vite('resources/js/ticket-print.js')
    <style>
        :root { --ticket-width: 80mm; --ticket-font: 13px; }
        body { margin: 0; background: #ececec; font-family: ui-monospace, 'Courier New', monospace; color: #000; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px 14px; background: #fff; border-bottom: 1px solid #ddd; font: 14px system-ui, sans-serif; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 8px; background: #111; color: #fff; font-weight: 700; cursor: pointer; }
        .toolbar select { padding: 6px; }
        .ticket { width: var(--ticket-width); box-sizing: border-box; margin: 16px auto; padding: 4mm 3mm; background: #fff; font-size: var(--ticket-font); line-height: 1.35; }
        .ticket h1 { font-size: 1.25em; margin: 0; text-align: center; } .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 6px; } hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .item { margin: 4px 0; } .sub { padding-left: 8px; font-size: .92em; } .big { font-size: 1.3em; font-weight: 800; }
        @media print { body { background: #fff; } .toolbar { display: none; } .ticket { margin: 0; padding: 2mm; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" data-ticket-print>Imprimir</button>
    <label>Largura <select data-ticket-setting="width"><option value="58">58 mm</option><option value="80">80 mm</option></select></label>
    <label>Letra <select data-ticket-setting="font"><option value="11">Pequena</option><option value="13">Média</option><option value="15">Grande</option></select></label>
    <label><input type="checkbox" data-ticket-setting="auto"> Imprimir ao abrir</label>
    <span>Essas escolhas ficam salvas só neste aparelho.</span>
</div>

<main class="ticket" data-ticket="{{ $sample ? 'sample' : 'order' }}">
    <h1>{{ $restaurant->name }}</h1>
    <p class="center">{{ $sample ? 'COMANDA DE TESTE' : 'Pedido' }} <span class="big">{{ $order->code }}</span></p>
    <p class="center">{{ $placed?->format('d/m/Y H:i') }} · {{ $delivery ? 'ENTREGA' : ($order->fulfillment === 'balcao' ? 'BALCÃO' : 'RETIRADA') }}</p>
    <hr>
    <p><b>{{ $order->customer_name ?? 'Cliente' }}</b>@if ($order->customer_phone) · {{ preg_replace('/^55(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $order->customer_phone) }}@endif</p>
    @if ($delivery && $order->addressLine())
        <p data-ticket-address>{{ $order->addressLine() }}@if ($order->address_complement), {{ $order->address_complement }}@endif</p>
        @if ($order->address_reference) <p>Ref.: {{ $order->address_reference }}</p> @endif
    @endif
    <hr>
    @foreach ($order->items as $item)
        <div class="item" data-ticket-item>
            <div class="row"><span><b>{{ $item->quantityLabel() }}{{ $item->sale_type === 'kg' ? '' : 'x' }}</b> {{ $item->name }}</span><span>{{ Money::brl($item->total) }}</span></div>
            @foreach (SelectionDescriber::lines($item->selections) as $selection)
                <div class="sub">{{ $selection }}</div>
            @endforeach
            @if ($item->notes) <div class="sub">OBS: {{ $item->notes }}</div> @endif
        </div>
    @endforeach
    @if ($order->notes)
        <hr><p data-ticket-notes>OBS DO PEDIDO: {{ $order->notes }}</p>
    @endif
    <hr>
    {!! $line('Subtotal', Money::brl($order->subtotal)) !!}
    @if ($delivery) {!! $line('Entrega', $order->delivery_fee_pending ? 'a combinar' : Money::brl($order->delivery_fee)) !!} @endif
    @if ((float) $order->discount > 0) {!! $line('Desconto', '-'.Money::brl($order->discount)) !!} @endif
    <div class="row big"><span>TOTAL</span><span>{{ Money::brl($order->total) }}</span></div>
    @if ($method)
        <p>Pagamento: {{ $method }}@if ($order->change_for) · troco p/ {{ Money::brl($order->change_for) }}@endif</p>
    @endif
</main>
</body>
</html>
