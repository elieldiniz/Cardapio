{{-- Landing page at "/" — the product's front door and the target of the "feito com" link. --}}
@use('App\Support\Money')
@php
    // The Grátis trial amount comes from the plan, so the copy follows the admin.
    $freeGenerations = (int) ($plans->firstWhere('price_cents', 0)?->initial_generations ?? 0);

    $metricsLabels = ['cardapio_total' => 'Veja quantas pessoas abrem o cardápio', 'por_prato' => 'Veja quais pratos chamam mais atenção', 'por_prato_com_tempo_assistido' => 'Veja por quanto tempo cada prato é assistido'];

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
        ['Como funciona o pedido?', 'O cliente monta o pedido no próprio cardápio (com tamanho, adicionais e observações), escolhe retirada ou entrega e envia. O pedido chega pronto no WhatsApp da loja e aparece no seu gestor de pedidos, onde você aceita, prepara e acompanha. Não há pagamento online: o Pix vai direto para a sua chave e você confere.'],
        ['Vocês cobram comissão por pedido?', 'Não. Você paga só o plano. O pedido, o cliente e o dinheiro são seus, sem percentual sobre as vendas.'],
        ['Como funcionam os 14 dias grátis?', 'Todo cadastro começa com 14 dias com todos os recursos liberados, sem cartão de crédito. Depois disso, se você não assinar, a conta continua no plano Grátis, com cardápio e pedidos. Caixa, PDV, cupons e outros recursos ficam guardados e voltam quando você assinar um plano que os inclua. Nada é apagado.'],
        ['Serve para entrega e para retirada?', 'Os dois. Na entrega você pode cobrar taxa por bairro, por distância ou combinar pelo WhatsApp, definir pedido mínimo e frete grátis acima de um valor, e passar a corrida para um motoboy com link próprio.'],
        ['Como o vídeo do prato é criado?', 'Você envia 1 foto do prato, escolhe um estilo (ou descreve do seu jeito) e nós criamos um clipe curto e vertical. Você confere o resultado e só vai para o cardápio o que você aprovar. Também dá para enviar um vídeo seu.'],
        ['O que é uma “geração”?', 'Cada vídeo criado a partir da sua foto consome 1 geração. Você ganha gerações grátis ao se cadastrar e um brinde ao assinar pela primeira vez; depois, compra pacotes avulsos, que não expiram. Se algo falhar, a geração não é descontada. Não gostou do resultado? É só gerar de novo, o que consome mais 1 geração. Enviar vídeo próprio nunca consome gerações.'],
        ['Posso cancelar quando quiser?', 'Sim. A assinatura é mensal e você cancela pelo próprio painel. O cardápio continua no ar no plano Grátis.'],
        ['Quais formas de pagamento são aceitas na assinatura?', 'Cartão de crédito, com pagamento processado com segurança pela Stripe.'],
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
                <h1 class="hero-rise font-display text-[38px] leading-[1.08] text-balance text-white sm:text-[56px]" style="--d: 0.1s">Faça o cliente <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">desejar o prato</span> e pedir na hora.</h1>
                <p class="hero-rise mt-6 max-w-xl text-[17px] leading-relaxed text-pretty text-white/75 sm:text-[18px]" style="--d: 0.3s">O cliente escaneia o QR Code, vê cada prato em vídeo e faz o pedido pelo celular. Você recebe tudo pronto no WhatsApp, com entrega e caixa organizados.</p>

                <div class="hero-rise mt-9 flex flex-wrap gap-3" style="--d: 0.5s">
                    <a href="{{ route('register') }}" class="rounded-[10px] bg-accent px-6 py-3.5 text-[15px] font-bold text-white shadow-lg shadow-accent/30 transition hover:brightness-95">Testar 14 dias grátis</a>
                    <a href="#como-funciona" class="rounded-[10px] border border-white/20 px-6 py-3.5 text-[15px] font-bold text-white transition hover:bg-white/5">Ver como funciona</a>
                </div>
                <p class="hero-rise mt-4 text-[13px] text-white/55" style="--d: 0.6s">14 dias com tudo liberado, sem cartão de crédito. Depois, o plano Grátis continua.</p>

                <ul class="hero-rise mt-7 flex flex-wrap gap-x-5 gap-y-2 text-[13.5px] text-white/80" style="--d: 0.75s">
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Sem app para o cliente</li>
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Pedido pronto no WhatsApp</li>
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-4 text-accent" />Sem comissão por pedido</li>
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

    {{-- ===== Faixa dos 14 dias grátis ===== --}}
    <section class="bg-accent/10 py-6" data-trial-strip>
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-center sm:px-6 md:flex-row md:text-left">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-[#ff9a5c] text-white shadow-lg shadow-accent/25"><x-ui.icon name="sparkles" class="size-5" /></span>
                <p class="text-[15.5px] leading-snug text-ink/80"><strong class="text-ink">14 dias com tudo liberado.</strong> Pedidos, caixa, PDV, cupons, fidelidade e motoboys, sem cartão. Depois, o plano Grátis continua com cardápio e pedidos.</p>
            </div>
            <a href="{{ route('register') }}" class="shrink-0 rounded-[10px] bg-accent px-6 py-3 text-[15px] font-bold text-white shadow-lg shadow-accent/25 transition hover:brightness-95">Começar o teste grátis</a>
        </div>
    </section>


    {{-- ===== Como funciona ===== --}}
    <section id="como-funciona" class="scroll-mt-20 bg-white py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">Do QR Code ao pedido no seu WhatsApp em <span class="text-accent">3 passos</span></h2>
                <p class="mt-4 text-[17px] leading-relaxed text-pretty text-ink/60">Sem app, sem site para montar e sem digitar pedido na mão.</p>
            </div>

            <ol class="relative mt-16 grid gap-12 md:grid-cols-3 md:gap-6" data-steps>
                <span class="absolute top-7 right-[16.66%] left-[16.66%] hidden border-t-2 border-dashed border-accent/35 md:block" aria-hidden="true"></span>

                @foreach ([
                    ['O cliente escaneia e vê o prato em vídeo', 'Aponta a câmera para o QR Code da mesa, do balcão ou do Instagram. O cardápio abre na hora.'],
                    ['Monta o pedido do jeito dele', 'Escolhe tamanho, sabores e adicionais, retirada ou entrega, e como vai pagar.'],
                    ['Você recebe tudo pronto', 'O pedido chega no WhatsApp e no gestor. Você aceita, prepara e acompanha até a entrega.'],
                ] as [$title, $text])
                    <li class="reveal-up relative flex flex-col items-center gap-5 text-center" style="--i: {{ $loop->index }}">
                        <span class="relative z-10 flex size-14 items-center justify-center rounded-full bg-accent font-serif text-[24px] text-white ring-[10px] ring-white">{{ $loop->iteration }}</span>

                        <div class="flex h-48 w-full items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-night to-[#3a2416] p-4 text-white shadow-lg ring-1 ring-black/5">
                            @if ($loop->first)
                                <div class="flex items-center gap-4">
                                    <span class="flex size-24 items-center justify-center rounded-2xl bg-white p-2.5 text-night"><x-ui.icon name="qr" class="size-full" /></span>
                                    <span class="text-[22px] text-accent" aria-hidden="true">→</span>
                                    <div class="relative h-32 w-[74px] overflow-hidden rounded-xl border-2 border-black ring-1 ring-white/20">
                                        <img src="{{ $heroDish['image'] }}" alt="" class="size-full object-cover" loading="lazy">
                                        <span class="absolute inset-x-1 bottom-1 rounded bg-black/55 px-1 py-0.5 text-[8px] leading-tight font-bold">{{ $heroDish['price'] }}</span>
                                    </div>
                                </div>
                            @elseif ($loop->index === 1)
                                <div class="w-full max-w-[240px] space-y-2 rounded-xl bg-white/10 p-3 text-left text-[11.5px] text-white/85 ring-1 ring-white/15">
                                    <p class="text-[13px] font-semibold text-white">Espaguete à Bolonhesa</p>
                                    <div class="flex gap-1.5"><span class="rounded-full bg-accent px-2 py-0.5 font-bold text-white">Médio</span><span class="rounded-full bg-white/15 px-2 py-0.5">Grande</span></div>
                                    <p class="text-white/65">+ Queijo extra · + Bacon</p>
                                    <div class="flex gap-1.5 pt-1"><span class="rounded-full bg-white/15 px-2 py-0.5">Retirar</span><span class="rounded-full bg-accent px-2 py-0.5 font-bold text-white">Entrega</span><span class="rounded-full bg-white/15 px-2 py-0.5">Pix</span></div>
                                    <p class="rounded-lg bg-accent py-1.5 text-center text-[12px] font-bold text-white">Pedir pelo WhatsApp · R$ 52,90</p>
                                </div>
                            @else
                                <div class="w-full max-w-[240px] space-y-1 rounded-xl bg-[#d9fdd3] p-3 text-left text-[11.5px] leading-snug text-ink shadow-lg">
                                    <p class="font-bold">Pedido D-79CF7</p>
                                    <p>1× Espaguete · Médio<br>+ Queijo extra, Bacon</p>
                                    <p>Entrega: Rua das Flores, 120</p>
                                    <p class="font-bold">Total R$ 52,90 · Pix</p>
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

            <div class="mt-14 flex flex-col items-center gap-2">
                <a href="{{ route('register') }}" class="rounded-xl bg-accent px-8 py-4 text-[16px] font-bold text-white shadow-lg shadow-accent/30 transition hover:brightness-105">Testar 14 dias grátis</a>
                <p class="text-[13px] text-ink/50">Sem cartão de crédito. Pronto em poucos minutos.</p>
            </div>
        </div>
    </section>

    {{-- ===== Da foto ao vídeo ===== --}}
    <section class="bg-sand py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">A foto que você já tem vira um vídeo que <span class="text-accent">dá água na boca</span></h2>
                <p class="mx-auto mt-5 max-w-xl text-[17px] leading-relaxed text-pretty text-ink/65">Sem câmera, sem edição e sem equipe. Você envia a foto, confere o resultado, e só vai ao ar o que você aprovar.</p>
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
                            @foreach (['Envie a foto do prato', 'Escolha um estilo e criamos o vídeo em minutos', 'Você confere e aprova'] as $step)
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
                        ['sparkles', 'Escolha o estilo do vídeo', 'Use um estilo pronto ou descreva do seu jeito como a câmera deve se mover.'],
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

    {{-- ===== Recursos ===== --}}
    <section id="recursos" class="features-stage relative isolate scroll-mt-20 overflow-hidden py-24 text-white">
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-accent/70 to-transparent" aria-hidden="true"></div>
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="inline-block rounded-full bg-accent/15 px-3.5 py-1 text-[12px] font-bold tracking-wide text-accent uppercase ring-1 ring-accent/30">Tudo em um só lugar</span>
                <h2 class="mt-5 font-serif text-[36px] leading-[1.1] text-balance sm:text-[50px]">Do pedido ao caixa, <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">sem planilha e sem confusão.</span></h2>
                <p class="mt-5 text-[17px] leading-relaxed text-pretty text-white/75 sm:text-[18px]">Tudo pelo celular, no meio do expediente: sem técnico, sem designer e sem sistema caro.</p>
            </div>

            <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3" data-features>
                @foreach ([
                    ['orders', 'Pedidos direto do cardápio', 'Carrinho com tamanhos, adicionais, pizza por sabores e produtos por kg. O servidor confere cada preço.'],
                    ['receipt', 'Gestor de pedidos', 'Novos, em preparo, em rota e finalizados num quadro. Aviso sonoro, comanda para impressora e link de acompanhamento para o cliente.'],
                    ['bike', 'Entrega do seu jeito', 'Taxa por bairro ou por distância, pedido mínimo, frete grátis e motoboy com link próprio e acerto no caixa.'],
                    ['cash', 'Caixa, fiado e relatório', 'Abra e feche o caixa, controle o fiado e veja vendas por forma de pagamento e por origem.'],
                    ['pos', 'PDV de balcão', 'Venda rápida no balcão, com pagamento dividido e tudo entrando no mesmo caixa.'],
                    ['tag', 'Cupons e fidelidade', 'Códigos de desconto com link para divulgar e cartão de selos que traz o cliente de volta.'],
                    ['chart', 'Saiba o que vende', 'Funil do cardápio ao pedido, produtos mais pedidos e horário de pico.'],
                    ['sparkles', 'Foto vira vídeo', 'Cada prato ganha um clipe curto com IA, e só vai ao ar o que você aprovar.'],
                    ['qr', 'Sua marca, QR Code fixo', 'Logo, cor e fonte do restaurante, e um QR Code que você imprime uma vez só, com display de mesa.'],
                ] as $i => [$icon, $title, $text])
                    <article class="reveal-up flex items-start gap-4 rounded-2xl bg-gradient-to-br from-white/[0.07] to-white/[0.02] p-5 ring-1 ring-white/10 transition duration-300 hover:-translate-y-1 hover:ring-accent/40" style="--i: {{ $i % 3 }}">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-[#ff9a5c] text-white shadow-lg shadow-accent/30"><x-ui.icon :name="$icon" class="size-6" /></span>
                        <div>
                            <h3 class="text-[17px] font-bold">{{ $title }}</h3>
                            <p class="mt-1.5 text-[14.5px] leading-relaxed text-white/70">{{ $text }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>


    {{-- ===== Comparativo de mensagens ===== --}}
    <section id="comparativo" class="scroll-mt-20 bg-white py-20" data-comparison>
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">11 mensagens para fechar um pedido, ou <span class="text-accent">1 mensagem</span> pronta</h2>
                <p class="mx-auto mt-5 max-w-xl text-[17px] leading-relaxed text-pretty text-ink/65">No pedido por conversa, você pergunta tudo. No cardápio do Degusta o cliente escolhe, e o pedido chega completo no seu WhatsApp.</p>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-2">
                <div class="rounded-3xl bg-sand p-6 ring-1 ring-black/5" data-comparison-chat>
                    <p class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Pedido por conversa · 11 mensagens</p>
                    <ol class="mt-4 flex flex-col gap-2 text-[14px] text-ink/75">
                        @foreach (['Oi, vocês estão abertos?', 'Estamos! Qual o seu pedido?', 'Quero um X-Bacon', 'Qual tamanho? Quer adicional?', 'Médio, com bacon extra', 'Mais alguma coisa?', 'Uma Coca 2L', 'Qual o endereço?', 'Rua das Flores, 120', 'Total R$ 48,90 + entrega. Pix ou dinheiro?', 'Pix'] as $index => $line)
                            <li class="flex gap-2 {{ $index % 2 === 0 ? '' : 'justify-end' }}"><span class="rounded-2xl px-3 py-1.5 {{ $index % 2 === 0 ? 'bg-white' : 'bg-accent/15' }}">{{ $line }}</span></li>
                        @endforeach
                    </ol>
                </div>
                <div class="rounded-3xl bg-night p-6 text-white shadow-xl" data-comparison-one>
                    <p class="text-[13px] font-bold tracking-wide text-accent uppercase">Com o Degusta · 1 mensagem</p>
                    <div class="mt-4 rounded-2xl bg-white/10 p-4 text-[14px] leading-relaxed text-white/90">
                        <p class="font-bold">Pedido D-79CF7</p>
                        <p class="mt-1">1× X-Bacon · Médio<br>+ Bacon extra<br>1× Coca-Cola 2L</p>
                        <p class="mt-2">Entrega: Rua das Flores, 120 · Centro</p>
                        <p class="mt-2 font-bold">Total: R$ 48,90 · Pix</p>
                    </div>
                    <p class="mt-4 text-[14px] text-white/70">Itens, adicionais, endereço, taxa e forma de pagamento já vêm certos. Você só confirma.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Calculadora de comissão ===== --}}
    @php
        $commissionRates = config('landing.commission_rates');
        $defaultSales = (int) config('landing.commission_default_sales');
    @endphp
    <section id="comissao" class="scroll-mt-20 bg-sand py-20" data-commission data-rates="{{ implode(',', $commissionRates) }}">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight text-balance sm:text-[44px]">Sem comissão: quanto os apps de delivery <span class="text-accent">levam de você</span>?</h2>
                <p class="mx-auto mt-5 max-w-xl text-[17px] leading-relaxed text-pretty text-ink/65">Digite quanto você vende por mês e veja a comissão. No Degusta você paga só o plano: o pedido é seu, e o Pix cai direto na sua conta.</p>
            </div>

            <div class="mt-10 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 sm:p-8">
                <label class="flex flex-col gap-2 text-[14px] font-bold text-ink">
                    Quanto você vende por mês (R$)
                    <input type="number" min="0" step="100" value="{{ $defaultSales }}" inputmode="numeric" class="w-full rounded-xl border border-ink/15 px-4 py-3 text-[18px] font-bold" data-commission-sales>
                </label>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    @foreach ($commissionRates as $rate)
                        <div class="rounded-2xl bg-sand p-4 text-center" data-commission-card="{{ $rate }}">
                            <p class="text-[12px] font-bold tracking-wide text-ink/50 uppercase">App que cobra {{ $rate }}%</p>
                            <p class="mt-1 text-[24px] font-extrabold text-accent" data-commission-month>{{ Money::brl($defaultSales * $rate / 100) }}</p>
                            <p class="text-[12.5px] text-ink/55">por mês · <span data-commission-year>{{ Money::brl($defaultSales * $rate / 100 * 12) }}</span> por ano</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-center text-[12.5px] text-ink/50">Comissão = vendas × percentual. Os percentuais são exemplos do que os apps costumam cobrar.</p>
            </div>
        </div>
        <script>
            (() => {
                const root = document.querySelector('[data-commission]');
                const input = root?.querySelector('[data-commission-sales]');
                const brl = (value) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

                input?.addEventListener('input', () => {
                    const sales = Math.max(0, Number(input.value) || 0);

                    root.querySelectorAll('[data-commission-card]').forEach((card) => {
                        const commission = sales * Number(card.dataset.commissionCard) / 100;

                        card.querySelector('[data-commission-month]').textContent = brl(commission);
                        card.querySelector('[data-commission-year]').textContent = brl(commission * 12);
                    });
                });
            })();
        </script>
    </section>

    {{-- ===== Planos ===== --}}
    @if (config('landing.show_prices') && $plans->isNotEmpty())
        <section id="planos" class="plans-stage scroll-mt-20 py-24" data-plans>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="inline-block rounded-full bg-accent/10 px-3.5 py-1 text-[12px] font-bold tracking-wide text-accent uppercase ring-1 ring-accent/25">Planos</span>
                    <h2 class="mt-5 font-serif text-[36px] leading-[1.1] text-balance sm:text-[48px]">Comece grátis. <span class="text-accent">Cresça quando fizer sentido.</span></h2>
                    <p class="mt-5 text-[17px] leading-relaxed text-pretty text-ink/65">Sem fidelidade: cancele quando quiser, pelo próprio painel.</p>
                    <p class="mt-3 inline-block rounded-full bg-accent/10 px-4 py-1.5 text-[14px] font-bold text-accent ring-1 ring-accent/25" data-trial-offer>🎁 Todo cadastro começa com 14 dias de tudo liberado, sem cartão.</p>
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
                                    @else
                                        <span class="text-[44px] leading-none font-bold">R$ 0</span><span class="text-[15px] text-ink/50"> para sempre</span>
                                        <span class="mt-2 block text-[13px] text-ink/50">sem cartão de crédito</span>
                                    @endif
                                </p>
                            </div>

                            @php
                                // Every card lists everything the plan gives, so each one can be read on its own.
                                $check = $featured ? 'text-accent' : 'text-success';
                                $lines = [[$plan->dish_limit ? 'Até '.$plan->dish_limit.' pratos' : 'Pratos ilimitados', true]];

                                if ($plan->monthly_generations > 0) {
                                    $lines[] = [$plan->monthly_generations.' vídeos do prato por mês', true];
                                } elseif ($plan->first_subscription_bonus > 0) {
                                    $lines[] = ['+'.$plan->first_subscription_bonus.' vídeos de brinde ao assinar', true];
                                } elseif ($plan->initial_generations > 0) {
                                    $lines[] = [$plan->initial_generations.' vídeos para experimentar', true];
                                } else {
                                    $lines[] = ['Vídeos em pacotes avulsos', true];
                                }

                                array_push($lines,
                                    ['Envie seus próprios vídeos, sem limite', false],
                                    ['QR Code que nunca muda, com display de mesa para imprimir', false],
                                );

                                if ($plan->qr_customization) {
                                    $lines[] = ['Personalize o QR Code e o display: estilo, logo, modelos e textos', false];
                                }

                                // What the plan includes for taking orders (the free plan has all of it during the 14-day trial).
                                $lines[] = ['Receba pedidos pelo cardápio, com gestor de pedidos e comanda', true];

                                foreach (['cash' => 'Caixa, fiado e relatório de vendas', 'pdv' => 'PDV para vender no balcão', 'promo_codes' => 'Cupons de desconto', 'loyalty' => 'Cartão fidelidade', 'couriers' => 'Motoboys com link próprio e acerto'] as $feature => $featureLabel) {
                                    if ($plan->hasFeature($feature)) {
                                        $lines[] = [$featureLabel, false];
                                    }
                                }

                                array_push($lines,
                                    ['Logo, capa, cor e fonte do seu restaurante', false],
                                    ['Preço e “esgotado” atualizados na hora', false],
                                    [$metricsLabels[$plan->metricsLevel?->slug] ?? 'Métricas do cardápio', false],
                                );

                                if ($plan->removes_branding) {
                                    $lines[] = ['Cardápio sem a marca '.config('app.name'), false];
                                }

                                if ($packages->isNotEmpty()) {
                                    $lines[] = ['Compre mais vídeos quando quiser, em pacotes sem validade', false];
                                }

                                $lines[] = ['Suporte pelo painel', false];
                            @endphp

                            <ul class="flex flex-1 flex-col gap-3 text-[15px] {{ $featured ? 'text-white/85' : 'text-ink/75' }}">
                                @foreach ($lines as [$text, $strong])
                                    <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-[18px] shrink-0 {{ $check }}" />@if ($strong)<strong class="{{ $featured ? 'text-white' : 'text-ink' }}">{{ $text }}</strong>@else<span>{{ $text }}</span>@endif</li>
                                @endforeach
                                @unless ($plan->removes_branding)
                                    <li class="flex gap-3"><x-ui.icon name="close" class="mt-0.5 size-[18px] shrink-0 text-ink/30" /><span class="text-ink/50">Exibe “feito com {{ config('app.name') }}”</span></li>
                                @endunless
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

        {{-- What each plan keeps once the 14-day trial is over. --}}
        <section class="bg-white py-16" data-plan-matrix>
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <h3 class="text-center font-serif text-[28px] leading-tight sm:text-[34px]">O que cada plano inclui</h3>
                <p class="mx-auto mt-3 max-w-xl text-center text-[15px] text-ink/60">Nos primeiros 14 dias todo cadastro tem tudo liberado. Depois, vale a tabela abaixo.</p>
                <div class="mt-8 overflow-x-auto rounded-2xl ring-1 ring-black/[0.06]">
                    <table class="w-full min-w-[520px] text-left text-[14.5px]">
                        <thead class="bg-sand text-[13px] font-bold text-ink/70">
                            <tr><th class="px-5 py-3.5">Recurso</th>@foreach ($plans as $plan)<th class="px-4 py-3.5 text-center">{{ $plan->name }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.05]">
                            @foreach ([
                                ['Cardápio, pedidos e gestor de pedidos', 'orders'],
                                ['Caixa, fiado e relatório', 'cash'],
                                ['PDV de balcão', 'pdv'],
                                ['Cupons de desconto', 'promo_codes'],
                                ['Cartão fidelidade', 'loyalty'],
                                ['Motoboys com link e acerto', 'couriers'],
                            ] as [$label, $feature])
                                <tr>
                                    <td class="px-5 py-3">{{ $label }}</td>
                                    @foreach ($plans as $plan)
                                        <td class="px-4 py-3 text-center">{!! $plan->hasFeature($feature) ? '<span class="font-bold text-success" aria-label="Incluído">✓</span>' : '<span class="text-ink/30" aria-label="Não incluído">—</span>' !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr>
                                <td class="px-5 py-3">Estatísticas</td>
                                @foreach ($plans as $plan)
                                    <td class="px-4 py-3 text-center text-[13px] text-ink/70">{{ ['cardapio_total' => 'Total', 'por_prato' => 'Por prato', 'por_prato_com_tempo_assistido' => 'Por prato + tempo'][$plan->metricsLevel?->slug] ?? '—' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <td class="px-5 py-3">Produtos no cardápio</td>
                                @foreach ($plans as $plan)
                                    <td class="px-4 py-3 text-center text-[13px] text-ink/70">{{ $plan->dish_limit ?? 'Ilimitados' }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-center text-[13px] text-ink/50">Sem comissão por pedido em nenhum plano.</p>
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
            <h2 class="font-serif text-[36px] leading-[1.1] text-balance sm:text-[52px]">Seu cardápio em vídeo, já recebendo pedidos, pode estar <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">no ar hoje.</span></h2>
            <p class="max-w-xl text-[17px] leading-relaxed text-pretty text-white/80 sm:text-[18px]">Crie a conta, cadastre o primeiro prato e teste 14 dias com tudo liberado: pedidos, caixa, cupons e motoboys.</p>
            <div class="mt-2 flex flex-col items-center gap-3">
                <a href="{{ route('register') }}" class="rounded-xl bg-accent px-8 py-4 text-[16px] font-bold text-white shadow-lg shadow-accent/30 transition hover:brightness-105">Testar 14 dias grátis</a>
                <p class="text-[13px] text-white/60">Sem cartão de crédito. Depois dos 14 dias, o plano Grátis continua.</p>
            </div>
        </div>
    </section>
</x-layouts.marketing>
