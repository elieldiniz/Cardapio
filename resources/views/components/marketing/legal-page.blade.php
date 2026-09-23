@props(['title', 'updated' => '23/09/2026', 'description' => null])

<x-layouts.marketing :title="$title" :description="$description ?? $title.' — '.config('app.name')">
    <main class="bg-sand py-14">
        <article class="mx-auto max-w-3xl rounded-2xl bg-white px-6 py-10 shadow-[0_1px_3px_rgba(0,0,0,0.06)] sm:px-10">
            <h1 class="font-serif text-[34px] leading-tight">{{ $title }}</h1>
            <p class="mt-2 text-[13px] text-ink/50">Última atualização: {{ $updated }}</p>
            <div class="mt-8 flex flex-col gap-4 text-[15px] leading-relaxed text-ink/75 [&_a]:font-semibold [&_a]:text-accent [&_h2]:mt-4 [&_h2]:text-[19px] [&_h2]:font-bold [&_h2]:text-ink [&_li]:ml-5 [&_li]:list-disc [&_strong]:text-ink">
                {{ $slot }}
            </div>
        </article>
    </main>
</x-layouts.marketing>
