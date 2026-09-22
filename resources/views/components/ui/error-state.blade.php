@props(['title' => 'Algo deu errado', 'description' => 'Não foi possível carregar esta informação. Tente novamente em instantes.'])

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border border-danger/20 bg-danger/5 p-4']) }} role="alert">
    <x-ui.icon name="alert" class="mt-0.5 size-5 text-danger" />
    <div class="flex flex-1 flex-col gap-1">
        <p class="text-[14px] font-bold text-danger">{{ $title }}</p>
        <p class="text-[13px] leading-relaxed text-ink/65">{{ $description }}</p>
        @if (! $slot->isEmpty())
            <div class="mt-2">{{ $slot }}</div>
        @endif
    </div>
</div>
