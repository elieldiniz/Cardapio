{{-- Landing page at "/" — the product's front door and the target of the "feito com" link. --}}
@use('App\Support\Money')
@php
    $metricsLabels = ['cardapio_total' => 'Veja quantas pessoas abrem o cardápio', 'por_prato' => 'Veja quais pratos chamam mais atenção', 'por_prato_com_tempo_assistido' => 'Veja o que chama atenção e por quanto tempo é assistido'];

    // "R$ 30,00" reads heavier than "R$ 30": drop the cents when they are zero.
    $brl = fn (int $cents) => \Illuminate\Support\Str::replaceEnd(',00', '', Money::brlFromCents($cents));

    $heroDish = [
        // The photo is the video's first frame: this exact photo became this video.
        'image' => asset('images/landing/hero-prato.jpg'),
        'video' => asset('videos/landing/hero-prato.mp4'),
        'name' => 'Espaguete à Bolonhesa',
        'price' => 'R$ 42,90',
        'desc' => 'Molho de carne cozido lentamente e parmesão ralado na hora.',
        'badge' => 'Mais pedido',
    ];

    $faq = [
        ['O cliente precisa baixar algum aplicativo?', 'Não. Ele aponta a câmera do celular para o QR Code da mesa e o cardápio abre direto no navegador, sem cadastro.'],
        ['Como o vídeo do prato é criado?', 'Você envia 1 foto (ou 3 a 4 fotos de ângulos diferentes, para um resultado melhor). Nós criamos clipes curtos e verticais do prato, sempre no mesmo estilo. Você escolhe a versão que mais gostar e só ela vai para o cardápio.'],
        ['O que é uma “geração”?', 'Cada vídeo criado a partir das suas fotos consome 1 geração. Pedir 2 versões do mesmo prato consome 2 gerações. Se algo falhar, a geração não é descontada.'],
        ['E se acabarem as gerações do mês?', 'Você pode comprar um pacote avulso de gerações, que não expira, ou mudar de plano. Enviar vídeo próprio nunca consome gerações.'],
        ['Dá para fazer pedido pelo cardápio?', 'Não. O cardápio é para ver os pratos em vídeo; o pedido continua sendo feito com o garçom, como o cliente já está acostumado.'],
        ['Posso cancelar quando quiser?', 'Sim. A assinatura é mensal e você cancela pelo próprio painel. O cardápio continua no ar no plano Grátis.'],
        ['Quais formas de pagamento são aceitas?', 'Cartão de crédito, com pagamento processado com segurança pela Stripe.'],
    ];
@endphp

<x-layouts.marketing>
    {{-- ===== Hero ===== --}}
    <section class="hero-stage relative isolate overflow-hidden bg-night text-white">
        {{-- Restaurant scene behind the hero; the overlay keeps the copy readable and the phone in the light. --}}
        <picture class="pointer-events-none absolute inset-0 -z-20" aria-hidden="true">
            <source media="(max-width: 767px)" srcset="{{ asset('images/landing/hero-fundo-mobile.webp') }}" type="image/webp">
            <source media="(max-width: 767px)" srcset="{{ asset('images/landing/hero-fundo-mobile.jpg') }}" type="image/jpeg">
            <source srcset="{{ asset('images/landing/hero-fundo.webp') }}" type="image/webp">
            <img src="{{ asset('images/landing/hero-fundo.jpg') }}" alt="" width="2200" height="860" class="hero-zoom size-full object-cover object-[68%_center] max-md:object-[50%_bottom]" fetchpriority="high" decoding="async">
        </picture>
        <div class="hero-overlay pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
        {{-- Halo rings behind the phone: a quiet spotlight that pulls the eye to the product. --}}
        <div class="pointer-events-none absolute top-1/2 right-[6%] -z-10 hidden -translate-y-1/2 md:block" aria-hidden="true">
            <div class="absolute top-1/2 left-1/2 size-[620px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/[0.06]"></div>
            <div class="absolute top-1/2 left-1/2 size-[820px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/[0.04]"></div>
        </div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 md:grid-cols-[1.15fr_0.85fr] md:py-24">
            <div>
                <h1 class="hero-rise font-display text-[38px] leading-[1.08] text-balance text-white sm:text-[56px]" style="--d: 0.1s">Faça o cliente <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">desejar o prato</span> antes de pedir.</h1>
                <p class="hero-rise mt-6 max-w-xl text-[17px] leading-relaxed text-pretty text-white/75 sm:text-[18px]" style="--d: 0.3s">Envie a foto e a IA faz o vídeo. O cliente escaneia o QR Code e vê cada prato “vivo”, como nos Reels. Sem app e sem gravar nada.</p>

                <div class="hero-rise mt-9 flex flex-wrap gap-3" style="--d: 0.5s">
                    <a href="{{ route('register') }}" class="rounded-[10px] bg-accent px-6 py-3.5 text-[15px] font-bold text-white shadow-lg shadow-accent/30 transition hover:brightness-95">Criar conta grátis</a>
                    <a href="#como-funciona" class="rounded-[10px] border border-white/20 px-6 py-3.5 text-[15px] font-bold text-white transition hover:bg-white/5">Ver como funciona</a>
                </div>
                <p class="hero-rise mt-4 text-[13px] text-white/55" style="--d: 0.6s">Plano grátis com 5 vídeos por IA para experimentar. Sem cartão de crédito.</p>

                <ul class="hero-rise mt-7 flex flex-wrap gap-x-5 gap-y-2 text-[13.5px] text-white/80" style="--d: 0.75s">
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Sem app para o cliente</li>
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Da foto ao vídeo em minutos</li>
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Preço e esgotado na hora</li>
                </ul>
            </div>

            {{-- Phone mockup of the client feed --}}
            <div class="hero-slide flex justify-center" aria-hidden="true" style="--d: 0.4s">
                <div class="relative h-[560px] w-[280px] overflow-hidden rounded-[42px] border-[7px] border-black bg-black shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8),0_0_90px_-10px_color-mix(in_srgb,var(--color-accent)_45%,transparent)] ring-1 ring-white/15">
                    <img src="{{ $heroDish['image'] }}" alt="" class="absolute inset-0 size-full object-cover" fetchpriority="high">
                    <video class="absolute inset-0 size-full object-cover" src="{{ $heroDish['video'] }}" poster="{{ $heroDish['image'] }}" autoplay muted loop playsinline preload="auto" data-hero-video></video>
                    <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-black/60 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/85 to-transparent"></div>
                    <div class="absolute inset-x-5 bottom-8 flex flex-col gap-2 pr-10">
                        <span class="w-fit rounded-full bg-accent px-2.5 py-1 text-[9.5px] font-bold tracking-wide uppercase">{{ $heroDish['badge'] }}</span>
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-serif text-[21px] leading-tight">{{ $heroDish['name'] }}</span>
                            <span class="text-[14px] font-bold text-accent">{{ $heroDish['price'] }}</span>
                        </div>
                        <span class="text-[12.5px] leading-snug text-white/85">{{ $heroDish['desc'] }}</span>
                    </div>
                    <div class="absolute inset-x-4 top-4 flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-7 items-center justify-center rounded-full bg-accent font-serif text-[13px]">T</span>
                            <span class="font-serif text-[15px]">Trattoria</span>
                        </div>
                        <div class="flex gap-1.5 text-[11px] font-semibold">
                            <span class="rounded-full bg-accent px-2.5 py-1">Massas</span>
                            <span class="px-2.5 py-1 text-white/65">Carnes</span>
                            <span class="px-2.5 py-1 text-white/65">Bebidas</span>
                        </div>
                    </div>
                    <div class="absolute right-3 bottom-40 flex flex-col gap-3">
                        <span class="flex size-9 items-center justify-center rounded-full border border-white/25 bg-black/40"><x-ui.icon name="media" class="size-4" /></span>
                        <span class="flex size-9 items-center justify-center rounded-full border border-white/25 bg-black/40"><x-ui.icon name="alert" class="size-4" /></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Da foto ao vídeo ===== --}}
    <section class="bg-sand py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">A foto que você já tem vira um vídeo que <span class="text-accent">dá água na boca</span></h2>
                <p class="mx-auto mt-5 max-w-xl text-[17px] leading-relaxed text-pretty text-ink/65">Sem câmera, sem edição e sem equipe. Você envia a foto, escolhe a melhor versão, e só vai ao ar o que você aprovar.</p>
            </div>

            {{-- Featured: 1 foto → vídeo, with the real before/after. --}}
            <div class="mt-12 overflow-hidden rounded-3xl bg-gradient-to-br from-night to-[#3a2416] text-white shadow-xl ring-1 ring-black/5" data-feature-photo>
                <div class="grid items-center gap-10 p-6 sm:p-10 md:grid-cols-[1.05fr_0.95fr] md:gap-12 md:p-14">
                    <div class="flex items-center justify-center gap-4 sm:gap-7">
                        <figure class="flex flex-col items-center gap-3">
                            <img src="{{ $heroDish['image'] }}" alt="Foto enviada: prato de espaguete" class="h-[250px] w-[141px] rounded-2xl object-cover shadow-2xl ring-1 ring-white/15 sm:h-[330px] sm:w-[186px]" loading="lazy">
                            <figcaption class="text-[12px] font-semibold tracking-wide text-white/60 uppercase">Sua foto</figcaption>
                        </figure>

                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-[18px] text-accent ring-1 ring-white/15 sm:size-12" aria-hidden="true">→</span>

                        <figure class="flex flex-col items-center gap-3">
                            <video src="{{ $heroDish['video'] }}" poster="{{ $heroDish['image'] }}" class="h-[250px] w-[141px] rounded-2xl object-cover shadow-[0_20px_60px_-15px_color-mix(in_srgb,var(--color-accent)_55%,transparent)] ring-2 ring-accent sm:h-[330px] sm:w-[186px]" autoplay muted loop playsinline preload="metadata" aria-label="Vídeo gerado a partir da foto"></video>
                            <figcaption class="text-[12px] font-semibold tracking-wide text-accent uppercase">Vídeo pronto</figcaption>
                        </figure>
                    </div>

                    <div>
                        <span class="inline-block rounded-full bg-accent/15 px-3 py-1 text-[12px] font-bold text-accent">O jeito mais simples</span>
                        <h3 class="mt-4 font-serif text-[30px] leading-tight text-balance sm:text-[38px]">Uma foto. Um vídeo pronto para dar vontade.</h3>
                        <p class="mt-4 text-[16px] leading-relaxed text-white/70">Envie a foto do prato e a IA cria um clipe curto, com aproximação suave e movimento de câmera, sempre no mesmo estilo em todo o cardápio.</p>

                        <ol class="mt-6 flex flex-col gap-3 text-[15px] text-white/85">
                            @foreach (['Envie a foto do prato', 'A IA gera as variações em minutos', 'Você escolhe a melhor e aprova'] as $step)
                                <li class="flex items-center gap-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-[13px] font-bold text-white">{{ $loop->iteration }}</span>
                                    {{ $step }}
                                </li>
                            @endforeach
                        </ol>

                        <a href="{{ route('register') }}" class="mt-8 inline-flex rounded-[10px] bg-accent px-6 py-3.5 text-[15px] font-bold text-white transition hover:brightness-95">Criar conta grátis</a>
                    </div>
                </div>
            </div>

            {{-- The other two ways, as a quiet list so the visitor knows there is more. --}}
            <div class="mt-10">
                <p class="text-center text-[12.5px] font-bold tracking-widest text-ink/45 uppercase">E também</p>
                <ul class="mt-4 grid gap-3 md:grid-cols-2" data-more-options>
                    @foreach ([
                        ['appearance', '3 a 4 ângulos', 'Com fotos de lados diferentes, o prato gira de verdade na tela. É o resultado mais impressionante.'],
                        ['upload', 'Seu próprio vídeo', 'Já gravou? Envie direto do celular. Vertical, de 5 a 15 segundos, sem gastar gerações.'],
                    ] as [$icon, $title, $text])
                        <li class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent"><x-ui.icon :name="$icon" class="size-5" /></span>
                            <div>
                                <h3 class="text-[16px] font-bold">{{ $title }}</h3>
                                <p class="mt-1 text-[14.5px] leading-relaxed text-ink/60">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ===== Como funciona ===== --}}
    <section id="como-funciona" class="scroll-mt-20 bg-white py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">Do prato ao vídeo em <span class="text-accent">3 passos</span></h2>
                <p class="mt-4 text-[17px] leading-relaxed text-pretty text-ink/60">Do cadastro ao primeiro cliente assistindo, em poucos minutos.</p>
            </div>

            <ol class="relative mt-16 grid gap-12 md:grid-cols-3 md:gap-6" data-steps>
                {{-- Dashed line joining the three numbers (desktop). --}}
                <span class="absolute top-7 right-[16.66%] left-[16.66%] hidden border-t-2 border-dashed border-accent/35 md:block" aria-hidden="true"></span>

                @foreach ([
                    ['Cadastre seus pratos', 'Nome, preço e fotos, direto do celular. Leva poucos minutos.'],
                    ['Transformamos a foto em vídeo', 'Você escolhe a melhor versão e só vai ao ar o que você aprovar.'],
                    ['Coloque o QR Code na mesa', 'O cliente escaneia e vê os pratos em vídeo na hora, sem baixar nada.'],
                ] as [$title, $text])
                    <li class="reveal-up relative flex flex-col items-center gap-5 text-center" style="--i: {{ $loop->index }}">
                        <span class="relative z-10 flex size-14 items-center justify-center rounded-full bg-accent font-serif text-[24px] text-white ring-[10px] ring-white">{{ $loop->iteration }}</span>

                        {{-- Mini scene: shows the step instead of only describing it. --}}
                        <div class="flex h-48 w-full items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-night to-[#3a2416] p-4 shadow-lg ring-1 ring-black/5">
                            @if ($loop->first)
                                <div class="w-full max-w-[230px] space-y-2.5 rounded-xl bg-white/10 p-3 text-left text-[11.5px] text-white/80 ring-1 ring-white/15">
                                    <div class="flex items-center gap-2.5">
                                        <img src="{{ $heroDish['image'] }}" alt="" class="size-11 rounded-lg object-cover" loading="lazy">
                                        <div class="min-w-0">
                                            <p class="truncate text-[13px] font-semibold text-white">{{ $heroDish['name'] }}</p>
                                            <p class="font-bold text-accent">{{ $heroDish['price'] }}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1.5">
                                        <span class="rounded-full bg-accent px-2 py-0.5 font-bold text-white">{{ $heroDish['badge'] }}</span>
                                        <span class="rounded-full bg-white/15 px-2 py-0.5">Massas</span>
                                    </div>
                                    <div class="h-8 rounded-lg bg-white/10"></div>
                                </div>
                            @elseif ($loop->iteration === 2)
                                <div class="flex items-center gap-4">
                                    <img src="{{ $heroDish['image'] }}" alt="" class="h-32 w-[74px] rounded-lg object-cover shadow-lg ring-1 ring-white/15" loading="lazy">
                                    <span class="text-[18px] text-accent" aria-hidden="true">→</span>
                                    <video src="{{ $heroDish['video'] }}" poster="{{ $heroDish['image'] }}" class="ring-pulse h-32 w-[74px] rounded-lg object-cover shadow-lg ring-2 ring-accent" autoplay muted loop playsinline preload="metadata" aria-hidden="true"></video>
                                </div>
                            @else
                                <div class="flex items-center gap-5">
                                    <div class="relative size-[104px] overflow-hidden rounded-xl bg-white p-2.5 shadow-lg">
                                        <svg viewBox="0 0 9 9" class="size-full" shape-rendering="crispEdges" aria-hidden="true">
                                            <g fill="#161412">
                                                <rect x="0" y="0" width="3" height="3"/><rect x="6" y="0" width="3" height="3"/><rect x="0" y="6" width="3" height="3"/>
                                                <rect x="4" y="0" width="1" height="1"/><rect x="4" y="2" width="1" height="2"/><rect x="3" y="4" width="2" height="1"/><rect x="6" y="4" width="1" height="1"/>
                                                <rect x="8" y="4" width="1" height="2"/><rect x="4" y="6" width="2" height="1"/><rect x="7" y="7" width="2" height="2"/><rect x="5" y="8" width="1" height="1"/><rect x="1" y="4" width="1" height="1"/>
                                            </g>
                                            <g fill="#fff"><rect x="1" y="1" width="1" height="1"/><rect x="7" y="1" width="1" height="1"/><rect x="1" y="7" width="1" height="1"/></g>
                                        </svg>
                                        <span class="qr-scan-line absolute inset-x-0 h-0.5 bg-accent shadow-[0_0_12px_2px_var(--color-accent)]" aria-hidden="true"></span>
                                    </div>
                                    <div class="flex flex-col items-start gap-2 text-left">
                                        <span class="rounded-full bg-white/10 px-3 py-1 text-[12px] font-bold text-white ring-1 ring-white/15">Mesa 12</span>
                                        <span class="flex items-center gap-1.5 text-[12px] font-semibold text-accent"><x-ui.icon name="media" class="size-4" />Abrindo o cardápio…</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <h3 class="text-[19px] font-bold">{{ $title }}</h3>
                            <p class="mx-auto mt-2 max-w-xs text-[15px] leading-relaxed text-ink/60">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="mt-14 flex flex-col items-center gap-3 text-center">
                <a href="{{ route('register') }}" class="rounded-[10px] bg-accent px-7 py-4 text-[15px] font-bold text-white transition hover:brightness-95">Criar conta grátis</a>
                <p class="text-[13px] text-ink/50">Pronto em poucos minutos. Sem cartão de crédito.</p>
            </div>
        </div>
    </section>

    {{-- ===== Recursos ===== --}}
    <section class="features-stage relative isolate overflow-hidden py-24 text-white">
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-accent/70 to-transparent" aria-hidden="true"></div>
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="inline-block rounded-full bg-accent/15 px-3.5 py-1 text-[12px] font-bold tracking-wide text-accent uppercase ring-1 ring-accent/30">Tudo em um só lugar</span>
                <h2 class="mt-5 font-serif text-[36px] leading-[1.1] text-balance sm:text-[50px]">Simples para você. <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">Irresistível para o cliente.</span></h2>
                <p class="mt-5 text-[17px] leading-relaxed text-pretty text-white/75 sm:text-[18px]">Tudo se resolve pelo celular, no meio do expediente: sem técnico, sem designer e sem complicação.</p>
            </div>

            {{-- Bento: three headline features with a live mini scene, three supporting ones. --}}
            <div class="mt-14 grid gap-5 md:grid-cols-3" data-features>
                {{-- 1. Price and sold out --}}
                <article class="reveal-up group flex flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-white/[0.09] to-white/[0.02] shadow-xl shadow-black/20 ring-1 ring-white/10 transition duration-300 hover:-translate-y-1.5 hover:ring-accent/50 hover:shadow-accent/10" style="--i: 0">
                    <div class="flex h-48 items-center justify-center bg-gradient-to-br from-accent/25 via-accent/[0.07] to-transparent p-5">
                        <div class="w-full max-w-[250px] rounded-xl bg-night/70 p-3 ring-1 ring-white/15">
                            <div class="flex items-center gap-3">
                                <img src="{{ $heroDish['image'] }}" alt="" class="size-12 rounded-lg object-cover" loading="lazy">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold">{{ $heroDish['name'] }}</p>
                                    <p class="text-[13px] font-bold text-accent">{{ $heroDish['price'] }}</p>
                                </div>
                                <span class="sold-switch relative h-6 w-11 shrink-0 rounded-full" aria-hidden="true"><span class="sold-knob absolute top-0.5 left-0.5 size-5 rounded-full bg-white"></span></span>
                            </div>
                            <div class="relative mt-3 h-6 text-[11.5px] font-bold">
                                <span class="sold-on absolute inset-0 flex items-center gap-1.5 text-success"><span class="size-2 rounded-full bg-success"></span>Disponível</span>
                                <span class="sold-off absolute inset-0 flex items-center gap-1.5 text-white/60"><span class="size-2 rounded-full bg-[#c0392b]"></span>Esgotado agora</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-[19px] font-bold">Preço e esgotado na hora</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-white/70">Acabou o prato ou mudou o preço? Altere no celular e o cardápio atualiza na próxima visita.</p>
                    </div>
                </article>

                {{-- 2. Which dishes get attention --}}
                <article class="reveal-up group flex flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-white/[0.09] to-white/[0.02] shadow-xl shadow-black/20 ring-1 ring-white/10 transition duration-300 hover:-translate-y-1.5 hover:ring-accent/50 hover:shadow-accent/10" style="--i: 1">
                    <div class="flex h-48 items-center justify-center bg-gradient-to-br from-accent/25 via-accent/[0.07] to-transparent p-5">
                        <div class="w-full max-w-[250px] space-y-3 rounded-xl bg-night/70 p-4 ring-1 ring-white/15">
                            <p class="text-[11px] font-bold tracking-wide text-white/50 uppercase">Mais assistidos</p>
                            @foreach ([['Espaguete', '92%'], ['Picanha', '68%'], ['Risoto', '45%']] as [$dish, $width])
                                <div class="flex items-center gap-3 text-[12px]">
                                    <span class="w-[62px] shrink-0 text-white/80">{{ $dish }}</span>
                                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-white/10"><span class="bar-fill block h-full rounded-full bg-gradient-to-r from-accent to-amber-300" style="--w: {{ $width }}; --d: {{ $loop->index * 0.25 }}s"></span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-[19px] font-bold">Saiba quais pratos chamam atenção</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-white/70">Veja visualizações e tempo assistido por prato e decida o que destacar.</p>
                    </div>
                </article>

                {{-- 3. Brand identity --}}
                <article class="reveal-up group flex flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-white/[0.09] to-white/[0.02] shadow-xl shadow-black/20 ring-1 ring-white/10 transition duration-300 hover:-translate-y-1.5 hover:ring-accent/50 hover:shadow-accent/10" style="--i: 2">
                    <div class="flex h-48 items-center justify-center bg-gradient-to-br from-accent/25 via-accent/[0.07] to-transparent p-5">
                        <div class="w-full max-w-[250px] rounded-xl bg-night/70 p-4 ring-1 ring-white/15">
                            <div class="flex items-center gap-3">
                                <span class="brand-swatch flex size-10 items-center justify-center rounded-full font-serif text-[18px] text-white">T</span>
                                <span class="font-serif text-[20px]">Trattoria</span>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="flex gap-2" aria-hidden="true">
                                    @foreach (['#FF6B3D', '#2E9E6B', '#3E7BFA', '#D94F8C'] as $swatch)
                                        <span class="size-5 rounded-full ring-2 ring-white/20" style="background: {{ $swatch }}"></span>
                                    @endforeach
                                </div>
                                <span class="brand-swatch rounded-full px-3 py-1 text-[11px] font-bold text-white">Massas</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-[19px] font-bold">A cara do seu restaurante</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-white/70">Logo, capa, cor de destaque e fonte. O cardápio fica com a sua identidade.</p>
                    </div>
                </article>

                {{-- Supporting features --}}
                @foreach ([
                    ['check', 'Nada vai ao ar sem você aprovar', 'Você revisa as versões e escolhe a que representa o prato de verdade.'],
                    ['qr', 'QR Code que nunca muda', 'Imprima uma vez e edite pratos, categorias e aparência quando quiser.'],
                    ['home', 'Sem app para o cliente', 'O cliente aponta a câmera e o cardápio abre no navegador, em segundos.'],
                ] as $i => [$icon, $title, $text])
                    <article class="reveal-up flex items-start gap-4 rounded-2xl bg-gradient-to-br from-white/[0.07] to-white/[0.02] p-5 ring-1 ring-white/10 transition duration-300 hover:-translate-y-1 hover:ring-accent/50" style="--i: {{ $i }}">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-[#ff9a5c] text-white shadow-lg shadow-accent/30"><x-ui.icon :name="$icon" class="size-6" /></span>
                        <div>
                            <h3 class="text-[16.5px] font-bold">{{ $title }}</h3>
                            <p class="mt-1.5 text-[14.5px] leading-relaxed text-white/70">{{ $text }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Planos ===== --}}
    @if (config('landing.show_prices') && $plans->isNotEmpty())
        <section id="planos" class="plans-stage scroll-mt-20 py-24" data-plans>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="inline-block rounded-full bg-accent/10 px-3.5 py-1 text-[12px] font-bold tracking-wide text-accent uppercase ring-1 ring-accent/25">Planos</span>
                    <h2 class="mt-5 font-serif text-[36px] leading-[1.1] text-balance sm:text-[48px]">Comece grátis. <span class="text-accent">Cresça quando fizer sentido.</span></h2>
                    <p class="mt-5 text-[17px] leading-relaxed text-pretty text-ink/65">Sem fidelidade: cancele quando quiser, pelo próprio painel.</p>
                </div>

                <div class="mt-14 grid items-stretch gap-6 {{ $plans->count() >= 3 ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
                    @foreach ($plans as $plan)
                        @php
                            $featured = $loop->count > 2 && $loop->iteration === 2;
                            $audience = $plan->price_cents === 0
                                ? 'Para começar e testar sem pagar nada'
                                : ($plan->dish_limit ? 'Para o dia a dia do seu restaurante' : 'Para quem quer tudo, sem limites');
                        @endphp
                        <div class="{{ $featured ? 'md:-my-4 md:scale-[1.03] bg-gradient-to-b from-[#2a1a12] to-night text-white shadow-2xl shadow-accent/25 ring-2 ring-accent' : 'bg-white text-ink shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-black/[0.04]' }} relative flex flex-col gap-6 rounded-3xl p-7 sm:p-8">
                            @if ($featured)
                                <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-accent px-4 py-1.5 text-[11.5px] font-bold tracking-wide whitespace-nowrap text-white uppercase shadow-lg shadow-accent/40">Recomendado</span>
                            @endif

                            <div>
                                <h3 class="font-serif text-[28px]">{{ $plan->name }}</h3>
                                <p class="mt-1 text-[14px] {{ $featured ? 'text-white/65' : 'text-ink/55' }}">{{ $audience }}</p>
                                <p class="mt-5">
                                    @if ($plan->price_cents > 0)
                                        <span class="text-[44px] leading-none font-bold">{{ $brl($plan->price_cents) }}</span><span class="text-[15px] {{ $featured ? 'text-white/60' : 'text-ink/50' }}">/mês</span>
                                        <span class="mt-2 block text-[13px] {{ $featured ? 'text-accent' : 'text-ink/50' }}">cerca de {{ $brl((int) round($plan->price_cents / 30)) }} por dia</span>
                                    @else
                                        <span class="text-[44px] leading-none font-bold">R$ 0</span><span class="text-[15px] text-ink/50"> para sempre</span>
                                        <span class="mt-2 block text-[13px] text-ink/50">sem cartão de crédito</span>
                                    @endif
                                </p>
                            </div>

                            <ul class="flex flex-1 flex-col gap-3 text-[15px] {{ $featured ? 'text-white/85' : 'text-ink/75' }}">
                                <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-[18px] {{ $featured ? 'text-accent' : 'text-success' }}" /><strong class="{{ $featured ? 'text-white' : 'text-ink' }}">{{ $plan->dish_limit ? 'Até '.$plan->dish_limit.' pratos' : 'Pratos ilimitados' }}</strong></li>
                                <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-[18px] {{ $featured ? 'text-accent' : 'text-success' }}" />
                                    <strong class="{{ $featured ? 'text-white' : 'text-ink' }}">
                                        @if ($plan->monthly_generations > 0)
                                            {{ $plan->monthly_generations }} vídeos do prato por mês
                                        @else
                                            {{ $plan->initial_generations }} vídeos para experimentar
                                        @endif
                                    </strong>
                                </li>
                                <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-[18px] {{ $featured ? 'text-accent' : 'text-success' }}" />Envie seus próprios vídeos, sem limite</li>
                                <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-[18px] {{ $featured ? 'text-accent' : 'text-success' }}" />{{ $metricsLabels[$plan->metricsLevel?->slug] ?? 'Métricas do cardápio' }}</li>
                                <li class="flex gap-3">
                                    @if ($plan->removes_branding)
                                        <x-ui.icon name="check" class="mt-0.5 size-[18px] {{ $featured ? 'text-accent' : 'text-success' }}" />Cardápio sem a marca {{ config('app.name') }}
                                    @else
                                        <x-ui.icon name="close" class="mt-0.5 size-[18px] text-ink/30" /><span class="text-ink/50">Exibe “feito com {{ config('app.name') }}”</span>
                                    @endif
                                </li>
                            </ul>

                            <div>
                                <a href="{{ route('register') }}" class="block rounded-xl px-5 py-3.5 text-center text-[15px] font-bold transition {{ $featured ? 'bg-accent text-white shadow-lg shadow-accent/30 hover:brightness-105' : 'border border-ink/15 hover:bg-ink/5' }}">
                                    {{ $plan->price_cents > 0 ? 'Começar com o '.$plan->name : 'Criar conta grátis' }}
                                </a>
                                @if ($plan->price_cents > 0)
                                    <p class="mt-2.5 text-center text-[12.5px] {{ $featured ? 'text-white/55' : 'text-ink/45' }}">Você cria a conta grátis e assina depois, no painel.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Trust: all three are true of the product today. --}}
                <ul class="mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-[14px] font-semibold text-ink/70" data-plans-trust>
                    @foreach (['Sem fidelidade', 'Cancele pelo painel', 'Pagamento seguro pela Stripe'] as $item)
                        <li class="flex items-center gap-2"><x-ui.icon name="check" class="size-[18px] text-success" />{{ $item }}</li>
                    @endforeach
                </ul>

                @if ($packages->isNotEmpty())
                    <div class="mx-auto mt-8 flex max-w-2xl items-center gap-4 rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-black/[0.04]" data-packages>
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-[#ff9a5c] text-white shadow-lg shadow-accent/25"><x-ui.icon name="sparkles" class="size-5" /></span>
                        <p class="text-[15px] leading-relaxed text-ink/70">Precisa de mais vídeos? Pacotes avulsos a partir de <strong class="text-ink">{{ $brl($packages->min('price_cents')) }}</strong>, que não expiram.</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ===== Perguntas frequentes ===== --}}
    <section id="perguntas" class="faq-stage scroll-mt-20 py-24">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
            <div class="lg:sticky lg:top-28 lg:self-start">
                <span class="inline-block rounded-full bg-accent/10 px-3.5 py-1 text-[12px] font-bold tracking-wide text-accent uppercase ring-1 ring-accent/25">Dúvidas</span>
                <h2 class="mt-5 font-serif text-[36px] leading-[1.1] text-balance sm:text-[46px]">Ficou com alguma <span class="text-accent">dúvida?</span></h2>
                <p class="mt-5 text-[17px] leading-relaxed text-pretty text-ink/65">Reunimos o que os donos de restaurante mais perguntam antes de começar.</p>

                <div class="mt-8 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-black/[0.04]">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-[#ff9a5c] text-white shadow-lg shadow-accent/25"><x-ui.icon name="support" class="size-5" /></span>
                    <p class="text-[14.5px] leading-relaxed text-ink/70">Não achou a resposta? <a href="{{ route('marketing.contact') }}" class="font-bold text-accent hover:underline">Fale com a gente</a>.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3" data-faq>
                @foreach ($faq as [$question, $answer])
                    <details class="group rounded-2xl bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-black/[0.04] transition duration-200 open:shadow-lg open:ring-accent/30">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5 text-[16.5px] font-bold [&::-webkit-details-marker]:hidden">
                            {{ $question }}
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent/10 text-[20px] leading-none text-accent transition duration-200 group-open:rotate-45 group-open:bg-accent group-open:text-white" aria-hidden="true">+</span>
                        </summary>
                        <p class="px-6 pb-6 text-[15.5px] leading-relaxed text-ink/70">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Chamada final ===== --}}
    <section class="cta-stage relative isolate overflow-hidden py-24 text-white">
        <picture class="pointer-events-none absolute inset-0 -z-20" aria-hidden="true">
            <source media="(max-width: 767px)" srcset="{{ asset('images/landing/hero-fundo-mobile.webp') }}" type="image/webp">
            <source srcset="{{ asset('images/landing/hero-fundo.webp') }}" type="image/webp">
            <img src="{{ asset('images/landing/hero-fundo.jpg') }}" alt="" width="2200" height="860" class="size-full object-cover object-[68%_center]" loading="lazy" decoding="async">
        </picture>
        <div class="cta-overlay pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>

        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-4 text-center sm:px-6">
            <h2 class="font-serif text-[36px] leading-[1.1] text-balance sm:text-[52px]">Seu primeiro prato em vídeo pode estar <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">no ar hoje.</span></h2>
            <p class="max-w-xl text-[17px] leading-relaxed text-pretty text-white/80 sm:text-[18px]">Crie sua conta grátis, cadastre o primeiro prato e veja o vídeo pronto em minutos.</p>
            <div class="mt-2 flex flex-col items-center gap-3">
                <a href="{{ route('register') }}" class="rounded-xl bg-accent px-8 py-4 text-[16px] font-bold text-white shadow-lg shadow-accent/30 transition hover:brightness-105">Criar conta grátis</a>
                <p class="text-[13px] text-white/60">Sem cartão de crédito. Cancele quando quiser.</p>
            </div>
        </div>
    </section>
</x-layouts.marketing>
