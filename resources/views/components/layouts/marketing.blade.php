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
<body class="bg-white text-ink">
    <header class="sticky top-0 z-40 border-b border-white/5 bg-night/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent font-serif text-lg text-white">D</span>
                <span class="truncate font-serif text-[16px] whitespace-nowrap text-white sm:text-[19px]">{{ config('app.name') }}</span>
            </a>

            <nav class="flex shrink-0 items-center gap-1 text-[13.5px] font-semibold whitespace-nowrap sm:gap-2">
                <a href="{{ route('home') }}#como-funciona" class="hidden rounded-lg px-3 py-2 text-white/70 hover:text-white md:inline">Como funciona</a>
                @if (config('landing.show_prices'))
                    <a href="{{ route('home') }}#planos" class="hidden rounded-lg px-3 py-2 text-white/70 hover:text-white md:inline">Planos</a>
                @endif
                <a href="{{ route('home') }}#perguntas" class="hidden rounded-lg px-3 py-2 text-white/70 hover:text-white md:inline">Dúvidas</a>
                @auth
                    <a href="{{ \App\Support\HomeRedirect::for(auth()->user()) }}" class="rounded-[9px] bg-accent px-4 py-2 text-white hover:brightness-95">Ir para o painel</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-white/80 hover:text-white">Entrar</a>
                    <a href="{{ route('register') }}" class="rounded-[9px] bg-accent px-3.5 py-2 text-white hover:brightness-95 sm:px-4">Criar conta<span class="hidden sm:inline"> grátis</span></a>
                @endauth
            </nav>
        </div>
    </header>

    @if (session('status'))
        <div class="bg-success/10 px-4 py-3 text-center text-[14px] font-semibold text-success" role="status">{{ session('status') }}</div>
    @endif

    {{ $slot }}

    <footer class="bg-night text-white/80" data-footer>
        <div class="mx-auto max-w-6xl px-4 py-10 text-center sm:px-6">
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
