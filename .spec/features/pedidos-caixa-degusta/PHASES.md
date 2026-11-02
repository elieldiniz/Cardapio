# Phases: pedidos-caixa-degusta

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/pedidos-caixa-degusta/PHASES.md`. Phase N corresponde à Fase N-1 do planejamento (seção 7). Regras gerais em todas as fases: Actions com `handle()` em `app/Actions/<Área>/`, `DB::transaction` + `lockForUpdate()` para dinheiro, lookups via `IsLookupTable`, feed Blade + JS puro sem Livewire, painel em Livewire single-file, testes Pest via `./vendor/bin/sail artisan test`, worker `--tries=1` (nada monetário em fila).

## Phase 1: Fundação (configurações, horário, menu)

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Schema de configurações do pedido e horários
      Arquivos: `database/migrations/2026_11_01_000001_create_restaurant_order_settings_and_business_hours_tables.php`, `app/Models/RestaurantOrderSetting.php`, `app/Models/BusinessHour.php`, `app/Models/Restaurant.php`
      Mudança: criar `restaurant_order_settings` (com timezone default America/Porto_Velho) e `business_hours`, modelos, factories e relações.
      Cobre: RF-01, RF-02, RF-02e, RNF-01, RNF-02, RNF-09
      Acceptance criteria: migration roda; `restaurant_id` único em settings; `config('app.timezone')` continua `UTC`.
      Testes: `tests/Feature/OrderSettingsSchemaTest.php` — persistência e unicidade
- [ ] T02 — Colunas de categoria (ícone/janela) e tipo de negócio
      Arquivos: `database/migrations/2026_11_01_000002_add_icon_window_to_categories_and_business_type_to_restaurants.php`, `app/Models/Category.php`, `app/Models/Restaurant.php`
      Mudança: `categories.icon/shows_from/shows_until`, `restaurants.business_type`, helper `Category::isShownAt`.
      Cobre: RF-02b, RF-02c
      Acceptance criteria: categoria 11:00-15:00 oculta às 16:00 e visível às 12:00; janela virando meia-noite funciona.
      Testes: `tests/Unit/CategoryWindowTest.php` — janelas
- [ ] T03 — Flags de funcionalidade por plano
      Arquivos: `database/migrations/2026_11_01_000003_add_features_to_plans_table.php`, `config/plan_features.php`, `app/Models/Plan.php`, `database/seeders/PlanSeeder.php`, `app/Http/Middleware/EnsurePlanFeature.php`, `bootstrap/app.php`
      Mudança: `plans.features` JSON, `Plan::hasFeature`, middleware `plan.feature:<chave>` (403), seeder preenchendo.
      Cobre: RF-02d, RF-21
      Acceptance criteria: rota com feature desligada retorna 403 e ligada 200; `PlanSeederTest` segue verde.
      Testes: `tests/Feature/PlanFeatureGateTest.php` — gate 403/200
- [ ] T04 — Support/BusinessHours (aberto/fechado no fuso da loja)
      Arquivos: `app/Support/BusinessHours.php`, `tests/Unit/BusinessHoursTest.php`
      Mudança: estado, próxima abertura, turnos múltiplos, virada de meia-noite, interruptor manual, grade semanal.
      Cobre: RF-02, RF-02e, RNF-09, UI-04
      Acceptance criteria: seg 18-23 America/Porto_Velho: 22:30 UTC seg = aberto, 03:30 UTC ter = fechado, seg 12:00 = "Fechado · abre às 18:00"; manual fechado prevalece.
      Testes: `tests/Unit/BusinessHoursTest.php` — casos acima e fuso inválido
- [ ] T05 — Actions de configurações, horários e policy
      Arquivos: `app/Actions/Settings/SaveOrderSettings.php`, `app/Actions/Settings/SaveBusinessHours.php`, `app/Policies/RestaurantOrderSettingPolicy.php`, `app/Providers/AppServiceProvider.php`
      Mudança: validar/normalizar WhatsApp, fuso, Pix e dinheiro (`Money::parse`), salvar em transação, policy por loja.
      Cobre: RF-01, RF-02, RF-02e, RNF-01
      Acceptance criteria: número e fuso inválidos rejeitados; dono B não altera loja A.
      Testes: `tests/Feature/SaveOrderSettingsTest.php` — validações e isolamento
- [ ] T06 — Tela Configurações (`/painel/configuracoes`)
      Arquivos: `resources/views/pages/panel/⚡settings.blade.php`, `routes/web.php`
      Mudança: página Livewire fina (loja, horários, Pix, aceitar fechado, "Testar este número" via wa.me), rota `panel.settings`.
      Cobre: RF-01, RF-02, RF-02e, UI-01
      Acceptance criteria: salvar e reabrir mostra os mesmos valores; teste do número não cria `orders`.
      Testes: `tests/Feature/PanelSettingsPageTest.php` — Livewire::test
- [ ] T07 — Feed: aberto/fechado, "Ver horários" e janela de categoria
      Arquivos: `app/Http/Controllers/Feed/FeedController.php`, `app/Services/Feed/FeedBuilder.php`, `resources/views/feed/show.blade.php`, `resources/views/feed/partials/category-bar.blade.php`, `app/Support/FeedSample.php`
      Mudança: estado da loja no Blade (sem Livewire), filtro de categoria por janela, ícone, FeedSample atualizado.
      Cobre: RF-02, RF-02b, RF-25, RNF-06
      Acceptance criteria: feed mostra "Aberto agora"/"Fechado · abre às 18:00"; categoria fora da janela some; testes Feed* existentes verdes.
      Testes: `tests/Feature/FeedOpenStateTest.php` — estados e janela
- [ ] T08 — Aviso de loja fechada no painel
      Arquivos: `resources/views/components/store-status-banner.blade.php`, `resources/views/layouts/panel.blade.php`, `resources/views/pages/panel/⚡home.blade.php`
      Mudança: componente com estado atual e próximo horário no layout e em Início.
      Cobre: UI-04, RF-02
      Acceptance criteria: aviso visível apenas quando RF-02 resulta fechado.
      Testes: `tests/Feature/PanelClosedBannerTest.php` — aberto/fechado
- [ ] T09 — Tipo de negócio no cadastro e categorias iniciais
      Arquivos: `config/business_types.php`, `app/Actions/RegisterRestaurantOwner.php`, `resources/views/pages/auth/⚡register.blade.php`
      Mudança: 5 tipos com categorias iniciais criadas na transação do cadastro; sem escolha, nenhuma.
      Cobre: RF-02c
      Acceptance criteria: "pizzaria" cria ≥1 categoria do conjunto; sem escolha zero categorias.
      Testes: `tests/Feature/RegisterBusinessTypeTest.php` — com e sem tipo
- [ ] T10 — Menu agrupado, rótulo "Produtos" e gate por plano
      Arquivos: `app/Support/PanelNavigation.php`, `resources/views/layouts/panel.blade.php`, `resources/views/pages/panel/⚡dishes.blade.php`, `resources/views/pages/panel/⚡home.blade.php`, `resources/views/pages/panel/⚡subscription.blade.php`, `routes/web.php`
      Mudança: `PanelNavigation::groups()` com 5 grupos, "Pratos"→"Produtos" no painel, itens respeitam `Plan::hasFeature`; rotas `panel.dishes*` intactas.
      Cobre: RF-02d, UI-01, RF-25
      Acceptance criteria: exatamente 5 grupos; nenhuma tela do painel contém "Pratos"; `/painel/pratos` e `/painel/pratos/novo` respondem 200.
      Testes: `tests/Feature/PanelNavigationTest.php` — grupos, rótulo, rotas
- [ ] T11 — Categorias: ícone e horário próprio no painel
      Arquivos: `resources/views/pages/panel/⚡categories.blade.php`, `app/Actions/Menu/SaveCategory.php`
      Mudança: campos de ícone e janela, Action `SaveCategory` que valida e persiste.
      Cobre: RF-02b, UI-01
      Acceptance criteria: salvar e reabrir devolve ícone e janela.
      Testes: `tests/Feature/CategoryManagementTest.php` — janela e ícone
- [ ] T12 — Fechamento Fase 0: colisão de rotas e regressão
      Arquivos: `tests/Feature/RouteCollisionTest.php`
      Mudança: teste de unicidade de nome e método+uri das rotas e de que as rotas de `api_contracts.md` respondem; suíte completa.
      Cobre: RF-25, CT-06, CT-01
      Acceptance criteria: sem rota duplicada; `./vendor/bin/sail artisan test` verde.
      Testes: `tests/Feature/RouteCollisionTest.php` — unicidade

## Phase 2: Cliente pede (complementos, carrinho, checkout, pedido)

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T13 — Tabelas lookup de pedido e pagamento
      Arquivos: `database/migrations/2026_11_02_000001_create_order_statuses_and_payment_methods_tables.php`, `app/Models/OrderStatus.php`, `app/Models/PaymentMethod.php`, `database/seeders/OrderStatusSeeder.php`, `database/seeders/PaymentMethodSeeder.php`, `database/seeders/ReferenceDataSeeder.php`, `tests/Feature/LookupSeedersTest.php`
      Mudança: lookups com `IsLookupTable` e `LookupSeeder` registrados no ReferenceDataSeeder.
      Cobre: RNF-03
      Acceptance criteria: `OrderStatus::idFor('novo')` e `PaymentMethod::idFor('pix')` resolvem após `seedReferenceData()`.
      Testes: `tests/Feature/LookupSeedersTest.php` — slugs
- [ ] T14 — Schema de complementos e 9 modelos prontos
      Arquivos: `database/migrations/2026_11_02_000002_create_option_groups_options_and_dish_option_group_tables.php`, `app/Models/OptionGroup.php`, `app/Models/Option.php`, `app/Models/Dish.php`, `database/factories/OptionGroupFactory.php`, `config/option_group_templates.php`
      Mudança: tabelas, relações e config com 9 modelos; `dish_variants` intactos.
      Cobre: RF-03, RNF-01, RNF-02
      Acceptance criteria: 9 modelos presentes; relação dish↔grupos funciona; variantes seguem funcionando.
      Testes: `tests/Feature/OptionGroupSchemaTest.php` — templates e relações
- [ ] T15 — SyncDishOptionGroups e vínculo no formulário
      Arquivos: `app/Actions/Menu/SyncDishOptionGroups.php`, `app/Actions/Menu/SaveDish.php`, `resources/views/pages/panel/⚡dish-form.blade.php`
      Mudança: sincronizar grupos na transação de SaveDish, inclusive na criação; seletor no form.
      Cobre: RF-03, RNF-01
      Acceptance criteria: criar produto com 2 grupos e reabrir devolve os 2; grupo de outra loja rejeitado.
      Testes: `tests/Feature/DishOptionGroupsTest.php` — criar e reabrir
- [ ] T16 — Tela Complementos (`/painel/complementos`)
      Arquivos: `resources/views/pages/panel/⚡option-groups.blade.php`, `app/Actions/Menu/SaveOptionGroup.php`, `app/Actions/Menu/DeleteOptionGroup.php`, `routes/web.php`
      Mudança: CRUD de grupos/opções, modelos prontos, prévia ao vivo, rota `panel.option-groups`, item no menu.
      Cobre: RF-03, UI-01
      Acceptance criteria: criar a partir de modelo e editar; outra loja não acessa.
      Testes: `tests/Feature/OptionGroupsPageTest.php` — CRUD e isolamento
- [ ] T17 — Bairros de entrega (delivery_zones)
      Arquivos: `database/migrations/2026_11_02_000003_create_delivery_zones_table.php`, `app/Models/DeliveryZone.php`, `app/Actions/Settings/SaveDeliveryZones.php`, `resources/views/pages/panel/⚡settings.blade.php`
      Mudança: tabela, Action e seção Entrega em Configurações (bairros, frete grátis, mínimo, "a combinar").
      Cobre: RF-06b, RNF-01, RNF-02
      Acceptance criteria: salvar e ler zonas; valores via `Money::parse`.
      Testes: `tests/Feature/DeliveryZonesTest.php` — salvar/ler
- [ ] T18 — Schema de pedidos
      Arquivos: `database/migrations/2026_11_02_000004_create_orders_tables.php`, `app/Models/Order.php`, `app/Models/OrderItem.php`, `app/Models/OrderPayment.php`, `app/Models/OrderStatusHistory.php`, `app/Models/Customer.php`, `database/factories/OrderFactory.php`
      Mudança: orders (único restaurant+code, tracking_token único), itens snapshot, pagamentos, histórico, clientes; sem FK para tabelas futuras.
      Cobre: RF-06, RF-02a, RF-07a, RNF-01, RNF-02
      Acceptance criteria: dois pedidos nunca compartilham código na loja; editar o produto não altera o snapshot.
      Testes: `tests/Feature/OrderSchemaTest.php` — unicidade e snapshot
- [ ] T19 — Services/Orders/OrderPricer
      Arquivos: `app/Services/Orders/OrderPricer.php`, `app/Services/Orders/PricedOrder.php`, `app/Exceptions/OrderRejectedException.php`
      Mudança: recalcular produto, variação, complementos, taxa, frete grátis, mínimo (sobre subtotal antes de desconto) e rejeitar item indisponível listando cada um; sem float.
      Cobre: RF-05, RF-06b, RF-06e, RNF-02
      Acceptance criteria: preço do navegador ignorado; item oculto/de outra loja rejeitado com lista; bairro fora da lista gera taxa "a combinar".
      Testes: `tests/Unit/OrderPricerTest.php` — adulteração, obrigatório, mínimo
- [ ] T20 — Código, token, mensagem de WhatsApp e payload Pix
      Arquivos: `app/Services/Orders/OrderCodeGenerator.php`, `app/Services/Orders/WhatsAppMessageBuilder.php`, `app/Support/PixPayload.php`, `tests/Unit/WhatsAppMessageBuilderTest.php`, `tests/Unit/PixPayloadTest.php`
      Mudança: código `D-XXXXX`, token aleatório, mensagem completa com link `/p/{token}`, Pix copia-e-cola com valor do servidor.
      Cobre: RF-06, RF-06c, RF-02b
      Acceptance criteria: mensagem contém link de acompanhamento; payload Pix contém o valor total e CRC válido.
      Testes: `tests/Unit/PixPayloadTest.php` — CRC e valor
- [ ] T21 — PlaceOrder e CT-01
      Arquivos: `app/Actions/Orders/PlaceOrder.php`, `app/Http/Requests/Feed/PlaceOrderRequest.php`, `app/Http/Controllers/Feed/OrderController.php`, `routes/web.php`, `bootstrap/app.php`
      Mudança: Action transacional, controller fino, rota `feed.orders.store` com throttle e CSRF isento, respostas 201/403/422.
      Cobre: RF-02a, RF-05, RF-06, RF-06b, RF-06c, RF-06e, CT-01, RNF-04, RNF-05
      Acceptance criteria: loja fechada 422, com opção ligada 201 com selo; preço adulterado recalculado; item oculto 422 listando o item e removido ⇒ 201; Pix não cria `order_payments`.
      Testes: `tests/Feature/PlaceOrderTest.php` — cenários de 201/403/422
- [ ] T22 — CT-02 e CT-03 (WhatsApp e acompanhamento)
      Arquivos: `app/Http/Controllers/Feed/OrderController.php`, `app/Http/Controllers/Feed/OrderTrackingController.php`, `app/Actions/Orders/RecordWhatsAppEvent.php`, `resources/views/order/track.blade.php`, `resources/js/track.js`, `routes/web.php`
      Mudança: POST `feed.orders.whatsapp` (204), GET `/p/{token}` e `/p/{token}/status` com polling, só primeiro nome e endereço parcial, expiração de 7 dias.
      Cobre: RF-06a, RF-07, RF-07a, CT-02, CT-03, UI-02
      Acceptance criteria: HTML e JSON sem sobrenome/WhatsApp/número/observações; encerrado há 8 dias 404 e há 6 dias 200; timestamps nulos até o evento.
      Testes: `tests/Feature/OrderTrackingTest.php` — PII e expiração
- [ ] T23 — Expurgo de endereço e observações (RNF-08)
      Arquivos: `app/Console/Commands/PruneOrderPersonalData.php`, `app/Actions/Orders/PurgeOrderPersonalData.php`, `routes/console.php`
      Mudança: comando agendado diário que anula endereço/observações 30 dias após o fim do pedido.
      Cobre: RNF-08, RF-07a
      Acceptance criteria: 31 dias limpa, 29 mantém, aberto intocado, duas execuções inofensivas.
      Testes: `tests/Feature/PruneOrderPersonalDataTest.php` — 31/29 dias
- [ ] T24 — Carrinho em JS puro
      Arquivos: `resources/js/cart.js`, `resources/js/feed.js`, `vite.config.js`
      Mudança: estado por loja em localStorage, regras de escolha, observação ≤ 140, chunk separado.
      Cobre: RF-04, RNF-06
      Acceptance criteria: grupo obrigatório sem escolha bloqueia; 141 caracteres bloqueia; o feed continua sem Livewire.
      Testes: `tests/Feature/FeedCartAssetsTest.php` — feed sem Livewire
- [ ] T25 — Feed: adicionar, ficha com escolhas, barra e sheet do carrinho
      Arquivos: `resources/views/feed/partials/dish.blade.php`, `resources/views/feed/partials/detail-sheet.blade.php`, `resources/views/feed/partials/cart-bar.blade.php`, `resources/views/feed/partials/cart-sheet.blade.php`, `resources/views/feed/show.blade.php`, `app/Services/Feed/FeedBuilder.php`
      Mudança: dados dos grupos no feed, botão Adicionar, barra "Ver pedido · N itens · R$ total", sheet editável, "Catálogo em preparação".
      Cobre: RF-04, RF-06e, UI-02, RF-25
      Acceptance criteria: produto esgotado sem botão; markup contém barra e grupos; Feed* verdes.
      Testes: `tests/Feature/FeedOrderingMarkupTest.php` — markup
- [ ] T26 — Checkout em etapas
      Arquivos: `resources/views/feed/partials/checkout.blade.php`, `resources/js/checkout.js`, `resources/js/cart.js`, `resources/views/feed/show.blade.php`
      Mudança: receber → endereço → pagamento → observação, erros 422 por item, wa.me, "Sim, enviei" via CT-02, dados salvos no aparelho, "Fazer outro pedido".
      Cobre: RF-06b, RF-06c, RF-06d, RF-06a, UI-02
      Acceptance criteria: usável em 360 px; segundo checkout no aparelho vem preenchido; mostra "faltam R$ X para entrega grátis".
      Testes: `tests/Feature/FeedCheckoutMarkupTest.php` — partial e zonas
- [ ] T27 — Actions da lista de produtos
      Arquivos: `app/Actions/Menu/UpdateDishPrice.php`, `app/Actions/Menu/DuplicateDish.php`, `app/Actions/Menu/DeleteDish.php`, `app/Actions/Menu/SetDishFlag.php`
      Mudança: preço via `Money::parse`, duplicar respeitando `canAddDish()`, excluir, interruptores disponível/visível.
      Cobre: RF-06f, RNF-01, RF-25
      Acceptance criteria: "R$ 32,90", "32,90" e "3290" viram 32.90; duplicar bloqueado no limite do plano.
      Testes: `tests/Feature/DishQuickActionsTest.php` — formatos e limite
- [ ] T28 — Lista de produtos (UI)
      Arquivos: `resources/views/pages/panel/⚡dishes.blade.php`
      Mudança: busca, filtro por categoria, abas, preço na linha, interruptores, duplicar e excluir.
      Cobre: RF-06f, UI-01
      Acceptance criteria: abas Todos/Ativos/Indisponíveis/Ocultos filtram corretamente; `DishManagementTest` verde.
      Testes: `tests/Feature/DishManagementTest.php` — abas e ações
- [ ] T29 — Política de privacidade por loja
      Arquivos: `app/Http/Controllers/PrivacyController.php`, `resources/views/marketing/legal/privacy.blade.php`, `routes/web.php`
      Mudança: `/privacidade?loja={slug}` via controller mantendo nome `legal.privacy`.
      Cobre: RF-06f, RNF-08
      Acceptance criteria: com `loja` válido mostra bloco da loja; sem parâmetro mostra a página geral.
      Testes: `tests/Feature/PrivacyPageTest.php` — com/sem loja
- [ ] T30 — Fechamento Fase 1: fluxo, peso do JS e regressão
      Arquivos: `tests/Feature/OrderCheckoutFlowTest.php`
      Mudança: teste ponta a ponta feed → CT-01 → CT-02 → CT-03, medição gzip do JS novo, suíte completa, restart do worker.
      Cobre: RF-25, RNF-05, RNF-06, RNF-07
      Acceptance criteria: JS novo ≤ 30 KB gzip; suíte e RouteCollisionTest verdes.
      Testes: `tests/Feature/OrderCheckoutFlowTest.php` — fluxo

## Phase 3: Gestor de pedidos

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T31 — Support/StorePeriod
      Arquivos: `app/Support/StorePeriod.php`, `tests/Unit/StorePeriodTest.php`
      Mudança: intervalos hoje/ontem/7 dias/mês/mês passado no fuso da loja, devolvidos em UTC.
      Cobre: RNF-09, RF-08b, RF-12
      Acceptance criteria: pedido às 02:00 UTC de terça cai em "hoje" de segunda no fuso Porto_Velho.
      Testes: `tests/Unit/StorePeriodTest.php` — fuso
- [ ] T32 — Policy e Actions de transição de pedido
      Arquivos: `app/Policies/OrderPolicy.php`, `app/Actions/Orders/AdvanceOrder.php`, `app/Actions/Orders/RefuseOrder.php`, `app/Actions/Orders/AcceptOrder.php`, `app/Services/Orders/OrderTransitions.php`, `app/Providers/AppServiceProvider.php`
      Mudança: mapa de transições, histórico por transição, `lockForUpdate()`, aceitar "a combinar" exige taxa.
      Cobre: RF-08, RNF-01, RNF-04
      Acceptance criteria: transições inválidas rejeitadas; 1 linha de histórico por transição; dono de outra loja recebe 403.
      Testes: `tests/Feature/OrderTransitionsTest.php` — transições
- [ ] T33 — Gestor de pedidos (`/painel/pedidos`)
      Arquivos: `resources/views/pages/panel/⚡orders.blade.php`, `resources/views/pages/panel/partials/order-card.blade.php`, `routes/web.php`
      Mudança: 4 colunas, cartão com selos, busca, filtro de período e soma, rota `panel.orders`.
      Cobre: RF-08, RF-08b, RF-02a, RF-06a, UI-01
      Acceptance criteria: colunas corretas; pedido fora do horário tem selo; loja B não vê pedidos da loja A.
      Testes: `tests/Feature/OrderManagerPageTest.php` — colunas e isolamento
- [ ] T34 — Tempo real, contador, som e avisos ao cliente
      Arquivos: `resources/views/pages/panel/⚡orders.blade.php`, `app/Support/PanelNavigation.php`, `resources/views/layouts/panel.blade.php`, `resources/js/panel-sound.js`, `app/Services/Orders/CustomerNoticeBuilder.php`
      Mudança: `wire:poll`, contador no menu, botão "Ativar som", mensagens prontas por status e opção WhatsApp Desktop por aparelho.
      Cobre: RF-08, RF-08a, UI-03
      Acceptance criteria: pedido via CT-01 aparece em ≤ 10 s; contador reflete status `novo`; sem ativar o som o contador ainda atualiza.
      Testes: `tests/Feature/OrderManagerRealtimeTest.php` — contador e render
- [ ] T35 — Fechamento Fase 2
      Arquivos: `tests/Feature/OrderTimelineTest.php`
      Mudança: status mudado no gestor aparece em `/p/{token}/status` com horário; regressão e colisão de rotas.
      Cobre: RF-07, RF-08, RF-25
      Acceptance criteria: página de acompanhamento reflete a mudança em ≤ 15 s; suíte verde.
      Testes: `tests/Feature/OrderTimelineTest.php` — linha do tempo

## Phase 4: Caixa e financeiro

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T36 — Schema de caixa, fiado e lookups financeiros
      Arquivos: `database/migrations/2026_11_03_000001_create_cash_tables.php`, `app/Models/CashSession.php`, `app/Models/CashMovement.php`, `app/Models/CashMovementType.php`, `app/Models/Receivable.php`, `database/seeders/CashMovementTypeSeeder.php`, `database/seeders/ReferenceDataSeeder.php`
      Mudança: tipos de movimento lookup, sessões, movimentos com `reference` único, fiados, FK `orders.cash_session_id`.
      Cobre: RF-09, RF-10, RF-11, RNF-02, RNF-03, RNF-04
      Acceptance criteria: 2 caixas abertos por loja impossíveis; reference duplicada rejeitada.
      Testes: `tests/Feature/CashSchemaTest.php` — restrições
- [ ] T37 — Actions de caixa
      Arquivos: `app/Actions/Cash/OpenCashSession.php`, `app/Actions/Cash/CloseCashSession.php`, `app/Actions/Cash/RecordMovement.php`, `app/Services/Cash/CashSummary.php`
      Mudança: abrir, fechar comparando contado × esperado, movimentos idempotentes com lock.
      Cobre: RF-10, RNF-04
      Acceptance criteria: esperado = troco + vendas em dinheiro + reforços − retiradas − despesas em dinheiro − estornos em dinheiro; Pix/cartão fora do esperado; mesma reference grava uma vez.
      Testes: `tests/Feature/CashSessionTest.php` — esperado e idempotência
- [ ] T38 — FinalizeOrder e edição de pagamento
      Arquivos: `app/Actions/Orders/FinalizeOrder.php`, `app/Actions/Orders/UpdateOrderPayment.php`, `app/Actions/Cash/CreateReceivable.php`
      Mudança: Pix/dinheiro/cartão/fiado/dividido/sem registrar, movimento só com caixa aberto, delivery com caixa fechado finaliza com aviso, editar pagamento.
      Cobre: RF-09, RF-06c, RNF-04
      Acceptance criteria: dividido ≠ total rejeitado; finalizar 2x não duplica; delivery com caixa fechado cria `order_payments` sem `cash_movements`; Pix só gera pagamento ao finalizar.
      Testes: `tests/Feature/FinalizeOrderTest.php` — cenários
- [ ] T39 — CancelFinalizedOrder (estorno)
      Arquivos: `app/Actions/Orders/CancelFinalizedOrder.php`, `app/Actions/Cash/CancelReceivable.php`
      Mudança: motivo obrigatório, estorno sem apagar a venda, cancelar fiado não pago, ponto de extensão para cupom e fidelidade, idempotente.
      Cobre: RF-08c, RNF-04
      Acceptance criteria: sem motivo rejeitado; 1 estorno; cancelar 2x não duplica; fiado pago preservado com estorno registrado.
      Testes: `tests/Feature/CancelFinalizedOrderTest.php` — cenários
- [ ] T40 — Finalizar e cancelar no gestor (UI)
      Arquivos: `resources/views/pages/panel/⚡orders.blade.php`, `resources/views/pages/panel/partials/finalize-modal.blade.php`
      Mudança: modal de pagamento, aviso sem lançamento, editar pagamento, cancelar com motivo.
      Cobre: RF-09, RF-08c, UI-01
      Acceptance criteria: aviso exibido com caixa fechado; cancelar exige motivo.
      Testes: `tests/Feature/OrderManagerFinalizeTest.php` — modal
- [ ] T41 — Tela Caixa (`/painel/caixa`)
      Arquivos: `resources/views/pages/panel/⚡cash.blade.php`, `routes/web.php`
      Mudança: abrir/fechar, despesa com categoria, retirada, reforço, gaveta, vendas por forma, rota `panel.cash`.
      Cobre: RF-10, UI-01
      Acceptance criteria: fechar mostra diferença contado × esperado; menu alcança a tela.
      Testes: `tests/Feature/CashPageTest.php` — fluxo
- [ ] T42 — Fiado (`/painel/fiado`)
      Arquivos: `app/Actions/Cash/SettleReceivable.php`, `resources/views/pages/panel/⚡receivables.blade.php`, `routes/web.php`
      Mudança: lista, total a receber, baixa idempotente com entrada no caixa aberto.
      Cobre: RF-11, RNF-04
      Acceptance criteria: total a receber = soma dos fiados sem `paid_at`; baixa 2x gera 1 entrada.
      Testes: `tests/Feature/ReceivablesTest.php` — total e baixa
- [ ] T43 — Relatório financeiro (`/painel/relatorio`)
      Arquivos: `app/Services/Cash/ReportBuilder.php`, `resources/views/pages/panel/⚡report.blade.php`, `routes/web.php`
      Mudança: vendas, ticket médio, recebido, despesas, resultado por período no fuso da loja.
      Cobre: RF-12, RNF-09
      Acceptance criteria: resultado = recebido − despesas; totais batem com os movimentos.
      Testes: `tests/Feature/ReportTest.php` — totais e fuso
- [ ] T44 — Fechamento Fase 3
      Arquivos: `tests/Feature/CashFlowEndToEndTest.php`
      Mudança: fluxo pedido → finalizar → caixa → relatório → cancelar; regressão e colisão de rotas.
      Cobre: RF-25, RF-09, RF-10, RF-12
      Acceptance criteria: pedido finalizado cai sozinho no caixa e o relatório bate; suíte verde.
      Testes: `tests/Feature/CashFlowEndToEndTest.php` — ponta a ponta

## Phase 5: PDV de balcão

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T45 — CreatePdvSale
      Arquivos: `app/Actions/Orders/CreatePdvSale.php`
      Mudança: venda de balcão com canal `pdv`, status Em preparo, desconto, cobrança com troco no caixa aberto, tudo transacional.
      Cobre: RF-13, RNF-04
      Acceptance criteria: sem caixa aberto bloqueia e não cria pedido; com caixa o movimento é lançado.
      Testes: `tests/Feature/PdvSaleTest.php` — com/sem caixa
- [ ] T46 — Tela PDV (`/painel/pdv`)
      Arquivos: `resources/views/pages/panel/⚡pdv.blade.php`, `routes/web.php`
      Mudança: grade por categoria, busca, escolhas do feed, desconto, cliente, enviar para cozinha, cobrança; rota `panel.pdv`.
      Cobre: RF-13, UI-01
      Acceptance criteria: venda concluída aparece em Em preparo; alcançável pelo menu.
      Testes: `tests/Feature/PdvPageTest.php` — venda
- [ ] T47 — Fechamento Fase 4
      Arquivos: `tests/Feature/PdvCashIntegrationTest.php`
      Mudança: venda de balcão no caixa e no relatório junto com delivery; regressão.
      Cobre: RF-13, RF-25
      Acceptance criteria: totais do relatório incluem PDV e delivery; suíte verde.
      Testes: `tests/Feature/PdvCashIntegrationTest.php` — integração

## Phase 6: Produtos avançados

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T48 — Schema de produtos avançados
      Arquivos: `database/migrations/2026_11_04_000001_add_advanced_product_columns_and_pizza_tables.php`, `app/Models/PizzaSize.php`, `app/Models/PizzaFlavor.php`, `app/Models/PizzaFlavorPrice.php`, `app/Models/PromoBanner.php`, `app/Models/Dish.php`
      Mudança: colunas `sale_type`, `kind`, `promo_price`, `is_featured` e tabelas de pizza e banners.
      Cobre: RF-14, RF-15, RF-16
      Acceptance criteria: migration roda; relações do Dish funcionam; produtos existentes inalterados.
      Testes: `tests/Feature/AdvancedProductSchemaTest.php` — relações
- [ ] T49 — SavePizza e formulário
      Arquivos: `app/Actions/Menu/SavePizza.php`, `resources/views/pages/panel/⚡dish-form.blade.php`
      Mudança: tamanhos, sabores e preços numa transação; bloquear publicação sem sabor com preço.
      Cobre: RF-14
      Acceptance criteria: criar e reler pizza devolve tamanhos, sabores e preços; sem sabor com preço falha.
      Testes: `tests/Feature/PizzaManagementTest.php` — criar e reler
- [ ] T50 — Preço de pizza no pricer e no feed
      Arquivos: `app/Services/Orders/OrderPricer.php`, `resources/js/cart.js`, `resources/views/feed/partials/detail-sheet.blade.php`, `resources/views/pages/panel/⚡settings.blade.php`
      Mudança: meio a meio por `maior`/`media`, máximo de sabores, seletor no feed, regra em Configurações.
      Cobre: RF-14a, RF-05
      Acceptance criteria: sabores R$ 40 e R$ 60 ⇒ R$ 60 (maior) ou R$ 50 (média).
      Testes: `tests/Unit/PizzaPricingTest.php` — regra
- [ ] T51 — Venda por kg e ajuste pós-pesagem
      Arquivos: `app/Services/Orders/OrderPricer.php`, `app/Actions/Orders/AdjustOrderValues.php`, `resources/js/cart.js`, `resources/views/pages/panel/⚡orders.blade.php`
      Mudança: passo 0,5 kg e ajuste separado de produtos e taxa antes de finalizar, registrado no pedido.
      Cobre: RF-15
      Acceptance criteria: ajuste recalcula total e fica registrado no pedido.
      Testes: `tests/Feature/WeightedOrderTest.php` — ajuste
- [ ] T52 — promo_price, destaque e Ofertas
      Arquivos: `app/Services/Orders/OrderPricer.php`, `app/Services/Feed/FeedBuilder.php`, `resources/views/feed/partials/dish.blade.php`, `app/Actions/Menu/SaveDish.php`
      Mudança: "de R$ X por R$ Y", destaque no topo, servidor cobra promoção vigente.
      Cobre: RF-16
      Acceptance criteria: preço promocional cobrado enquanto vigente e normal depois.
      Testes: `tests/Feature/PromoPriceTest.php` — vigência
- [ ] T53 — Banner promocional
      Arquivos: `app/Actions/Menu/SavePromoBanner.php`, `resources/views/pages/panel/⚡dishes.blade.php`, `resources/views/feed/partials/promo-banner.blade.php`
      Mudança: editor do banner (título ≤ 70, texto ≤ 140, data limite) e exibição no feed.
      Cobre: RF-16
      Acceptance criteria: banner some após a data limite; limites de caracteres validados.
      Testes: `tests/Feature/PromoBannerTest.php` — validação e expiração
- [ ] T54 — Fechamento Fase 5
      Arquivos: `tests/Feature/AdvancedProductsFlowTest.php`
      Mudança: pizza meio a meio e kg fecham valor certo no feed e no gestor; regressão.
      Cobre: RF-25, RF-14, RF-15
      Acceptance criteria: valores corretos no pedido; suíte verde.
      Testes: `tests/Feature/AdvancedProductsFlowTest.php` — fluxo

## Phase 7: Crescimento

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T55 — Cupons do restaurante (backend)
      Arquivos: `database/migrations/2026_11_05_000001_create_promo_codes_table.php`, `app/Models/PromoCode.php`, `app/Actions/Orders/ApplyPromoCode.php`, `app/Actions/Orders/ReleasePromoCodeUse.php`, `app/Services/Orders/OrderPricer.php`, `app/Actions/Orders/PlaceOrder.php`, `app/Actions/Orders/CancelFinalizedOrder.php`
      Mudança: tabela `promo_codes`, validação, `uses` +1 na criação, devolução idempotente no cancelamento.
      Cobre: RF-17, RF-08c, RNF-04
      Acceptance criteria: expirado/abaixo do mínimo/esgotado rejeitados; cancelar devolve 1 uso uma vez, nunca abaixo de 0; tabela `coupons` intacta.
      Testes: `tests/Feature/PromoCodeTest.php` — cenários
- [ ] T56 — Tela Cupons e campo no checkout
      Arquivos: `resources/views/pages/panel/⚡promo-codes.blade.php`, `resources/js/checkout.js`, `routes/web.php`
      Mudança: CRUD com gerador, % ou R$, mínimo, limite, validade, descrição ≤ 80, link `?cupom=`.
      Cobre: RF-17, UI-01
      Acceptance criteria: cupom aplicado atualiza o total no checkout; alcançável pelo menu.
      Testes: `tests/Feature/PromoCodesPageTest.php` — CRUD
- [ ] T57 — Fidelidade (backend)
      Arquivos: `database/migrations/2026_11_05_000002_create_loyalty_tables.php`, `app/Models/LoyaltyProgram.php`, `app/Models/LoyaltyCard.php`, `app/Actions/Loyalty/CreditLoyaltyStamp.php`, `app/Actions/Loyalty/RevertLoyaltyStamp.php`, `app/Actions/Loyalty/RedeemLoyaltyReward.php`, `app/Actions/Orders/FinalizeOrder.php`, `app/Actions/Orders/CancelFinalizedOrder.php`
      Mudança: selo ao finalizar, devolução única ao cancelar, resgate único.
      Cobre: RF-18, RNF-04
      Acceptance criteria: finalizar 2x credita 1 selo; cancelar 2x devolve 1 selo.
      Testes: `tests/Feature/LoyaltyTest.php` — idempotência
- [ ] T58 — Tela Fidelidade
      Arquivos: `resources/views/pages/panel/⚡loyalty.blade.php`, `routes/web.php`
      Mudança: configuração do programa, prévia "O cliente vê", contadores.
      Cobre: RF-18, UI-01
      Acceptance criteria: configurar e reabrir persiste; alcançável pelo menu.
      Testes: `tests/Feature/LoyaltyPageTest.php` — página
- [ ] T59 — Clientes, "Apagar dados" e expurgo de 2 anos
      Arquivos: `resources/views/pages/panel/⚡customers.blade.php`, `app/Actions/Customers/EraseCustomerData.php`, `app/Console/Commands/PruneInactiveCustomers.php`, `routes/console.php`, `routes/web.php`
      Mudança: lista de clientes, apagar dados anonimiza cliente e pedidos, comando agendado de 2 anos.
      Cobre: RF-19, RNF-08
      Acceptance criteria: após apagar, nome e WhatsApp não existem; o comando remove só último pedido > 2 anos.
      Testes: `tests/Feature/CustomerErasureTest.php` — apagar e expurgar
- [ ] T60 — Origem dos pedidos
      Arquivos: `resources/js/cart.js`, `app/Actions/Orders/PlaceOrder.php`, `app/Services/Cash/ReportBuilder.php`, `resources/views/pages/panel/⚡qr-code.blade.php`, `resources/views/pages/panel/⚡report.blade.php`
      Mudança: guardar `?origem=` e campanha, normalizar para lista fechada, gerar links, relatório por origem.
      Cobre: RF-20
      Acceptance criteria: `?origem=instagram` grava `origin=instagram`; valor desconhecido grava `outro`.
      Testes: `tests/Feature/OrderOriginTest.php` — origem
- [ ] T61 — Funil (CT-04)
      Arquivos: `database/migrations/2026_11_05_000003_create_funnel_events_table.php`, `app/Models/FunnelEvent.php`, `app/Http/Controllers/Feed/FunnelEventController.php`, `resources/js/cart.js`, `routes/web.php`, `bootstrap/app.php`
      Mudança: endpoint público com throttle, 204, dedupe por sessão e etapa.
      Cobre: RF-21, RNF-05, CT-04
      Acceptance criteria: evento duplicado na sessão não infla; responde 204 com throttle.
      Testes: `tests/Feature/FunnelEventTest.php` — dedupe
- [ ] T62 — Estatísticas com gate por metrics_level
      Arquivos: `app/Services/Orders/OrderStats.php`, `resources/views/pages/panel/⚡views.blade.php`
      Mudança: contadores simples em todos os planos; funil, rankings e pico só para `por_prato` ou superior.
      Cobre: RF-21
      Acceptance criteria: plano `cardapio_total` vê só contadores (bloqueio/upgrade nos demais); `por_prato` vê tudo.
      Testes: `tests/Feature/OrderStatsGateTest.php` — gate
- [ ] T63 — Fechamento Fase 6
      Arquivos: `tests/Feature/GrowthFlowTest.php`
      Mudança: cupom, fidelidade e cancelamento coerentes; regressão e colisão de rotas.
      Cobre: RF-25, RF-17, RF-18
      Acceptance criteria: cancelar devolve cupom e selo uma única vez; suíte verde.
      Testes: `tests/Feature/GrowthFlowTest.php` — fluxo

## Phase 8: Operação avançada e acabamento

Antes de implementar, leia:
1. `.spec/features/pedidos-caixa-degusta/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/pedidos-caixa-degusta/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T64 — Motoboys (cadastro e tela)
      Arquivos: `database/migrations/2026_11_06_000001_create_couriers_table.php`, `app/Models/Courier.php`, `app/Actions/Couriers/SaveCourier.php`, `resources/views/pages/panel/⚡couriers.blade.php`, `routes/web.php`
      Mudança: cadastro com nome, WhatsApp, valor por entrega e token revogável; rota `panel.couriers`.
      Cobre: RF-22
      Acceptance criteria: criar, editar e revogar token; alcançável pelo menu.
      Testes: `tests/Feature/CourierManagementTest.php` — CRUD e revogação
