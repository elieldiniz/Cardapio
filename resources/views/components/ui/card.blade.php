@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-[14px] bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06)] '.($padding ? 'p-5 sm:p-6' : 'overflow-hidden')]) }}>
    @if ($title || isset($actions))
        <div class="mb-4 flex items-center justify-between gap-3 {{ $padding ? '' : 'px-5 pt-5 sm:px-6' }}">
            @if ($title)
                <h2 class="text-[15px] font-bold text-ink">{{ $title }}</h2>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif

    {{ $slot }}
</div>
