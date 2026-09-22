@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'flex flex-col gap-1.5 text-[12px] font-semibold text-ink/60']) }}>
    @if ($label)
        <span>{{ $label }}</span>
    @endif

    {{ $slot }}

    @if ($hint)
        <span class="text-[11.5px] font-normal text-ink/45">{{ $hint }}</span>
    @endif

    @if ($error)
        @error($error)
            <span class="text-[12px] font-medium text-danger" role="alert">{{ $message }}</span>
        @enderror
    @endif
</label>
