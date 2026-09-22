{{--
    Usage: <x-ui.modal wire:model="showDishModal" title="Novo prato"> ... <x-slot:footer>...</x-slot:footer></x-ui.modal>
    Without wire:model, open it from Alpine with $dispatch('open-modal', 'name') and pass name="...".
--}}
@props(['title' => null, 'name' => null, 'maxWidth' => 'max-w-[400px]'])

@php
    $model = $attributes->wire('model')->value();
@endphp

<div
    x-data="{ open: @if ($model) $wire.entangle('{{ $model }}') @else false @endif }"
    @if ($name)
        x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
        x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    @endif
    x-on:keydown.escape.window="open = false"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-40 flex items-end justify-center bg-ink/45 p-0 sm:items-center sm:p-4"
        x-on:click.self="open = false"
        role="dialog"
        aria-modal="true"
    >
        <div
            x-show="open"
            x-transition
            class="flex max-h-[92vh] w-full {{ $maxWidth }} flex-col gap-3.5 overflow-y-auto rounded-t-2xl bg-white p-6 sm:rounded-2xl sm:p-7"
        >
            @if ($title)
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-serif text-[22px] leading-tight text-ink">{{ $title }}</h2>
                    <button type="button" x-on:click="open = false" class="rounded-md p-1 text-ink/50 hover:bg-ink/5" aria-label="Fechar">
                        <x-ui.icon name="close" />
                    </button>
                </div>
            @endif

            {{ $slot }}

            @isset($footer)
                <div class="mt-1.5 flex gap-2.5">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
