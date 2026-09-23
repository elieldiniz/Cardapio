# Custos e precificação — estudo para definir os preços

> **Status:** estudo para o futuro. Os preços cadastrados hoje (planos e pacotes) são provisórios.
> **Preços de fornecedores levantados em 23/09/2026.** Confira nas fontes antes de decidir — eles mudam.

Este documento explica **quanto custa operar o Degustou Cardápio** e **como calcular preços que dão lucro**. Os valores de planos e pacotes são editados no `/admin` (Planos / Pacotes de gerações), sem deploy.

---

## 1. Os custos, um por um

### 1.1 Geração de vídeo por IA — o custo que mais pesa

Cada **geração** = **1 clipe gerado**. Pedir 3 variações consome 3 gerações e custa 3 clipes ao fornecedor.

| Fornecedor / modelo | Preço | Clipe de 5 s | Clipe de 10 s |
|---|---|---|---|
| Runway **Gen-4 Turbo** (referência da spec) | 5 créditos/s × US$ 0,01 | **US$ 0,25** | **US$ 0,50** |
| Runway Gen-4.5 | 12 créditos/s × US$ 0,01 | US$ 0,60 | US$ 1,20 |
| Kling 3.0 (API) | ~US$ 0,08–0,10/s | ~US$ 0,40–0,50 | ~US$ 0,80–1,00 |

- **A duração do clipe dobra o custo.** O preset (`/admin → Presets de IA → Duração`) aceita de 5 a 10 s. **5 s é o mais barato.**
- Geração que falha no fornecedor **não é cobrada do restaurante**, mas pode ser cobrada pelo fornecedor, dependendo de quem falhou.
- O custo real de cada geração fica gravado em `video_generations.cost_usd` e aparece em `/admin → Custos x receita`.

### 1.2 Mux (hospedagem e reprodução dos vídeos)

| Item | Preço |
|---|---|
| Codificação (qualidade *basic*) | **grátis** |
| Armazenamento | US$ 0,003 por minuto de vídeo por mês |
| Reprodução (entrega) | **100.000 minutos grátis/mês**, depois US$ 0,001/min |
| Crédito do plano *pay as you go* | US$ 20/mês |
| MP4 estáticos (480p/720p, que o feed usa) | **preço não publicado — confirmar com o Mux** |

Na prática:
- **Armazenamento é quase zero:** um clipe de 10 s é 1/6 de minuto, ou US$ 0,0005/mês. Mil clipes custam cerca de US$ 0,50/mês.
- **Reprodução:** um restaurante com 1.000 visitas/mês, em que cada cliente assiste ~20 pratos por ~8 s, consome ~2.700 min. Os **100 mil minutos grátis cobrem ~37 restaurantes assim** antes de custar algo.
- ⚠️ Variações **não aprovadas** continuam guardadas no Mux. Apagar as descartadas é uma otimização futura.

### 1.3 Stripe (taxas de pagamento — Brasil)

| Taxa | Valor |
|---|---|
| Cartão nacional | **3,99% + R$ 0,39** por transação |
| Cartão internacional | +2% |
| Stripe Billing (assinaturas recorrentes) | **+0,7%** do valor cobrado |
| Pix | 1,19% (só por convite; o sistema aceita apenas cartão hoje) |

Quanto a Stripe fica de cada venda, com os preços atuais:

| Venda | Taxa Stripe | % do preço |
|---|---|---|
| Plano Básico R$ 30 (assinatura) | 3,99% + 0,7% + R$ 0,39 = **R$ 1,80** | 6,0% |
| Plano Pro R$ 60 (assinatura) | **R$ 3,20** | 5,3% |
| Pacote R$ 10 (pagamento único) | 3,99% + R$ 0,39 = **R$ 0,79** | 7,9% |
| Pacote R$ 20 (pagamento único) | **R$ 1,19** | 6,0% |

A taxa fixa de R$ 0,39 pesa muito em vendas pequenas. **Pacotes muito baratos perdem margem só com a taxa.**

### 1.4 Servidor (infraestrutura) — a definir

A spec ainda não escolheu hospedagem (Laravel Cloud está descartado). Referências:

| Opção | Preço/mês | Observação |
|---|---|---|
| DigitalOcean 2 GB | US$ 12 | sem datacenter no Brasil (mais latência) |
| DigitalOcean 4 GB | US$ 24 | idem |
| Hetzner | barato | **sem datacenter na América do Sul** |
| AWS São Paulo / Magalu Cloud / Locaweb | **pesquisar** | datacenter no Brasil |

Também entram na conta:
- **Banco gerenciado.** Opcional no início, porque o MySQL pode ficar na mesma máquina.
- **Armazenamento de fotos** (R2/S3), que é centavos.
- **Painel de deploy** (Forge/Ploi, pesquisar).
- **Domínio.**

O vídeo não passa pelo servidor (vai direto para o Mux), então um servidor pequeno aguenta muitos restaurantes. **O custo do servidor é fixo e se divide entre todos os clientes.**

### 1.5 Impostos — **consultar um contador**

