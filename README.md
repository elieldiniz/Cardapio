# Degustou Cardápio

Cardápio digital em vídeo no estilo Reels. O cliente escaneia o QR Code da mesa e rola um feed vertical com um prato por tela, com vídeo em autoplay, mudo e em loop. O dono do restaurante gera o vídeo de cada prato a partir de 1 a 4 fotos (IA com preset fixo e aprovação obrigatória) ou envia um vídeo próprio.

O produto é só de visualização: o pedido continua sendo feito com o garçom.

## Áreas do sistema

| Área | Rota | Tecnologia | Quem usa |
| --- | --- | --- | --- |
| Site / landing | `/`, `/sobre`, `/termos`… | Blade | Público |
| Feed do cliente | `/r/{slug}` | HTML + JS leve (scroll-snap, IntersectionObserver, service worker) | Cliente na mesa, sem login |
| Painel do restaurante | `/painel` | Livewire 4, mobile-first | Dono |
| Painel administrativo | `/admin` | Filament 5 | Super admin |

**Funcionalidades principais:** categorias e pratos (preço, selos, variações, esgotado/oculto), geração de vídeo por IA com 2–3 variações, upload de vídeo próprio, QR Code para impressão, aparência do cardápio, métricas de visualização e tempo assistido, assinaturas e pacotes avulsos de gerações via Stripe. No admin: restaurantes, planos, cupons, pacotes, presets e provedores de IA, fila de gerações, moderação, custos e impersonação.

## Stack

- PHP 8.3+ / **Laravel 13**, Livewire 4, Filament 5, Tailwind CSS 4, Vite
- **MySQL** e filas no driver `database`
- **Mux** para hospedagem e streaming dos vídeos
- **Stripe** via Laravel Cashier (BRL, assinatura + checkout avulso)
- Provedor de IA plugável via `App\Contracts\VideoGenerationProvider` (`config/ai.php`)
- Testes com **Pest 4**

## Rodando localmente

O projeto roda com **Laravel Sail** (app, worker de fila, MySQL e Mailpit). Use sempre `./vendor/bin/sail`, porque o PHP do host pode não ter as extensões necessárias.

```sh
cp .env.example .env
composer install            # ou via container, se não tiver PHP/Composer no host
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Criar um super admin para acessar `/admin`:

```sh
./vendor/bin/sail artisan app:create-super-admin voce@exemplo.com --name="Seu Nome"
```

| Serviço | Endereço |
| --- | --- |
| Aplicação | http://localhost |
| Mailpit (e-mails) | http://localhost:8025 |
| Vite | http://localhost:5173 |

O container `queue` processa os jobs (geração de vídeo, exclusão de contas). Depois de mudar código usado por jobs, reinicie o worker:

```sh
./vendor/bin/sail restart queue
```

Em ambiente `local`, `/dev/components` e `/dev/feed` mostram os componentes e um feed de exemplo.

## Variáveis de ambiente

Além das variáveis padrão do Laravel:

| Variável | Uso |
| --- | --- |
| `MUX_TOKEN_ID`, `MUX_TOKEN_SECRET` | API do Mux |
| `MUX_WEBHOOK_SECRET` | Verificação de assinatura em `POST /webhooks/mux` |
| `STRIPE_KEY`, `STRIPE_SECRET` | API da Stripe |
| `STRIPE_WEBHOOK_SECRET` | Verificação de assinatura em `POST /stripe/webhook` (Cashier) |
| `CASHIER_CURRENCY`, `CASHIER_CURRENCY_LOCALE` | `brl` / `pt_BR` |
| `AI_VIDEO_PROVIDER` | Slug do provedor de IA em `config/ai.php` (padrão: `null`) |
| `LANDING_SHOW_PRICES` | Exibe ou oculta os preços na landing |
| `LANDING_CONTACT_EMAIL`, `LANDING_CONTACT_WHATSAPP`, `LANDING_CONTACT_INSTAGRAM` | Contatos exibidos no site |

Para receber os webhooks da Stripe localmente:

```sh
stripe listen --forward-to localhost/stripe/webhook
```

> **Provedor de IA:** por enquanto só existe o `NullVideoGenerationProvider`, que não gera vídeo. Com ele, toda geração termina em `erro` e não consome saldo. Para ligar um provedor real, implemente `VideoGenerationProvider`, registre a classe em `config/ai.php` e ajuste `AI_VIDEO_PROVIDER`.

## Regras de negócio importantes

- **1 geração = 1 clipe.** Pedir 3 variações consome 3 gerações.
- O saldo é verificado **antes** de enfileirar (descontando as gerações em andamento) e debitado **só quando a geração dá certo**. Erro do provedor não consome saldo, e reprocessar nunca cobra duas vezes.
- O saldo mensal é **resetado** a cada fatura paga e não acumula. Os pacotes avulsos não expiram e são usados depois do saldo mensal.
- Nenhum vídeo vai ao ar sem aprovação do dono. Cada prato tem no máximo um vídeo ativo.
- Planos, preços, limites e pacotes são cadastrados pelo super admin no `/admin`, sem deploy.
- Toda movimentação de saldo passa pelo `generation_ledger`, com referências idempotentes (`stripe_invoice:…`, `stripe_checkout:…`, `video_generation:…`).

## Estrutura

```
app/
  Actions/      regras de negócio (Ai, Billing, Menu, Videos, Admin, Account)
  Contracts/    MuxClient, VideoGenerationProvider
  Filament/     painel administrativo
  Jobs/         GenerateDishVideo, PurgeRestaurant
  Listeners/    StripeWebhookListener
  Services/     Mux, IA, Feed, métricas do admin, sincronização de cupons
resources/views/
  pages/        páginas Livewire (auth e painel)
  feed/         feed do cliente
  marketing/    landing e páginas institucionais
.spec/init/     descrição do projeto, user stories, schema e fases
.phases/        prompts das 20 fases de implementação
Docs/           estudo de custos e precificação, referências de design
```

## Testes e estilo

```sh
./vendor/bin/sail artisan test --compact
./vendor/bin/sail bin pint --dirty
```
