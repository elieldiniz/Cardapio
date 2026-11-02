# Degusta — do cardápio em vídeo ao sistema de pedidos

> Planejamento criado em 2026-10-31 a partir da análise do Catálogo Prime (conta de teste "Docin", painel e catálogo do cliente) e do código atual do Degusta.
> Nada aqui foi implementado ainda. Este arquivo é só o plano.

## 1. Direção

O Degusta deixa de ser só um cardápio e passa a ser um sistema de **cardápio + pedidos + entrega + caixa**.

**Regra de ouro: não perder o que já é bom.** O feed de vídeo estilo Instagram, a grade de abertura, o visual com vidro fosco, a cor e a fonte da loja, o QR customizável e o preview ao vivo continuam como estão. Tudo que entra é construído em cima disso e deve parecer parte do mesmo produto.

A vantagem do Degusta sobre o Catálogo Prime é o **vídeo do prato vendendo**. O Catálogo Prime tem fotos num layout de lista comum. O plano usa isso: o pedido nasce dentro do feed, onde o cliente já está vendo o vídeo.

## 2. O que existe hoje no Degusta (e o que falta)

| Área | Hoje | Falta |
|---|---|---|
| Cardápio do cliente | Feed em vídeo + grade, categorias, ficha do prato (bottom sheet), compartilhar, tema (cor e fonte) | Adicionar ao pedido, carrinho, checkout |
| Prato | Nome, preço, descrição, selos (Badge), variações simples (nome + preço), status (ativo/esgotado/oculto) | Complementos/adicionais, venda por kg, pizza, promoção "de/por", destaque |
| Pedido | **Nada** | Tudo |
| Entrega | **Nada** | Taxa por bairro/distância, frete grátis, pedido mínimo, retirada |
| Horário | **Nada** | Horário por dia, aberto/fechado, aceitar pedido fechado |
| Pagamento | **Nada** | Pix (QR + copia e cola), dinheiro/troco, cartão, fiado |
| Painel | Pratos, categorias, aparência, QR, visualizações, assinatura, suporte | Gestor de pedidos, caixa, clientes, entrega, cupons, fidelidade |
| Métricas | Visualizações por prato (watch-time) | Funil de pedido, origem, horário de pico, mais pedidos |
| Cupons | Só os da **plataforma** (cobrança Stripe) | Cupons do **restaurante** (nome diferente, para não conflitar) |

## 3. Funcionalidades observadas, em ordem de prioridade

**Critério:** (1) quanto o dono ganha no dia a dia, (2) quanto os outros itens dependem dele, (3) esforço.

### P0 — Sem isso não existe pedido
1. **Carrinho e pedido pelo WhatsApp com mensagem pronta e código do pedido.** É o coração do produto. O cliente monta o pedido e ele chega pronto no WhatsApp do dono (itens, endereço, taxa, pagamento). Substitui a conversa de ida e volta ("11 mensagens para 1 pedido").
2. **Complementos e adicionais** (grupos reutilizáveis: obrigatório ou opcional, escolhe 1 ou vários, limite, pode repetir, preço extra). Quase todo prato precisa disso (tamanho, adicional, ponto da carne, borda). Já vêm 9 modelos prontos no Catálogo Prime (Tamanhos, Adicionais, Bordas, Bebida do combo, Molhos, Ponto da carne, Tipo de corte, Açaí, do zero).
3. **WhatsApp de pedidos, endereço, horário e dados do restaurante** nas configurações. Base de todo o resto.
4. **Pedido salvo no sistema (não só mensagem).** Cada pedido vira um registro com status. É o que permite gestor, caixa e métricas. O Catálogo Prime faz isso e rastreia "WhatsApp abriu" e "Cliente confirmou envio".

### P1 — O dia a dia da loja
5. **Entrega e retirada:** taxa por bairro (e depois por distância), "outro bairro = a combinar", frete grátis acima de X, pedido mínimo para entrega, retirada na loja.
6. **Horário de funcionamento automático:** por dia da semana, com mais de um turno, "Aberto agora / fechado", opção de aceitar pedido fechado, botão de abrir/fechar a loja na hora.
7. **Pagamento:** Pix com QR Code e copia e cola **com o valor do pedido**, dinheiro com troco, débito e crédito na entrega.
8. **Gestor de pedidos** em colunas: Novos, Em preparo, Pronto/Em rota, Finalizados. Aceitar, recusar, chamar no WhatsApp, avisar o cliente com mensagem pronta, aviso sonoro, busca e filtro por período.
9. **Página de acompanhamento do cliente** (`/p/CÓDIGO`) com linha do tempo e horário de cada etapa, atualizando sozinha.

