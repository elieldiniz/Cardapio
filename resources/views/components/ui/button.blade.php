@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-[9px] font-bold transition cursor-pointer disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'primary' => 'bg-accent text-white hover:brightness-95',
        'secondary' => 'border border-ink/15 bg-transparent text-ink hover:bg-ink/5',
        'danger' => 'bg-transparent text-danger hover:bg-danger/10',
        'ghost' => 'bg-transparent text-ink/70 hover:bg-ink/5',
    ];

    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-[12.5px]',
        'md' => 'px-[18px] py-2.5 text-[13px]',
        'lg' => 'px-5 py-3 text-[14px]',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
