{{-- Landing page at "/" — the product's front door and the target of the "feito com" link. --}}
@use('App\Support\Money')
@php
    $metricsLabels = ['cardapio_total' => 'Visualizações do cardápio', 'por_prato' => 'Visualizações por prato', 'por_prato_com_tempo_assistido' => 'Visualizações e tempo assistido por prato'];

    $heroDish = [
        // The photo is the video's first frame: this exact photo became this video.
        'image' => asset('images/landing/hero-prato.jpg'),
        'video' => asset('videos/landing/hero-prato.mp4'),
        'name' => 'Espaguete à Bolonhesa',
        'price' => 'R$ 42,90',
        'desc' => 'Molho de carne cozido lentamente e parmesão ralado na hora.',
        'badge' => 'Mais pedido',
    ];

    $features = [
        ['sparkles', 'Vídeo por IA a partir de uma foto', 'Envie a foto do prato e receba vídeos curtos, sempre no mesmo estilo profissional. Com 3 a 4 ângulos, o prato gira de verdade.'],
        ['media', 'Ou envie o seu próprio vídeo', 'Já tem vídeos gravados? Envie direto do celular, sem gastar gerações.'],
        ['check', 'Nada vai ao ar sem você aprovar', 'Você revisa as variações e escolhe a que representa o prato de verdade.'],
        ['dishes', 'Preço e “esgotado” na hora', 'Mudou o preço ou acabou o prato? Altere no celular e o cardápio atualiza na próxima visita.'],
        ['qr', 'QR Code que nunca muda', 'Imprima uma vez. Pode editar pratos, categorias e aparência à vontade sem reimprimir.'],
        ['views', 'Saiba quais pratos chamam atenção', 'Veja visualizações e tempo assistido por prato e decida o que destacar.'],
        ['appearance', 'A cara do seu restaurante', 'Logo, capa, cor de destaque e fonte. O cardápio fica com a sua identidade.'],
        ['home', 'Sem app, sem cadastro para o cliente', 'O cliente aponta a câmera e o cardápio abre no navegador, em segundos.'],
    ];

    $faq = [
        ['O cliente precisa baixar algum aplicativo?', 'Não. Ele aponta a câmera do celular para o QR Code da mesa e o cardápio abre direto no navegador, sem cadastro.'],
        ['Como a IA cria o vídeo do prato?', 'Você envia 1 foto (ou 3 a 4 fotos de ângulos diferentes, para um resultado melhor). A IA gera clipes curtos e verticais do prato, sempre no mesmo estilo. Você escolhe a variação que mais gostar e só ela vai para o cardápio.'],
        ['O que é uma “geração”?', 'Cada vídeo criado pela IA consome 1 geração. Pedir 2 variações do mesmo prato consome 2 gerações. Se a IA falhar, a geração não é descontada.'],
        ['E se acabarem as gerações do mês?', 'Você pode comprar um pacote avulso de gerações, que não expira, ou mudar de plano. Enviar vídeo próprio nunca consome gerações.'],
        ['Dá para fazer pedido pelo cardápio?', 'Não. O cardápio é para ver os pratos em vídeo; o pedido continua sendo feito com o garçom, como o cliente já está acostumado.'],
        ['Posso cancelar quando quiser?', 'Sim. A assinatura é mensal e você cancela pelo próprio painel. O cardápio continua no ar no plano Grátis.'],
        ['Quais formas de pagamento são aceitas?', 'Cartão de crédito, com pagamento processado com segurança pela Stripe.'],
    ];
@endphp

<x-layouts.marketing>
    {{-- ===== Hero ===== --}}
    <section class="relative overflow-hidden bg-night text-white">
        <div class="pointer-events-none absolute -top-40 -right-40 size-[520px] rounded-full bg-accent/25 blur-3xl" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 md:grid-cols-[1.15fr_0.85fr] md:py-24">
            <div>
                <h1 class="font-display text-[38px] leading-[1.1] sm:text-[52px]">Seu cardápio em vídeo, direto do QR Code da mesa.</h1>
                <p class="mt-5 max-w-xl text-[17px] leading-relaxed text-white/70">O cliente escaneia e vê cada prato “vivo”, rolando como nos Reels. Você só envia a foto: a IA faz o vídeo.</p>

                <ul class="mt-7 flex flex-col gap-3 text-[15px] text-white/85">
                    <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-5 text-accent" /><span><strong class="text-white">Sem gravar nada</strong> — da foto ao vídeo em minutos.</span></li>
                    <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-5 text-accent" /><span><strong class="text-white">Sem app para o cliente</strong> — abre no navegador do celular.</span></li>
                    <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-5 text-accent" /><span><strong class="text-white">Preço e esgotado na hora</strong> — sem reimprimir cardápio.</span></li>
                </ul>

                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="rounded-[10px] bg-accent px-6 py-3.5 text-[15px] font-bold text-white shadow-lg shadow-accent/30 hover:brightness-95">Criar conta grátis</a>
                    <a href="#como-funciona" class="rounded-[10px] border border-white/20 px-6 py-3.5 text-[15px] font-bold text-white hover:bg-white/5">Ver como funciona</a>
                </div>
                <p class="mt-4 text-[13px] text-white/50">Plano grátis com 5 vídeos por IA para experimentar. Sem cartão de crédito.</p>
            </div>

            {{-- Phone mockup of the client feed --}}
            <div class="flex justify-center" aria-hidden="true">
                <div class="relative h-[560px] w-[280px] overflow-hidden rounded-[42px] border-[7px] border-black bg-black shadow-2xl shadow-black/60 ring-1 ring-white/10">
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
                <h2 class="font-serif text-[34px] leading-tight sm:text-[42px]">Da foto ao vídeo, sem gravar nada</h2>
                <p class="mt-4 text-[16px] text-ink/60">Escolha como quer mostrar cada prato. Em todos os casos, só vai ao ar o que você aprovar.</p>
            </div>

            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['1 foto', 'Um clipe curto com aproximação suave e movimento de câmera, no mesmo estilo em todo o cardápio.', '1 foto → vídeo'],
                    ['3 a 4 ângulos', 'Com fotos de lados diferentes, o prato gira de verdade na tela. É o resultado mais impressionante.', 'ângulos → giro'],
                    ['Seu próprio vídeo', 'Já gravou? Envie direto do celular. Vertical, de 5 a 15 segundos, sem gastar gerações.', 'vídeo → cardápio'],
                ] as [$title, $text, $tag])
                    <div class="flex flex-col gap-4 rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
                        <div class="flex h-40 items-center justify-center gap-3 rounded-xl bg-gradient-to-br from-night to-[#3a2416]">
                            @if ($loop->first)
                                {{-- The real before/after: the photo sent, and the video it became. --}}
                                <img src="{{ $heroDish['image'] }}" alt="Foto enviada: prato de espaguete" class="h-32 w-[74px] rounded-lg object-cover shadow-lg" loading="lazy">
                                <span class="text-white/40">→</span>
                                <video src="{{ $heroDish['video'] }}" poster="{{ $heroDish['image'] }}" class="h-32 w-[74px] rounded-lg object-cover shadow-lg ring-2 ring-accent" autoplay muted loop playsinline preload="metadata" aria-label="Vídeo gerado a partir da foto"></video>
                            @else
                                <span class="flex size-14 items-center justify-center rounded-lg bg-white/10 text-white/80"><x-ui.icon name="{{ $loop->last ? 'upload' : 'appearance' }}" class="size-6" /></span>
                                <span class="text-white/40">→</span>
                                <span class="flex size-14 items-center justify-center rounded-lg bg-accent text-white"><x-ui.icon name="media" class="size-6" /></span>
                            @endif
                        </div>
                        <span class="w-fit rounded-full bg-accent/10 px-3 py-1 text-[12px] font-bold text-accent">{{ $tag }}</span>
                        <h3 class="text-[18px] font-bold">{{ $title }}</h3>
                        <p class="text-[14.5px] leading-relaxed text-ink/60">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Como funciona ===== --}}
    <section id="como-funciona" class="scroll-mt-20 bg-white py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <h2 class="text-center font-serif text-[34px] sm:text-[42px]">Como funciona</h2>
            <ol class="mt-12 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['Cadastre seus pratos', 'Nome, preço, fotos, selos e variações. Organize por categorias no painel, pelo celular.'],
                    ['Gere os vídeos', 'Envie a foto e a IA cria as variações. Você escolhe a melhor e aprova.'],
                    ['Imprima o QR Code', 'Coloque nas mesas. O cliente escaneia e vê o cardápio em vídeo na hora.'],
                ] as [$title, $text])
                    <li class="flex flex-col items-center gap-3 text-center">
                        <span class="flex size-14 items-center justify-center rounded-full bg-accent font-serif text-[24px] text-white">{{ $loop->iteration }}</span>
                        <h3 class="text-[18px] font-bold">{{ $title }}</h3>
                        <p class="max-w-xs text-[14.5px] leading-relaxed text-ink/60">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== Recursos ===== --}}
    <section class="bg-night py-20 text-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-serif text-[34px] leading-tight sm:text-[42px]">Tudo o que o seu cardápio precisa</h2>
                <p class="mt-4 text-[16px] text-white/60">Feito para o dono do restaurante usar no celular, no meio do expediente.</p>
            </div>
            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($features as [$icon, $title, $text])
                    <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-accent/15 text-accent"><x-ui.icon :name="$icon" class="size-5" /></span>
                        <h3 class="text-[16px] font-bold">{{ $title }}</h3>
                        <p class="text-[14px] leading-relaxed text-white/60">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Planos ===== --}}
    @if (config('landing.show_prices') && $plans->isNotEmpty())
        <section id="planos" class="scroll-mt-20 bg-sand py-20" data-plans>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-serif text-[34px] sm:text-[42px]">Planos</h2>
                    <p class="mt-4 text-[16px] text-ink/60">Comece grátis. Mude de plano ou cancele quando quiser.</p>
                </div>

                <div class="mt-12 grid gap-5 {{ $plans->count() >= 3 ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
                    @foreach ($plans as $plan)
                        @php $featured = $loop->count > 2 && $loop->iteration === 2; @endphp
                        <div class="relative flex flex-col gap-5 rounded-2xl bg-white p-7 shadow-[0_1px_3px_rgba(0,0,0,0.06)] {{ $featured ? 'ring-2 ring-accent' : '' }}">
                            @if ($featured)
                                <span class="absolute -top-3 left-7 rounded-full bg-accent px-3 py-1 text-[11px] font-bold tracking-wide text-white uppercase">Mais escolhido</span>
                            @endif
                            <div>
                                <h3 class="font-serif text-[26px]">{{ $plan->name }}</h3>
                                <p class="mt-2">
                                    @if ($plan->price_cents > 0)
                                        <span class="text-[34px] font-bold">{{ Money::brlFromCents($plan->price_cents) }}</span><span class="text-[14px] text-ink/50">/mês</span>
                                    @else
                                        <span class="text-[34px] font-bold">R$ 0</span><span class="text-[14px] text-ink/50"> para sempre</span>
                                    @endif
                                </p>
                            </div>
                            <ul class="flex flex-1 flex-col gap-2.5 text-[14.5px] text-ink/75">
                                <li class="flex gap-2.5"><x-ui.icon name="check" class="mt-0.5 size-4 text-success" />{{ $plan->dish_limit ? 'Até '.$plan->dish_limit.' pratos' : 'Pratos ilimitados' }}</li>
                                <li class="flex gap-2.5"><x-ui.icon name="check" class="mt-0.5 size-4 text-success" />
                                    @if ($plan->monthly_generations > 0)
                                        {{ $plan->monthly_generations }} vídeos por IA por mês
                                    @else
                                        {{ $plan->initial_generations }} vídeos por IA para experimentar
                                    @endif
                                </li>
                                <li class="flex gap-2.5"><x-ui.icon name="check" class="mt-0.5 size-4 text-success" />Vídeos próprios ilimitados</li>
                                <li class="flex gap-2.5"><x-ui.icon name="check" class="mt-0.5 size-4 text-success" />{{ $metricsLabels[$plan->metricsLevel?->slug] ?? 'Métricas' }}</li>
                                <li class="flex gap-2.5">
                                    @if ($plan->removes_branding)
                                        <x-ui.icon name="check" class="mt-0.5 size-4 text-success" />Sem a marca “feito com”
                                    @else
                                        <x-ui.icon name="close" class="mt-0.5 size-4 text-ink/30" /><span class="text-ink/50">Exibe “feito com {{ config('app.name') }}”</span>
                                    @endif
                                </li>
                            </ul>
                            <a href="{{ route('register') }}" class="rounded-[10px] px-5 py-3 text-center text-[14.5px] font-bold {{ $featured ? 'bg-accent text-white hover:brightness-95' : 'border border-ink/15 hover:bg-ink/5' }}">
                                {{ $plan->price_cents > 0 ? 'Começar e assinar depois' : 'Criar conta grátis' }}
                            </a>
                        </div>
                    @endforeach
                </div>

                @if ($packages->isNotEmpty())
                    <p class="mt-8 text-center text-[14px] text-ink/60">
                        Precisa de mais vídeos por IA? Pacotes avulsos a partir de
                        <strong class="text-ink">{{ Money::brlFromCents($packages->min('price_cents')) }}</strong>, que não expiram.
                    </p>
                @endif
            </div>
        </section>
    @endif

    {{-- ===== Perguntas frequentes ===== --}}
    <section id="perguntas" class="scroll-mt-20 bg-white py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <h2 class="text-center font-serif text-[34px] sm:text-[42px]">Perguntas frequentes</h2>
            <div class="mt-10 flex flex-col divide-y divide-ink/10 border-y border-ink/10">
                @foreach ($faq as [$question, $answer])
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[16px] font-bold">
                            {{ $question }}
                            <span class="text-[22px] leading-none text-accent transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-[15px] leading-relaxed text-ink/65">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Chamada final ===== --}}
    <section class="relative overflow-hidden bg-accent py-20 text-white">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-4 text-center sm:px-6">
            <h2 class="font-serif text-[34px] leading-tight sm:text-[44px]">Coloque seus pratos para rodar hoje</h2>
            <p class="text-[17px] text-white/85">Crie sua conta grátis, cadastre o primeiro prato e veja o vídeo pronto em minutos.</p>
            <a href="{{ route('register') }}" class="rounded-[10px] bg-night px-7 py-4 text-[15px] font-bold text-white hover:bg-black">Criar conta grátis</a>
        </div>
    </section>
</x-layouts.marketing>