### P2 — Controle financeiro
10. **Caixa:** abrir com troco, entradas por forma de pagamento, despesa (com categoria), retirada/sangria, reforço de troco, fechar contando o dinheiro.
11. **Finalizar pedido registrando o pagamento:** Pix, dinheiro (calcula troco), cartão, **fiado**, **dividido**.
12. **Fiado:** lista de quem deve, total a receber, nome e WhatsApp para cobrar.
13. **Relatório financeiro:** hoje, ontem, 7 dias, mês, mês passado. Vendas, ticket médio, recebido, despesas, resultado.
14. **PDV de balcão:** venda presencial no mesmo caixa, com troco, desconto, nome do cliente, enviar para a cozinha, imprimir comanda.

### P3 — Vender mais e mais variedade de produto
15. **Pizza:** tamanhos com fatias e máximo de sabores, sabores com seção e ingredientes, preço por sabor e tamanho, meio a meio pelo sabor mais caro (ou média).
16. **Venda por kg** (passos de 0,5 kg), com **ajuste de valor depois da pesagem**.
17. **Promoção "de R$ X por R$ Y", destaque no topo e banner promocional** com data limite.
18. **Cupons do restaurante** (código ou link, % ou valor).
19. **Cartão fidelidade** (selos por pedido finalizado, prêmio em %, R$ ou brinde, pedido mínimo, selos voltam se cancelar).
20. **Clientes:** lista de quem pediu, com apagamento automático após 2 anos (privacidade).
21. **Origem dos pedidos** (anúncio, Instagram, WhatsApp, QR Code) com links com `?origem=`, e relatório por origem.
22. **Estatísticas de pedido:** funil (visitou, adicionou, iniciou, WhatsApp abriu, confirmou), conversão, horário de pico, mais vistos/adicionados/pedidos, tempo médio de preparo.

### P4 — Operação avançada e acabamento
23. **Motoboys:** cadastro, escolher quem leva cada pedido, contagem de entregas e acerto, link do entregador.
24. **Impressão de comanda** (58/80 mm, automática para a cozinha).
25. **Painel instalável no celular (PWA)** e guia de primeiros passos ("sua loja está 22% pronta").
26. **Ajuda em vídeos**, landing atualizada (calculadora de comissão, comparativo de mensagens) e plano comercial.

### 3.1 Itens adicionais (2ª varredura, que a 1ª lista não trazia)

Abri os formulários e telas que eu só tinha visto por cima. Cada item abaixo já está encaixado numa fase da seção 7.

**Cardápio e catálogo**
- **Lista de produtos com ações rápidas:** busca, filtro por categoria, abas Todos/Ativos/Indisponíveis/Ocultos, **editar o preço direto na lista**, ligar/desligar "disponível" e "visível" sem abrir o produto, **duplicar** e excluir. *(Fase 1)*
- **Dois interruptores por produto:** "Disponível hoje" (aparece como "Indisponível no momento") e "Mostrar no catálogo" (oculto). O Degusta já tem `esgotado` e `oculto`, então só precisa expor isso na lista. *(Fase 1)*
- **Categoria com ícone e horário próprio** ("Mostrar só em um horário", passando da meia-noite): é o **cardápio do almoço e do jantar**, que some fora do horário. O ícone também aparece na mensagem do WhatsApp. *(Fase 0)*
- **Categorias e produtos já prontos por tipo de negócio** no cadastro (lanchonete, pizzaria, marmitaria, açaí, doceria), para a loja nascer com categorias certas. *(Fase 0)*
- **Aviso de "Catálogo em preparação"** com botão de WhatsApp quando a loja ainda não tem produtos. *(Fase 1)*
- **Estado "Ver horários"** no topo do catálogo, mostrando a grade semanal. *(Fase 0)*
- **Metadados de compartilhamento (link preview)** por loja e por produto. *(O Degusta já faz.)*