- [ ] T65 — Atribuição e acerto
      Arquivos: `app/Actions/Couriers/AssignCourier.php`, `app/Actions/Couriers/SettleCourierRun.php`, `resources/views/pages/panel/⚡orders.blade.php`
      Mudança: atribuir motoboy no pedido; acerto lança despesa via `RecordMovement` com referência.
      Cobre: RF-22, RNF-04
      Acceptance criteria: fechar acerto 2x lança 1 despesa.
      Testes: `tests/Feature/CourierSettlementTest.php` — idempotência
- [ ] T66 — Link do entregador (CT-05)
      Arquivos: `app/Http/Controllers/CourierLinkController.php`, `resources/views/courier/show.blade.php`, `routes/web.php`
      Mudança: `GET /entregador/{token}` com só os pedidos em rota do dia do motoboy, marcar entregue, 404 se revogado.
      Cobre: RF-22, CT-05
      Acceptance criteria: token de um motoboy não lê pedidos de outro; revogado retorna 404; não expõe WhatsApp nem sobrenome.
      Testes: `tests/Feature/CourierLinkTest.php` — isolamento e revogação
- [ ] T67 — Comanda 58/80 mm
      Arquivos: `resources/views/panel/ticket.blade.php`, `resources/js/ticket-print.js`, `app/Http/Controllers/Panel/TicketController.php`, `routes/web.php`
      Mudança: view de impressão com largura 58/80, comanda de teste, opções por aparelho em localStorage.
      Cobre: RF-23
      Acceptance criteria: impressão contém código, itens, escolhas, observações e endereço; nenhuma coluna de banco guarda as opções.
      Testes: `tests/Feature/TicketPrintTest.php` — conteúdo
