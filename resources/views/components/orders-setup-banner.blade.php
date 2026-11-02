{{--
    Tells the dono that the cardápio does not take orders yet because the store has no WhatsApp number:
    without it the public cardápio stays view-only (RF-04). Renders nothing once it is set, or when the
    plan does not include orders.
--}}
@php
    $restaurant = auth()->user()?->restaurant;
    $missing = $restaurant && $restaurant->hasFeature('orders') && blank($restaurant->orderSettings()->whatsapp_number);
@endphp

@if ($missing)
    <div data-orders-setup-banner role="alert" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[12px] bg-amber-50 px-4 py-3 text-[13px] text-amber-900 ring-1 ring-amber-200']) }}>
        <span class="size-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
        <span class="font-bold">Seu cardápio ainda não recebe pedidos.</span>
        <span class="text-amber-900/75">Informe o WhatsApp da loja para o carrinho aparecer para os clientes.</span>
        @if (Route::has('panel.settings'))
            <a href="{{ route('panel.settings') }}" wire:navigate class="font-bold underline">Informar o WhatsApp</a>
        @endif
    </div>
@endif