**Pedido e checkout**
- **Cupom no checkout** (campo "Tem um cupom de desconto?" + botão Aplicar, com o total atualizando). *(Fase 6)*
- **Endereço, nome e WhatsApp salvos no aparelho** para o próximo pedido, com aviso de privacidade ("endereço e observações são apagados em até 30 dias; nome e WhatsApp ficam com a loja"). *(Fase 1)*
- **Mensagem de privacidade e termos por loja** (`/privacidade?loja=slug`). *(Fase 1)*
- **Botão "Falar com a loja"** na página de acompanhamento e **"Ver cardápio"**. *(Fase 1)*
- **"Fazer outro pedido"** depois de enviar. *(Fase 1)*
- **Pedido mínimo** que bloqueia a entrega (e só deixa retirar). *(Fase 1)*

**Gestor e operação**
- **Recusar pedido** (com a ação no menu "Mais ações"). *(Fase 2)*
- **Editar o pagamento** de um pedido já finalizado ("Editar" no cartão). *(Fase 3)*
- **"Finalizar sem registrar pagamento"**. *(Fase 3)*
- **Ajuste de valor** do pedido (produtos e taxa de entrega separados) antes de finalizar. *(Fase 5, junto com kg)*
- **"Abrir no aplicativo WhatsApp deste computador"**: opção que faz os botões abrirem o WhatsApp Desktop em vez de abrir abas do WhatsApp Web. *(Fase 2)*
- **"Testar este número"** do WhatsApp nas configurações. *(Fase 0)*
- **Impressão da comanda, opções:** largura 58 ou 80 mm, "imprimir ao aceitar", "imprimir pedidos novos sozinho" (para computador fixo no balcão), comanda de teste, e dicas (Chrome `--kiosk-printing`, RawBT no Android). Valem **por aparelho**. *(Fase 7)*
- **Contador de pedidos novos no menu** ("1 pedidos a conferir"). *(Fase 2)*

**Motoboys**
- Cadastro com **nome, WhatsApp e valor sugerido por entrega** (o dono ajusta em cada pedido: R$ 7 perto, R$ 15 longe).
- Escolher o motoboy no pedido, contar as entregas e **fechar o acerto**; o valor entra **sozinho nas despesas do caixa**, sem lançar à mão.
- Mandar ao motoboy o **endereço com a rota no mapa** e abrir o **link do entregador** (ele vê só os pedidos dele e marca "entregue"). *(Fase 7)*

**Clientes e fidelidade**
- Lista com nome, WhatsApp, nº de pedidos e "último há 17 min", selo **"🆕 novo"** e contador de **"Fiéis (5+ pedidos)"**. *(Fase 6)*
- **"Apagar dados"** do cliente (LGPD) e expurgo automático após 2 anos. *(Fase 6)*
- Fidelidade: o selo entra ao finalizar, **prêmio: % OFF, R$ OFF ou brinde**, mínimo para ganhar selo, prévia "O cliente vê", e contadores (clientes, cartões completos, prêmios resgatados). *(Fase 6)*

**Cupons do restaurante (campos completos)**
- Código **com gerador aleatório 🎲**, tipo (**porcentagem ou valor em R$**), **pedido mínimo**, **limite de usos**, **vale a partir de / vale até**, **descrição** que o cliente vê (80 caracteres). Ideias prontas: `BEMVINDO10`, `FRETEGRATIS`, `SEXTOU5`. Cupom também por **link**. *(Fase 6)*

**Promoções**
- **Banner** no topo (título 70, texto 140, data limite, foto, prévia ao vivo) e botão "Ver ofertas" que aparece sozinho quando há produto em promoção; filtro "Ofertas" no catálogo. *(Fase 5)*

**Estatísticas (mais do que eu tinha listado)**
- Funil com **taxa de passagem entre etapas**, **retirada × entrega** (% e quantidade), **valor aceito pela loja**, **tempo médio de preparo**, **pedidos com cupom**, gráficos de **visitas por horário**, **visitas por dia**, **pedidos por dia**, e o rótulo **"Horário de pico"**.
- Rankings: **mais vistos, mais adicionados, mais pedidos** e **complementos mais escolhidos** (ex.: Bacon 2 vezes). Dica automática: "muito visto e pouco pedido pode precisar de foto melhor, descrição ou promoção". *(Fase 6)*

**QR e divulgação**
- **Plaquinha pronta** (A5/A4) com logo, QR e chamada "Aponte a câmera e faça seu pedido pelo WhatsApp", **só o QR avulso**, **links com origem** (anúncio, Instagram, WhatsApp, Facebook) e **nome da campanha**. *(O Degusta já tem QR customizável e display de mesa, está à frente aqui. Só faltam os links com origem.)* *(Fase 6)*

