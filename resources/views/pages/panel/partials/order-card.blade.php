{{--
    One order card of the manager (RF-08, UI-01). Rendered inside the orders page, so the
    wire: actions belong to that component. $order is loaded with items, status, payment
    method and customer.
--}}
@php
    use App\Support\Money;

    $slug = $order->status->slug;
    $timezone = $this->restaurant->orderSettings()->timezone;
    $placedAt = $order->created_at->copy()->setTimezone($timezone);
    $minutes = (int) $order->created_at->diffInMinutes(now());
    $ago = $minutes < 1 ? 'agora' : ($minutes < 60 ? "há {$minutes} min" : $placedAt->format('H:i'));
    $payment = $order->paymentMethod?->slug;
    $paymentLabel = match ($payment) {
        'dinheiro' => '💵 Dinheiro'.($order->change_for ? ' · troco p/ '.Money::brl($order->change_for) : ''),
        'pix' => '⚡ Pix',
        'debito' => '💳 Débito',
        'credito' => '💳 Crédito',
        default => null,
    };
    $ended = in_array($slug, ['finalizado', 'recusado', 'cancelado'], true);
    $delivery = $order->isDelivery();
    $whatsapp = $order->customer_phone ? 'https://api.whatsapp.com/send?phone='.$order->customer_phone : null;
    $notice = app(\App\Services\Orders\CustomerNoticeBuilder::class)->link($order);
    $itemsSummary = $order->items->map(fn ($item) => $item->quantityLabel().' '.($item->sale_type === 'kg' ? '' : 'un. ').$item->name)->implode(' · ');
@endphp

