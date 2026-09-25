@props(['size' => 'size-9', 'nameClass' => 'text-[26px] text-ink'])
{{-- Degusta symbol + wordmark in Imperial Script. --}}
<span {{ $attributes->class('flex min-w-0 items-center gap-2.5') }}>
    <img src="{{ asset('images/brand/symbol.png') }}" alt="" class="{{ $size }} shrink-0 object-contain">
    <span class="truncate font-brand leading-none whitespace-nowrap {{ $nameClass }}">{{ config('app.name') }}</span>
</span>