**Plataforma e acabamento**
- **Guia de primeiros passos com % pronto** (9 passos: WhatsApp, logo, 5 produtos, fotos, horário, entrega, Pix, pedido de teste, divulgar), com "próximo passo" em destaque e link para a tela certa. *(Fase 7)*
- **Faixa fixa do teste grátis** ("termina em 13 dias") com botão de assinar. *(O Degusta já tem trial/assinatura, só ligar à faixa.)*
- **Central de ajuda em vídeos** com busca (primeiros passos, cardápio, complementos, taxa de entrega, pedidos, PDV/QR/cupons/fidelidade) + WhatsApp de suporte. *(Fase 7)*
- **Painel instalável no celular** e menu "Mais" no mobile para telas que não cabem na barra. *(Fase 7)*
- **Rodapé "Feito com …"** no catálogo da loja, com link de aquisição. *(O Degusta já tem, com `removes_branding` por plano.)*
- **Landing:** comparativo "11 mensagens vs 1 mensagem", **calculadora de comissão** (vendas por mês × 12/20/27% de app de delivery), oferta com urgência, FAQ, segmentos (lanchonete, pizzaria, marmitaria, açaí, doceria, pastelaria), botão de WhatsApp. *(Fase 7)*

## 4. Onde o Degusta deve ser melhor (e não copiar)

- **Pedido dentro do feed.** O Catálogo Prime tem lista com foto. No Degusta o botão "Adicionar" fica no vídeo e na ficha do prato (bottom sheet), e uma barra "Ver pedido · 3 itens · R$ 129,90" fica fixa embaixo, no mesmo estilo de vidro do feed.
- **Ficha do prato com o vídeo ao fundo** enquanto escolhe adicionais, sem sair da tela.
- **Tema da loja aplicado em tudo:** cor, fonte e capa já existem. O carrinho, o checkout e a página de acompanhamento herdam isso (o Catálogo Prime tem só 2 temas fixos, Clean e Rústico).
- **Estilos de cardápio** (ex.: Vidro/Escuro, Claro/Clean, Rústico), escolhidos em Aparência, com o preview ao vivo que já existe.
- **Mesma barra, sem tela cheia de formulário:** checkout em etapas curtas (receber → endereço → pagamento) em vez de um formulário longo.
- **Corrigir as falhas que vimos no concorrente** (seção 8).

## 5. Decisões de arquitetura

1. **O feed continua em Blade + JS puro (sem Livewire).** O comentário em `feed/show.blade.php` explica o motivo (rolagem fluida em celular fraco). O carrinho é JS puro com `localStorage` por loja, e o servidor **recalcula todos os preços** ao receber o pedido (nunca confia no valor vindo do navegador).
2. **O painel continua em Livewire** (`pages::panel.*`, `PanelNavigation`). As telas novas seguem o mesmo padrão e os componentes de `components/ui`.
3. **Pedido guarda um "retrato" (snapshot)** dos itens: nome, preço e escolhas no momento da compra. Mudar o cardápio depois não altera pedidos antigos.
4. **Dinheiro:** manter o padrão do projeto (`decimal(…,2)` e `App\Support\Money`). Totais são sempre calculados no servidor.
5. **Tabelas de apoio** (status do pedido, forma de pagamento, tipo de movimento) seguem o padrão de lookup que o projeto já usa (`IsLookupTable`, `LookupSeeder`).
6. **Nome da tabela de cupons do restaurante:** `promo_codes`, para não colidir com `coupons` (cobrança da plataforma).
7. **Variações atuais (`dish_variants`) continuam.** Elas significam "preço alternativo do prato" (ex.: Pequena R$ 30 / Grande R$ 55). Os complementos significam "extras que somam ao preço". As duas coisas convivem.
8. **Multi-loja e isolamento:** todo dado novo carrega `restaurant_id` e é protegido por policy, como já é feito.
9. **Testes:** Pest, um arquivo de feature por área, rodando via `./vendor/bin/sail artisan test`.
10. **Planos:** decidir o que cada plano inclui (seção 9). Hoje `plans` tem `dish_limit`, `removes_branding` e nível de métricas.

## 6. Modelo de dados (rascunho)

