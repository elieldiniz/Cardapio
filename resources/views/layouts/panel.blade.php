@php
    $restaurant = auth()->user()?->restaurant;
    $restaurantName = $restaurant?->name ?? config('app.name');
    $accent = $restaurant?->accentColor() ?? \App\Models\Restaurant::DEFAULT_ACCENT_COLOR;
    $navGroups = \App\Support\PanelNavigation::groups();
    // The bottom bar of phones (≤ 640 px): the four screens used all day, plus "Mais" for the rest.
    $quickItems = collect($navGroups)->flatMap(fn ($group) => $group['items'])
        ->filter(fn ($item) => in_array($item['route'], ['panel.home', 'panel.orders', 'panel.pdv', 'panel.dishes', 'panel.cash'], true))
        ->take(4)->values();
@endphp
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $accent }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $restaurantName }} · Painel</title>
    @include('partials.favicons')
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#101010">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen pb-[calc(64px+env(safe-area-inset-bottom))] sm:pb-0" x-data="{ navOpen: false }">
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

            <nav class="flex min-h-0 flex-col gap-4 overflow-y-auto" aria-label="Painel">
                @foreach ($navGroups as $group)
                    <div class="flex flex-col gap-[3px]" data-nav-group>
                        <span class="px-3 pb-1 text-[10.5px] font-bold uppercase tracking-wider text-white/30">{{ $group['label'] }}</span>
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['url'] }}"
                                wire:navigate
                                @if ($item['active']) aria-current="page" @endif
                                class="flex items-center gap-[11px] rounded-[9px] px-3 py-2 text-[13.5px] font-semibold transition {{ $item['active'] ? 'bg-accent text-white' : 'text-white/65 hover:bg-white/5 hover:text-white' }}"
                            >
                                <x-ui.icon :name="$item['icon']" />
                                {{ $item['label'] }}
                                @if ($item['locked'] ?? false)
                                    <span class="ml-auto text-[11px]" title="Não incluído no seu plano" data-nav-lock>🔒</span>
                                @endif
                                @if ($item['route'] === 'panel.orders')
                                    <span
                                        x-data="{ count: {{ (int) $item['badge'] }} }"
                                        x-on:orders-count.window="count = $event.detail.count"
                                        x-show="count > 0"
                                        x-text="count"
                                        data-nav-badge="orders"
                                        title="Pedidos novos"
                                        class="ml-auto min-w-[20px] rounded-full bg-white px-1.5 py-0.5 text-center text-[11px] font-extrabold text-accent {{ (int) $item['badge'] > 0 ? '' : 'hidden' }}"
                                        @if ((int) $item['badge'] === 0) x-cloak @endif
                                    >{{ (int) $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
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

            <main class="w-full {{ ($wide ?? false) ? 'max-w-none' : 'max-w-[980px]' }} flex-1 px-4 py-6 sm:px-8 sm:py-8 lg:px-11 lg:py-9">
                @auth
                    <x-orders-setup-banner class="mb-5" />
                    <x-trial-banner class="mb-5" />
                    <x-store-status-banner class="mb-5" />
                    <livewire:panel-notices class="mb-5" />
                @endauth

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Phones: quick bar with "Mais" opening the full menu (RF-24). --}}
    @auth
        <nav class="fixed inset-x-0 bottom-0 z-30 flex items-stretch border-t border-white/10 bg-night pb-[env(safe-area-inset-bottom)] sm:hidden" aria-label="Atalhos" data-bottom-bar>
            @foreach ($quickItems as $item)
                <a href="{{ $item['url'] }}" wire:navigate @if ($item['active']) aria-current="page" @endif class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10.5px] font-semibold {{ $item['active'] ? 'text-accent' : 'text-white/65' }}">
                    <x-ui.icon :name="$item['icon']" class="size-5" />
                    {{ $item['label'] }}
                </a>
            @endforeach
            <button type="button" x-on:click="navOpen = true" class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10.5px] font-semibold text-white/65" data-more-menu>
                <x-ui.icon name="menu" class="size-5" />
                Mais
            </button>
        </nav>
    @endauth

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/panel-sw.js', { scope: '/painel/' }).catch(() => {}));
        }
    </script>

    <x-ui.toast />
</body>
</html>
