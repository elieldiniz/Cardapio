<select {{ $attributes->merge(['class' => 'w-full rounded-lg border border-ink/15 bg-white px-3 py-2.5 text-[14px] font-medium text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20']) }}>
    {{ $slot }}
</select>