**Configuração da loja** (uma linha por restaurante, `restaurant_order_settings`)
`whatsapp_number`, `address`, `accepts_orders` (loja aberta/fechada manual), `hours_enabled`, `accepts_when_closed`, `delivery_mode` (`a_combinar` | `bairro` | `distancia`), `free_delivery_over`, `min_order_delivery`, `pix_key`, `pix_holder`, `pix_city`, `pizza_pricing_rule` (`maior` | `media`), `lat`, `lng`.

**Horários:** `business_hours` (restaurant, weekday, shift, opens_at, closes_at).
**Entrega:** `delivery_zones` (restaurant, name, fee, is_active, display_order), `delivery_distance_tiers` (fase posterior).

**Complementos:**
- `option_groups` (restaurant, name, mode `one`|`many`, max_choices, is_required, allow_repeat, display_order, is_active)
- `options` (group, name, price nullable, is_available, display_order)
- `dish_option_group` (dish, group, display_order)

**Prato (colunas novas):** `sale_type` (`unit`|`kg`), `kind` (`common`|`pizza`), `promo_price` nullable, `is_featured`.
**Pizza:** `pizza_sizes` (dish, name, slices, max_flavors, is_available, order), `pizza_flavors` (dish, name, section, ingredients, is_available), `pizza_flavor_prices` (flavor, size, price).

**Pedido:**
- `orders` (restaurant, code único por loja, tracking_token, status_id, fulfillment `entrega`|`retirada`|`balcao`, channel `feed`|`pdv`, origin, customer_id, customer_name, customer_phone, endereço (rua, número, complemento, bairro, referência), delivery_fee, subtotal, discount, promo_code_id, total, payment_method, change_for, notes, `whatsapp_opened_at`, `customer_confirmed_at`, `accepted_at`, `dispatched_at`, `finished_at`, `cancelled_at`, courier_id, cash_session_id)
- `order_items` (order, dish nullable, name_snapshot, quantity, unit_price, total, notes, sale_type, weight, `selections` JSON com tamanho/sabores/complementos)
- `order_payments` (order, method, amount, received, change) — permite pagamento dividido e fiado
- `order_status_history` (order, status, at) — alimenta a linha do tempo e o tempo médio de preparo
- `customers` (restaurant, name, whatsapp, last_order_at, orders_count, first_order_at)

**Caixa:** `cash_sessions` (restaurant, opened_at, opening_float, closed_at, counted_cash, expected_cash, notes), `cash_movements` (session, type `venda`|`despesa`|`retirada`|`reforco`, method, amount, category, description, order nullable), `receivables` (fiado: restaurant, customer, name, phone, amount, paid_at).

**Crescimento:** `promo_codes` (restaurant, code, type, value, min_order, expires_at, uses, is_active), `loyalty_programs` (restaurant, stamps_required, reward_type, reward_value, min_order, is_active), `loyalty_cards` (program, customer, stamps, completed_at, redeemed_at), `couriers` (restaurant, name, phone, fee_per_delivery, token), `promo_banners` (restaurant, title, text, until, photo).

**Funil:** eventos de carrinho/checkout, reaproveitando o padrão do `dish_views` (endpoint com throttle, sem login).

## 7. Fases de execução

Cada fase termina com testes passando, a UI conferida no navegador (feed no celular e painel no desktop) e um commit. Nenhuma fase quebra a anterior.

### Fase 0 — Fundação (pequena)
- Tela **Configurações do restaurante**: WhatsApp de pedidos (com "testar este número"), endereço, Pix (chave, titular, cidade).
- Horário de funcionamento por dia, com turnos, e o estado "Aberto agora / Fechado" calculado no servidor (fuso `America/Porto_Velho` ou o da loja).
- Botão manual "Loja aberta / fechada" e "Ver horários" no topo do cardápio do cliente.
- **Categoria com ícone e horário próprio** (cardápio de almoço e de jantar que some fora do horário).
- **Categorias iniciais por tipo de negócio** no cadastro (lanchonete, pizzaria, marmitaria, açaí, doceria).
- Menu do painel reorganizado em grupos (Cardápio, Pedidos, Financeiro, Divulgação, Conta), mantendo o visual atual.
- Decisão de planos (seção 9) e flag de funcionalidade por plano.
- **Pronto quando:** o dono salva as configurações e o feed mostra "Aberto agora" ou "Fechado · abre às 18h".

