@php
    $restaurant = auth()->user()?->restaurant;
    $restaurantName = $restaurant?->name ?? config('app.name');
    $accent = $restaurant?->accentColor() ?? \App\Models\Restaurant::DEFAULT_ACCENT_COLOR;
    $navItems = \App\Support\PanelNavigation::items();
@endphp
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $accent }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $restaurantName }} · Painel</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ navOpen: false }">
    @if (\App\Actions\Admin\Impersonation::active())
        <div class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 bg-accent px-4 py-2 text-[13px] font-semibold text-white" data-impersonation-banner>
            <span>Modo suporte: você está vendo o painel como {{ auth()->user()->name }} ({{ $restaurantName }}).</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">
                @csrf
                <button type="submit" class="rounded-md bg-white/20 px-3 py-1 font-bold hover:bg-white/30">Voltar ao admin</button>
            </form>
        </div>
    @endif

    @stack('banners')

    <div class="flex min-h-screen">
        {{-- Sidebar: fixed column on desktop, off-canvas drawer on mobile --}}
        <div
            x-show="navOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-30 bg-ink/50 md:hidden"
            x-on:click="navOpen = false"
        ></div>

        <aside
            data-panel-nav
            class="fixed inset-y-0 left-0 z-40 flex w-[230px] shrink-0 -translate-x-full flex-col gap-[22px] bg-night px-3.5 py-[22px] transition-transform md:sticky md:top-0 md:h-screen md:translate-x-0"
            :class="{ 'translate-x-0': navOpen }"
        >
            <div class="flex items-center gap-2.5 px-2">
                @if ($restaurant?->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($restaurant->logo_path) }}" alt="" class="size-[38px] shrink-0 rounded-full object-cover">
                @else
                    <span class="flex size-[38px] shrink-0 items-center justify-center rounded-full bg-accent font-serif text-lg text-white">{{ mb_substr($restaurantName, 0, 1) }}</span>
                @endif
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-serif text-base text-white">{{ $restaurantName }}</span>
                    <span class="text-[11px] font-medium text-white/40">Painel do dono</span>
                </div>
            </div>

            <nav class="flex flex-col gap-[3px]" aria-label="Painel">
                @foreach ($navItems as $item)
                    @if ($item['url'])
                        <a
                            href="{{ $item['url'] }}"
                            wire:navigate
                            @if ($item['active']) aria-current="page" @endif
                            class="flex items-center gap-[11px] rounded-[9px] px-3 py-2.5 text-[13.5px] font-semibold transition {{ $item['active'] ? 'bg-accent text-white' : 'text-white/65 hover:bg-white/5 hover:text-white' }}"
                        >
                            <x-ui.icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span
                            aria-disabled="true"
                            class="flex cursor-default items-center gap-[11px] rounded-[9px] px-3 py-2.5 text-[13.5px] font-semibold text-white/25"
                        >
                            <x-ui.icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </span>
                    @endif
                @endforeach
            </nav>

            <div class="flex-1"></div>

            @stack('sidebar-footer')

            <a
                href="{{ route('panel.account') }}"
                wire:navigate
                @if (request()->routeIs('panel.account')) aria-current="page" @endif
                class="flex items-center gap-[11px] rounded-[9px] px-3 py-2.5 text-[13.5px] font-semibold transition {{ request()->routeIs('panel.account') ? 'bg-accent text-white' : 'text-white/65 hover:bg-white/5 hover:text-white' }}"
            >
                <x-ui.icon name="user" />
                Minha conta
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-[11px] rounded-[9px] px-3 py-2.5 text-left text-[13.5px] font-semibold text-white/65 transition hover:bg-white/5 hover:text-white">
                    <x-ui.icon name="logout" />
                    Sair
                </button>
            </form>

            <div class="px-2 text-[10.5px] text-white/30">Cardápio em Vídeo · v1</div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Mobile header --}}
            <header class="sticky top-0 z-20 flex items-center gap-3 bg-night px-4 py-3 md:hidden">
                <button type="button" x-on:click="navOpen = true" class="rounded-md p-1.5 text-white" aria-label="Abrir menu">
                    <x-ui.icon name="menu" class="size-5" />
                </button>
                <span class="truncate font-serif text-[17px] text-white">{{ $restaurantName }}</span>
            </header>

            <main class="w-full max-w-[980px] flex-1 px-4 py-6 sm:px-8 sm:py-8 lg:px-11 lg:py-9">
                @auth
                    <livewire:panel-notices class="mb-5" />
                @endauth

                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast />
</body>
</html>