- [ ] T68 — Taxa por distância
      Arquivos: `database/migrations/2026_11_06_000002_create_delivery_distance_tiers_table.php`, `app/Models/DeliveryDistanceTier.php`, `app/Services/Orders/OrderPricer.php`, `resources/views/pages/panel/⚡settings.blade.php`, `resources/js/checkout.js`
      Mudança: faixas km, distância calculada no servidor, "a combinar" fora das faixas.
      Cobre: RF-24a, RF-05
      Acceptance criteria: faixas 0-3 km R$ 5 e 3-6 km R$ 8: 4 km ⇒ R$ 8; 7 km ou sem distância ⇒ "a combinar"; desligado ⇒ taxa do bairro.
      Testes: `tests/Unit/DistanceFeeTest.php` — faixas
- [ ] T69 — PWA e menu "Mais"
      Arquivos: `public/manifest.webmanifest`, `public/sw.js`, `resources/views/layouts/panel.blade.php`
      Mudança: manifesto, service worker mínimo e menu "Mais" em viewport ≤ 640 px.
      Cobre: RF-24
      Acceptance criteria: manifesto válido; "Mais" presente no layout.
      Testes: `tests/Feature/PanelPwaTest.php` — manifesto
- [ ] T70 — Guia de 9 passos
      Arquivos: `app/Services/Panel/OnboardingProgress.php`, `resources/views/pages/panel/⚡home.blade.php`
      Mudança: 9 passos derivados dos dados, % de conclusão, próximo passo.
      Cobre: RF-24
      Acceptance criteria: 9 passos listados; % = concluídos / 9.
      Testes: `tests/Feature/OnboardingGuideTest.php` — percentual