### Fase 1 — O cliente pede (o maior salto)
- **Complementos:** CRUD de grupos com os 9 modelos prontos, prévia ao vivo "como o cliente vê", ligar grupos ao prato no formulário do prato. *(Aprender com o Catálogo Prime: tudo salvar de primeira, inclusive ao criar.)*
- **Feed:** botão de adicionar nos vídeos e na ficha do prato, ficha com escolhas (tamanho/variação, adicionais com quantidade, observação de 140 caracteres), barra fixa "Ver pedido", carrinho em bottom sheet com editar e remover, no estilo de vidro.
- **Checkout** em etapas: retirada ou entrega → endereço (nome, rua, número, complemento, bairro com taxa, referência, WhatsApp) → pagamento (Pix, dinheiro com troco, débito, crédito) → observação.
- **Entrega por bairro** (`delivery_zones`), "outro bairro = a combinar", frete grátis acima de X, pedido mínimo, mostrando "faltam R$ X para entrega grátis".
- **Lista de produtos** com busca, filtros (Todos/Ativos/Indisponíveis/Ocultos), preço editável na linha, interruptores de disponível e visível, duplicar e excluir.
- **Endereço e contato salvos no aparelho**, aviso de privacidade e "Fazer outro pedido" depois de enviar.
- **Servidor recalcula tudo** e cria o pedido com código curto único (ex.: `D-X6ECG`), `tracking_token` e status "novo".
- **Mensagem do WhatsApp** montada com itens, escolhas, subtotal, taxa, total, forma de recebimento, pagamento (e troco), endereço e o link de acompanhamento.
- **Confirmação "Conseguiu enviar? Sim, enviei / Ainda não"** que grava `whatsapp_opened_at` e `customer_confirmed_at`.
- **Página `/p/{token}`** com a linha do tempo, tema da loja e atualização automática (polling leve).
- **Pronto quando:** dá para fazer um pedido completo pelo celular, ele fica salvo e o WhatsApp abre com a mensagem certa.

### Fase 2 — Gestor de pedidos
- Tela **Pedidos** em 4 colunas (arrastar ou botões), cartão com todos os dados do pedido, selos "WhatsApp abriu" e "Cliente confirmou envio", "Cliente novo", busca, filtro hoje/ontem/7 dias, valor do período, contador de pedidos novos no menu e opção de abrir no WhatsApp Desktop.
- Ações: aceitar (definir taxa se "a combinar"), recusar (em "Mais ações"), em preparo → saiu para entrega → finalizar, **avisar o cliente pelo WhatsApp com mensagem pronta**.
- **Aviso sonoro** e contador no menu quando chega pedido; atualização sem recarregar.
- Histórico de status (alimenta a linha do tempo do cliente).
- **Pronto quando:** o pedido da Fase 1 aparece na hora, anda pelas colunas e o cliente vê cada etapa na página de acompanhamento.

### Fase 3 — Caixa e financeiro
- **Finalizar com pagamento:** Pix, dinheiro (troco), cartão, fiado, dividido, ou "finalizar sem registrar".
- **Caixa:** abrir com troco, "dinheiro que deve estar na gaveta", vendas por forma de pagamento, despesa com categoria, retirada, reforço, movimentos em ordem, **fechar contando o dinheiro e mostrando a diferença**.
- **Fiado** com lista e baixa de pagamento. **Relatório** por período (hoje, ontem, 7 dias, mês, mês passado).
- **Pronto quando:** o pedido finalizado cai sozinho no caixa e o relatório bate com os movimentos.

### Fase 4 — PDV de balcão
- Grade por categoria com busca, itens com as mesmas escolhas do feed, desconto, nome do cliente, enviar para a cozinha (entra em "Em preparo"), cobrança com troco, tudo no mesmo caixa.
- **Pronto quando:** uma venda de balcão aparece no caixa e no relatório junto com o delivery.

### Fase 5 — Produtos avançados
- **Pizza** (tamanhos, sabores, preço por sabor e tamanho, meio a meio).
- **Venda por kg** com passo de 0,5 kg e **ajuste de valor depois de pesar**.
- Promoção "de/por", destaque no topo, selos (o `Badge` atual já cobre parte).
- **Pronto quando:** pizza meio a meio e produto por kg fecham o valor certo, no cliente e no gestor.