A receita de SaaS paga impostos (ex.: Simples Nacional, com alíquota inicial na casa de ~6% para serviços, variando por faixa e atividade). Isso **não está em nenhuma conta do sistema** e precisa ser definido com um contador.

### 1.6 Câmbio

**Custos em dólar** (IA, Mux, servidor) e **receita em real**. Toda conta abaixo usa uma cotação; os exemplos usam **US$ 1 = R$ 5,50 (hipótese)**. A cotação real do mês é informada em `/admin → Custos x receita`.

---

## 2. Fórmulas

### Custo de 1 geração (em reais)
```
custo_geracao = custo_do_clipe_USD × cotação
```
Exemplo (Runway Gen-4 Turbo, 5 s): `0,25 × 5,50 = R$ 1,38`

### Preço mínimo de um pacote avulso
```
preço_mínimo = (gerações × custo_geracao + 0,39) ÷ (1 − 0,0399 − imposto% − margem%)
```

### Lucro de um plano (pior caso: restaurante usa todas as gerações do mês)
```
lucro = preço − taxa_stripe − imposto − (gerações_incluídas × custo_geracao) − parte_do_servidor
```
Na média nem todo restaurante usa todas as gerações todo mês. O pior caso mostra se o plano **pode** dar prejuízo.

---

## 3. Os preços atuais passam na conta?

Hipóteses: Runway Gen-4 Turbo, clipe de **5 s** (R$ 1,38/geração), imposto de 6%, servidor fora da conta.

| Oferta | Preço | Custo das gerações | Stripe | Imposto | **Sobra** |
|---|---|---|---|---|---|
| Plano Básico (15/mês) | R$ 30 | R$ 20,63 | R$ 1,80 | R$ 1,80 | **R$ 5,78** |
| Plano Pro (30/mês) | R$ 60 | R$ 41,25 | R$ 3,20 | R$ 3,60 | **R$ 11,95** |
| Pacote 20 gerações | R$ 10 | R$ 27,50 | R$ 0,79 | R$ 0,60 | **❌ −R$ 18,89** |
| Pacote 40 gerações | R$ 20 | R$ 55,00 | R$ 1,19 | R$ 1,20 | **❌ −R$ 37,39** |

Com clipe de **10 s** o custo das gerações dobra, e **os planos Básico e Pro também passam a dar prejuízo** no pior caso.

**Conclusões:**
1. **Os pacotes avulsos a R$ 0,50/geração dão prejuízo.** Uma geração custa ~R$ 1,38 (5 s) ou ~R$ 2,75 (10 s). O preço por geração avulsa deveria ficar **acima de ~R$ 2,00 (clipe de 5 s)**.
2. **Os planos só se pagam com clipe de 5 s** e com margem pequena no pior caso.
3. **Plano Grátis:** 5 gerações = ~R$ 6,88 de custo por cadastro. É custo de aquisição de cliente, e precisa entrar na conta de marketing.

### Exemplo de pacotes com margem (5 s, imposto 6%, margem de 30%)
| Pacote | Preço mínimo | Sugestão |
|---|---|---|
| 10 gerações | R$ 23,56 | R$ 24,90 |
| 20 gerações | R$ 46,48 | R$ 47,90 |

---

## 4. O que ainda precisa ser pesquisado

- [ ] **Fornecedor de IA definitivo** e o preço dele (testar qualidade × preço: Runway Gen-4 Turbo, Kling, Veo, Seedance…).
- [ ] **Duração padrão do clipe** (5 s ou mais), o maior fator de custo.
- [ ] **Preço dos MP4 estáticos no Mux**, que não está publicado; perguntar ao suporte.
- [ ] **Hospedagem**, de preferência com datacenter no Brasil, mais o custo de deploy/backup.
- [ ] **Impostos**, com um contador: regime e alíquota.
- [ ] **Cotação do dólar**: definir uma cotação de segurança para precificar (ex.: +10% sobre a atual).
- [ ] **Uso real médio de gerações por restaurante**, medido no piloto, para trocar o "pior caso" pela média.
- [ ] **Quanto custa trazer um cliente** (inclui as 5 gerações grátis).

## 5. Onde mudar os valores no sistema

| O quê | Onde |
|---|---|
| Preço, gerações/mês, limite de pratos dos planos | `/admin → Planos` (+ `stripe_price_id` do preço criado na Stripe) |
| Pacotes avulsos | `/admin → Pacotes de gerações` |
| Duração do clipe e fornecedor de IA | `/admin → Presets de IA` |
| Custo do Mux do mês e cotação do dólar | `/admin → Custos x receita` |

---

### Fontes (consultadas em 23/09/2026)
- Mux — https://www.mux.com/pricing/video
- Stripe Brasil — https://stripe.com/br/pricing
- Runway API — https://docs.dev.runwayml.com/guides/pricing/
- Kling API (agregadores de preço) — https://costgoat.com/pricing/kling · https://kling.ai/dev/pricing
- DigitalOcean — https://www.digitalocean.com/pricing/droplets
- Hetzner — https://www.hetzner.com/cloud/
