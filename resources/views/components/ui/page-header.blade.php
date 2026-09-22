@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3']) }}>
    <div>
        <h1 class="font-serif text-[26px] leading-tight text-ink sm:text-[30px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-[14px] text-ink/55">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