### Fase 6 — Crescimento
- **Cupons do restaurante** completos (gerador de código, % ou R$, mínimo, limite de usos, validade, descrição, por link) e o campo de cupom no checkout.
- **Cartão fidelidade** (selos ao finalizar, prêmio em %, R$ ou brinde, mínimo, selos voltam ao cancelar).
- **Clientes** (novo, fiéis 5+, "Apagar dados", expurgo em 2 anos).
- **Origem dos pedidos** (links `?origem=` e QR já marcado, nome da campanha).
- **Estatísticas completas:** funil com taxas, retirada × entrega, tempo médio de preparo, pedidos com cupom, visitas e pedidos por horário e por dia, horário de pico, mais vistos/adicionados/pedidos e **complementos mais escolhidos**, com dica automática.
- **Pronto quando:** o dono vê quanto cada origem vendeu e quais pratos mais convertem.

### Fase 7 — Operação avançada e acabamento
- **Motoboys:** cadastro (nome, WhatsApp, valor por entrega), atribuição no pedido, contagem e acerto que cai sozinho nas despesas do caixa, endereço com rota no mapa, link do entregador.
- **Impressão de comanda:** 58/80 mm, imprimir ao aceitar, imprimir pedidos novos sozinho, comanda de teste, opções por aparelho.
- Taxa **por distância** (localização do cliente), PWA do painel e menu "Mais" no celular.
- Guia "sua loja está X% pronta" (9 passos) e central de ajuda em vídeos com busca.
- **Estilos de cardápio** em Aparência (ex.: Vidro, Claro, Rústico), com preview ao vivo.
- Landing e planos atualizados (comparativo de mensagens, calculadora de comissão, preço).

## 8. Armadilhas que vimos no Catálogo Prime (e como evitar)

| O que aconteceu lá | Como fazer aqui |
|---|---|
| Pizza criada **sem os sabores**: tamanhos salvaram, sabores e preços não, e o catálogo mostrou "Indisponível / R$ 0,00". | Salvar tudo numa única transação e testar criar-e-ler logo em seguida. Bloquear a publicação de pizza sem sabor com preço. |
| Complemento ligado ao criar o produto **não persistiu**. | Teste de feature: criar prato com grupos e conferir que voltam na edição. |
| Campo de preço com máscara que **aceita digitação, mas quebra com valor colado** (virou R$ 3.000,00). | Usar `Money::parse` no servidor para qualquer formato ("32,90", "R$ 32,90", "3290"), e testar. |
| **Horário automático fechou a loja** à noite de sábado sem aviso. | Mostrar no painel o estado atual e o próximo horário, e um aviso quando a loja está fechada para clientes. |
| Mensagem e valores calculados no navegador. | O servidor recalcula tudo e grava o retrato do pedido. |

## 9. Decisões que preciso de você

Marquei a opção que eu recomendo.

1. **Quem tem pedidos?** (a) Todos os planos têm pedidos, e os planos se diferenciam por vídeo, limite de pratos e métricas **(recomendado)**; (b) pedidos só em planos pagos mais caros.
2. **Pagamento online (Pix/cartão dentro do Degusta)?** Recomendo **não agora**: manter o fluxo do Catálogo Prime (Pix direto para a conta do dono, sem taxa, conferido no app do banco). Cobrança online pode vir depois.
3. **O que chamamos de "prato"?** Hoje o painel diz "Pratos". Para lanchonete/pizzaria/açaí, **"Produtos"** serve melhor. Recomendo trocar o rótulo no painel e manter `dishes` no código.
4. **Mensagem de WhatsApp:** link `wa.me`/`api.whatsapp.com` como o concorrente (recomendado, simples) ou integração com a API oficial do WhatsApp (custo e aprovação).
5. **Pedido mínimo e impressão:** deixar a impressão da comanda (térmica) para a Fase 7 (recomendado) ou antecipar.
6. **Nome do módulo no painel:** "Pedidos" e "Financeiro", como no concorrente.

## 10. Como vamos trabalhar

- Uma fase por vez, na ordem acima. Ao fim de cada uma: testes (`./vendor/bin/sail artisan test`), conferência no navegador e commit.
- Migrações novas respeitam o padrão do projeto (tabelas de apoio por lookup, FKs na ordem certa para o MySQL).
- Depois de mexer em código PHP, reiniciar o worker (`./vendor/bin/sail restart queue`).
- Nenhum e-mail ou WhatsApp real é disparado em desenvolvimento sem você pedir.