- [ ] T71 — Central de ajuda em vídeo
      Arquivos: `config/help_videos.php`, `resources/views/pages/panel/⚡help.blade.php`, `routes/web.php`
      Mudança: lista com busca por termo e WhatsApp de suporte; rota `panel.help`.
      Cobre: RF-24
      Acceptance criteria: busca retorna vídeos pelo termo; alcançável pelo menu.
      Testes: `tests/Feature/HelpCenterTest.php` — busca
- [ ] T72 — Estilos de cardápio (Vidro, Claro, Rústico)
      Arquivos: `database/migrations/2026_11_06_000003_add_menu_style_to_restaurants.php`, `resources/views/pages/panel/⚡appearance.blade.php`, `resources/views/feed/show.blade.php`, `resources/css/app.css`
      Mudança: seleção em Aparência com preview ao vivo; estilo aplicado ao feed, carrinho, checkout e `/p`.
      Cobre: RF-24, UI-02
      Acceptance criteria: estilo aplica ao feed sem quebrar o preview existente; `AppearanceTest` verde.
      Testes: `tests/Feature/AppearanceTest.php` — estilos
- [ ] T73 — Landing: comparativo e calculadora de comissão
      Arquivos: `resources/views/marketing/home.blade.php`, `config/landing.php`, `tests/Feature/LandingPageTest.php`
      Mudança: seção de comparativo de mensagens e calculadora de comissão.
      Cobre: RF-24b
      Acceptance criteria: landing contém as duas seções; comissão = faturamento × percentual.
      Testes: `tests/Feature/LandingPageTest.php` — seções
- [ ] T74 — Documentação
      Arquivos: `docs/agents/project_overview.md`, `README.md`, `docs/agents/api_contracts.md`, `docs/agents/data_model.md`
      Mudança: remover "só de visualização" e documentar rotas e tabelas novas.
      Cobre: RF-24c
      Acceptance criteria: `grep -i "só de visualização" README.md docs/agents/project_overview.md` retorna 0 ocorrências.
      Testes: `grep -ci "só de visualização" README.md docs/agents/project_overview.md` — resultado 0
- [ ] T75 — Fechamento Fase 7
      Arquivos: `tests/Feature/RouteCollisionTest.php`
      Mudança: colisão final, regressão completa, pint e restart do worker.
      Cobre: RF-25
      Acceptance criteria: `./vendor/bin/sail artisan test` verde; sem rota duplicada.
      Testes: suíte completa
