@props(['title', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2.5 px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-accent/10 text-accent">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <p class="text-[15px] font-bold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="max-w-sm text-[13.5px] leading-relaxed text-ink/55">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