<div class="flex flex-col gap-2.5 rounded-[14px] bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.08)] {{ in_array($slug, ['recusado', 'cancelado'], true) ? 'opacity-70' : '' }}" data-order-card="{{ $order->code }}" data-status="{{ $slug }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-1.5 text-[13px] font-extrabold tracking-wide text-ink">
                {{ $order->code }}
                <span class="rounded-full bg-ink/[0.07] px-2 py-0.5 text-[10.5px] font-bold uppercase text-ink/60">{{ $delivery ? '🛵 Entrega' : ($order->fulfillment === 'balcao' ? '🏪 Balcão' : '🏪 Retirada') }}</span>
                @if ($slug === 'recusado') <span class="rounded-full bg-danger/10 px-2 py-0.5 text-[10.5px] font-bold uppercase text-danger">Recusado</span> @endif
                @if ($slug === 'cancelado') <span class="rounded-full bg-danger/10 px-2 py-0.5 text-[10.5px] font-bold uppercase text-danger">Cancelado</span> @endif
            </p>
            <p class="mt-0.5 text-[13px] leading-snug text-ink/70"><strong class="text-ink">{{ $order->customer_name ?? 'Cliente' }}</strong>@if ($order->customer_phone) · {{ preg_replace('/^55(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $order->customer_phone) }}@endif @if ($order->neighborhood) · {{ $order->neighborhood }} @endif</p>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-[15px] font-extrabold text-ink">{{ Money::brl($order->total) }}</p>
            @if ($order->adjusted_at && $order->original_total !== null)
                <p class="text-[11px] font-semibold text-amber-800" data-adjusted title="Valor ajustado">era {{ Money::brl($order->original_total) }}</p>
            @endif
            <p class="text-[11.5px] text-ink/45">{{ $ago }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-1.5 text-[11.5px] font-semibold">
        @if ($paymentLabel)
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-900" data-payment>{{ $paymentLabel }}@unless ($ended) · a cobrar @endunless</span>
        @endif
        @if ($order->customer && $order->customer->orders_count <= 1)
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800">🆕 Cliente novo</span>
        @endif
        @if ($order->placed_off_hours)
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-900" data-off-hours>⏰ Feito fora do horário</span>
        @endif
        @if ($delivery && $order->delivery_fee_pending)
            <span class="rounded-full bg-accent/10 px-2.5 py-1 text-accent">Taxa a combinar</span>
        @endif
    </div>

    <details class="group text-[13px]" data-order-items>
        <summary class="cursor-pointer list-none text-ink/80">
            <span class="line-clamp-2">{{ $itemsSummary }}</span>
        </summary>
        <ul class="mt-2 flex flex-col gap-1.5 border-l-2 border-ink/10 pl-3">
            @foreach ($order->items as $item)
                <li>
                    <p class="font-semibold text-ink">{{ $item->quantityLabel() }}{{ $item->sale_type === 'kg' ? '' : '×' }} {{ $item->name }}</p>
                    @foreach (\App\Services\Orders\SelectionDescriber::lines($item->selections) as $line)
                        <p class="text-[12px] text-ink/60">{{ $line }}</p>
                    @endforeach
                    @if ($item->notes) <p class="text-[12px] italic text-ink/60">Obs.: {{ $item->notes }}</p> @endif
                </li>
            @endforeach
        </ul>
    </details>

    @if ($order->notes)
        <p class="rounded-lg bg-ink/[0.04] px-2.5 py-1.5 text-[12px] text-ink/70" data-order-notes>📝 {{ $order->notes }}</p>
    @endif

    @if ($delivery && $order->addressLine())
        <p class="text-[12.5px] text-ink/70" data-order-address>📍 {{ $order->addressLine() }}@if ($order->address_reference) <span class="text-ink/50">· {{ $order->address_reference }}</span>@endif</p>
    @endif

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] font-semibold">
        <a href="{{ route('panel.ticket', $order) }}" target="_blank" rel="noopener" class="text-ink/60 hover:underline" data-ticket>🖨 Comanda</a>
        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="text-emerald-700 hover:underline" data-chat data-wa>Conversar</a>
        @endif
        @if ($notice)
            <a href="{{ $notice }}" target="_blank" rel="noopener" class="text-emerald-700 hover:underline" data-notify data-wa>Avisar o cliente</a>
        @endif
        @if ($order->channel === 'feed')
            @if ($order->whatsapp_opened_at) <span class="text-emerald-700" data-badge="opened">✓ WhatsApp abriu</span> @endif
            @if ($order->customer_confirmed_at) <span class="text-emerald-700" data-badge="confirmed">✓ Cliente confirmou envio</span> @endif
        @endif
    </div>

    @if ($delivery && ! $ended && in_array($slug, ['em_preparo', 'em_rota'], true) && $this->couriers->isNotEmpty())
        <label class="flex items-center gap-2 text-[12px] font-semibold text-ink/60" data-courier>
            🛵 Motoboy
            <select wire:change="assignCourier({{ $order->id }}, $event.target.value)" class="min-w-0 flex-1 rounded-lg border border-ink/15 bg-white px-2 py-1.5 text-[12.5px] font-medium text-ink" aria-label="Motoboy de {{ $order->code }}">
                <option value="">Sem motoboy</option>
                @foreach ($this->couriers as $courier)
                    <option value="{{ $courier->id }}" @selected($order->courier_id === $courier->id)>{{ $courier->name }}</option>
                @endforeach
            </select>
            @if ($order->delivered_at) <span class="text-emerald-700" data-delivered>✓ Entregue</span> @endif
        </label>
        @if ($order->courier?->phone && $order->courier->linkIsValid())
            @php
                $courierText = 'Olá, '.$order->courier->firstName().'! Nova entrega para você: pedido '.$order->code.($order->neighborhood ? ' ('.$order->neighborhood.')' : '').".\nVeja o endereço e marque como entregue por aqui: ".route('courier.show', $order->courier->token);
            @endphp
            <a href="https://api.whatsapp.com/send?phone={{ $order->courier->phone }}&text={{ rawurlencode($courierText) }}" target="_blank" rel="noopener" class="text-[12px] font-bold text-emerald-700 hover:underline" data-notify-courier>📲 Avisar {{ $order->courier->firstName() }} no WhatsApp</a>
        @endif
    @elseif ($order->courier_id && $order->courier)
        <p class="text-[12px] font-semibold text-ink/55" data-courier-name>🛵 {{ $order->courier->name }}</p>
    @endif

    @if ($slug === 'finalizado' || ($slug === 'cancelado' && $order->payments->isNotEmpty()))
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px]" data-paid>
            @forelse ($order->payments as $paid)
                <span class="font-semibold text-ink/70">{{ match ($paid->method->slug) { 'dinheiro' => '💵', 'pix' => '⚡', 'cartao' => '💳', 'fiado' => '📒', default => '💳' } }} {{ Money::brl($paid->amount) }} · {{ $paid->method->name }}</span>
            @empty
                <span class="text-ink/45">Sem pagamento registrado</span>
            @endforelse
        </div>
    @endif

    @if ($slug === 'finalizado')
        <div class="flex flex-wrap items-center gap-2" data-actions>
            <x-ui.button size="sm" variant="secondary" wire:click="openEditPayment({{ $order->id }})" data-edit-payment>Editar pagamento</x-ui.button>
            <x-ui.button size="sm" variant="danger" wire:click="openCancel({{ $order->id }})" data-cancel>Cancelar pedido</x-ui.button>
        </div>
    @endif

    @unless ($ended)
        <div class="flex flex-wrap items-center gap-2 pt-0.5" data-actions>
            @if ($slug === 'novo')
                @if ($delivery && $order->delivery_fee_pending)
                    <x-ui.input wire:model="fees.{{ $order->id }}" placeholder="Taxa R$" inputmode="decimal" aria-label="Taxa de entrega de {{ $order->code }}" class="!w-[96px] !py-2" data-fee-input />
                @endif
                <x-ui.button size="sm" wire:click="accept({{ $order->id }})" data-accept>Aceitar</x-ui.button>
                <x-ui.button size="sm" variant="danger" wire:click="refuse({{ $order->id }})" wire:confirm="Recusar o pedido {{ $order->code }}?" data-refuse>Recusar</x-ui.button>
                <button type="button" wire:click="openAdjust({{ $order->id }})" title="Ajustar valor depois de pesar" aria-label="Ajustar valor de {{ $order->code }}" class="grid size-9 place-items-center rounded-xl border border-ink/15 text-[15px]" data-adjust>💲</button>
            @elseif ($slug === 'em_preparo')
                <x-ui.button size="sm" wire:click="advance({{ $order->id }}, 'em_rota')" data-advance>{{ $delivery ? 'Saiu p/ entrega' : 'Pronto p/ retirada' }}</x-ui.button>
                <button type="button" wire:click="openAdjust({{ $order->id }})" title="Ajustar valor depois de pesar" aria-label="Ajustar valor de {{ $order->code }}" class="grid size-9 place-items-center rounded-xl border border-ink/15 text-[15px]" data-adjust>💲</button>
                <x-ui.button size="sm" variant="ghost" wire:click="advance({{ $order->id }}, 'novo')" aria-label="Voltar {{ $order->code }} para Novos">↩</x-ui.button>
            @elseif ($slug === 'em_rota')
                <x-ui.button size="sm" wire:click="openFinalize({{ $order->id }})" data-finalize>Finalizar</x-ui.button>
                <button type="button" wire:click="openAdjust({{ $order->id }})" title="Ajustar valor depois de pesar" aria-label="Ajustar valor de {{ $order->code }}" class="grid size-9 place-items-center rounded-xl border border-ink/15 text-[15px]" data-adjust>💲</button>
                <x-ui.button size="sm" variant="ghost" wire:click="advance({{ $order->id }}, 'em_preparo')" aria-label="Voltar {{ $order->code }} para Em preparo">↩</x-ui.button>
            @endif
        </div>
    @endunless
</div>
