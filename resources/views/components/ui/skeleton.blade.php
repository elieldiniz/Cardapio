{{-- Skeleton placeholder rows shown while a screen or list is loading. --}}
@props(['rows' => 3, 'avatar' => false])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }} aria-busy="true" aria-label="Carregando">
    @for ($i = 0; $i < $rows; $i++)
        <div class="flex animate-pulse items-center gap-3.5">
            @if ($avatar)
                <div class="size-[52px] shrink-0 rounded-lg bg-ink/10"></div>
            @endif
            <div class="flex flex-1 flex-col gap-2">
                <div class="h-3.5 w-2/5 rounded bg-ink/10"></div>
                <div class="h-3 w-1/4 rounded bg-ink/[0.07]"></div>
            </div>
        </div>
    @endfor
</div>
