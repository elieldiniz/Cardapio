@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'flex min-w-[150px] flex-1 flex-col gap-1.5 rounded-[14px] bg-white px-5 py-[18px] shadow-[0_1px_3px_rgba(0,0,0,0.06)]']) }}>
    <span class="text-[12px] font-semibold text-ink/50">{{ $label }}</span>
    <span class="text-[28px] leading-none font-bold text-ink">{{ $value }}</span>
</div>
