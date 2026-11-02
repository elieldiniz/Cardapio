{{--
    Warns the dono that customers currently see the store as closed (RF-02, UI-04).
    Renders nothing while the store is open, so it never nags.
--}}
@php
    $restaurant = auth()->user()?->restaurant;
    $state = $restaurant ? \App\Support\BusinessHours::state($restaurant) : null;
@endphp

@if ($state && ! $state['open'])
    <div
        data-store-closed-banner
        role="status"
        {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[12px] bg-amber-50 px-4 py-3 text-[13px] text-amber-900']) }}
    >
        <span class="size-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
        <span class="font-semibold">
            @if ($state['reason'] === 'manual')
                Sua loja está fechada para pedidos agora.
            @else
                {{ $state['label'] }} — sua loja está fechada para pedidos agora.
            @endif
        </span>
        <span class="text-amber-900/70">Os clientes veem o cardápio, mas só conseguem pedir quando abrir.</span>
        @if (Route::has('panel.settings'))
            <a href="{{ route('panel.settings') }}" wire:navigate class="font-bold underline">Ajustar horário</a>
        @endif
    </div>
@endif
