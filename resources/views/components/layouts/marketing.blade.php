{{-- Public marketing pages: landing, Sobre, Contato and the legal pages. --}}
@props([
    'title' => null,
    'description' => 'Cardápio em vídeo pelo QR Code da mesa. Transforme a foto do prato em vídeo com IA, sem app para o cliente. Comece grátis.',
])

@php
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name').' — Cardápio em vídeo pelo QR Code';
    $contact = config('landing.contact');
    $footerLinks = [
        ['Sobre', route('marketing.about')],
        ['Termos e Condições', route('legal.terms')],
        ['Privacidade', route('legal.privacy')],
        ['Política de Reembolso', route('legal.refund')],
        ['Contato', route('marketing.contact')],
        ['Política de Cookies', route('legal.cookies')],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @include('partials.favicons')
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#161412">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="site-type bg-white text-ink">
    <header class="sticky top-0 z-40 border-b border-white/10 bg-night shadow-[0_8px_30px_-12px_rgba(0,0,0,0.6)]" data-site-header>
        {{-- Accent hairline: ties the header to the brand color without a heavy bar. --}}
        <div class="h-px bg-gradient-to-r from-transparent via-accent/70 to-transparent" aria-hidden="true"></div>

        <div class="relative mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <a href="{{ route('home') }}" class="min-w-0" aria-label="{{ config('app.name') }}">
                <x-brand.logo size="size-10" name-class="text-[28px] text-white sm:text-[32px]" />
            </a>

            <nav class="flex shrink-0 items-center gap-1.5 text-[14px] font-semibold whitespace-nowrap sm:gap-2" aria-label="Principal">
                <div class="hidden items-center gap-1 md:flex">
                    <a href="{{ route('home') }}#como-funciona" class="px-3.5 py-2 text-white/70 underline-offset-8 transition hover:text-white hover:underline hover:decoration-accent hover:decoration-2">Como funciona</a>
                    @if (config('landing.show_prices'))
                        <a href="{{ route('home') }}#planos" class="px-3.5 py-2 text-white/70 underline-offset-8 transition hover:text-white hover:underline hover:decoration-accent hover:decoration-2">Planos</a>
                    @endif
                    <a href="{{ route('home') }}#perguntas" class="px-3.5 py-2 text-white/70 underline-offset-8 transition hover:text-white hover:underline hover:decoration-accent hover:decoration-2">Dúvidas</a>
                </div>

                @auth
                    <a href="{{ \App\Support\HomeRedirect::for(auth()->user()) }}" class="rounded-full bg-accent px-5 py-2.5 text-white transition hover:brightness-95">Ir para o painel</a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-3.5 py-2 text-white/80 underline-offset-8 transition hover:text-white hover:underline hover:decoration-accent hover:decoration-2 sm:inline">Entrar</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-accent px-4 py-2.5 text-white transition hover:brightness-95 sm:px-5">Criar conta<span class="hidden sm:inline"> grátis</span></a>
                @endauth

                {{-- Mobile menu: plain <details>, so it works without JavaScript. --}}
                <details class="group md:hidden" data-mobile-menu>
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full border border-white/15 text-white transition hover:bg-white/10 [&::-webkit-details-marker]:hidden" aria-label="Abrir menu">
                        <svg class="size-5 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
                        <svg class="hidden size-5 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
                    </summary>
                    <div class="absolute inset-x-3 top-full mt-2 flex flex-col rounded-2xl border border-white/10 bg-night p-2 shadow-2xl sm:inset-x-6">
                        <a href="{{ route('home') }}#como-funciona" class="rounded-xl px-4 py-3 text-white/80 hover:bg-white/5 hover:text-white">Como funciona</a>
                        @if (config('landing.show_prices'))
                            <a href="{{ route('home') }}#planos" class="rounded-xl px-4 py-3 text-white/80 hover:bg-white/5 hover:text-white">Planos</a>
                        @endif
                        <a href="{{ route('home') }}#perguntas" class="rounded-xl px-4 py-3 text-white/80 hover:bg-white/5 hover:text-white">Dúvidas</a>
                        @guest
                            <a href="{{ route('login') }}" class="rounded-xl px-4 py-3 text-white/80 hover:bg-white/5 hover:text-white sm:hidden">Entrar</a>
                        @endguest
                    </div>
                </details>
            </nav>
        </div>
    </header>

    @if (session('status'))
        <div class="bg-success/10 px-4 py-3 text-center text-[14px] font-semibold text-success" role="status">{{ session('status') }}</div>
    @endif

    {{ $slot }}

    <footer class="border-t border-white/10 bg-night text-white/80" data-footer>
        <div class="mx-auto max-w-6xl px-4 py-10 text-center sm:px-6">
            <a href="{{ route('home') }}" class="mb-6 inline-flex" aria-label="{{ config('app.name') }}">
                <x-brand.logo size="size-10" name-class="text-[30px] text-white" />
            </a>

            <nav class="flex flex-wrap items-center justify-center gap-x-1 gap-y-2 text-[13.5px]" aria-label="Links institucionais">
                @foreach ($footerLinks as [$label, $href])
                    <a href="{{ $href }}" class="hover:text-white">{{ $label }}</a>
                    @unless ($loop->last)
                        <span class="text-white/30" aria-hidden="true">|</span>
                    @endunless
                @endforeach
            </nav>

            @if (array_filter($contact))
                <div class="mt-4 flex flex-wrap justify-center gap-4 text-[13px] text-white/60">
                    @if ($contact['whatsapp'])
                        <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="hover:text-white">WhatsApp</a>
                    @endif
                    @if ($contact['email'])
                        <a href="mailto:{{ $contact['email'] }}" class="hover:text-white">{{ $contact['email'] }}</a>
                    @endif
                    @if ($contact['instagram'])
                        <a href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener" class="hover:text-white">Instagram</a>
                    @endif
                </div>
            @endif

            <p class="mt-4 text-[13px] text-white/55">Copyright © {{ now()->year }} {{ config('landing.legal_entity') }}. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
