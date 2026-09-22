# Degustou Cardápio — Database Schema

<!-- inputs: project-description.md@sha256:de789e216b36 user-stories.md@sha256:8ee3dfe566da -->

## Overview

O modelo gira em torno de **restaurants** (o tenant/dono da conta) que possuem **categories** e **dishes**; cada prato tem no máximo um **video** ativo, de origem IA (rastreado por **video_generations**, que por sua vez usa um **ai_preset** e fotos de **dish_photos**) ou upload próprio. Cobrança roda sobre **plans** e as tabelas padrão do **Laravel Cashier** (`subscriptions`/`subscription_items`), com saldo de gerações controlado por **generation_balances** e seu extrato em **generation_ledger**. Acesso é por **users** com **role** (dono ou super_admin), e ações sensíveis do super admin ficam em **admin_logs**.

Convenções em vigor (stack detectada: Laravel + Livewire/Filament, ORM Eloquent):

- Tabelas no plural, `snake_case`; chave primária `id bigint [pk, increment]`; chaves estrangeiras `<singular>_id`.
- `created_at`/`updated_at` em toda tabela de domínio. Soft delete (`deleted_at`) **não é usado** neste schema — ocultar/suspender é sempre feito por `status_id` (ver Notes & Conventions).
- Todo campo categórico (status, tipo, papel, nível, ação) vira uma tabela de lookup com FK — nunca uma coluna enum/string.
- Upload de arquivo é sempre uma coluna `*_path`; quando um registro pode ter vários arquivos, existe uma tabela relacionada.
- `subscriptions`/`subscription_items` seguem o schema padrão do pacote Laravel Cashier — não são convertidas para o padrão de lookup table (ver exceção nas Notes & Conventions).

## Schema (DBML)

```dbml
// ===== Lookup tables =====

Table roles {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table restaurant_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table dish_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table video_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table video_origins {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table generation_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table generation_ledger_types {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table metrics_levels {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table discount_types {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table badges {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table admin_actions {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table ai_providers {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

// ===== Auth =====

Table users {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, null, note: 'null for super_admin']
  role_id bigint [ref: > roles.id, not null]
  name varchar [not null]
  email varchar [unique, not null]
  password varchar [not null]
  email_verified_at timestamp [null]
  remember_token varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table password_reset_tokens {
  email varchar [pk]
  token varchar [not null]
  created_at timestamp [null]
}

// ===== Billing =====

Table plans {
  id bigint [pk, increment]
  name varchar [not null]
  price_cents int [not null, default: 0]
  stripe_price_id varchar [null, unique]
  monthly_generations int [not null, default: 0, note: 'renews on subscription renewal, does not accumulate']
  initial_generations int [not null, default: 0, note: 'one-time grant, e.g. 5 for the Free plan; does not renew']
  dish_limit int [null, note: 'null = unlimited']
  removes_branding boolean [not null, default: false]
  metrics_level_id bigint [ref: > metrics_levels.id, not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table video_addon_packages {
  id bigint [pk, increment]
  name varchar [not null]
  generations_count int [not null]
  price_cents int [not null]
  stripe_price_id varchar [null, unique]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table coupons {
  id bigint [pk, increment]
  code varchar [unique, not null]
  stripe_coupon_id varchar [null, unique]
  discount_type_id bigint [ref: > discount_types.id, not null]
  discount_value decimal(10,2) [not null]
  is_active boolean [not null, default: true]
  expires_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

Table subscriptions {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, not null]
  name varchar [not null, note: 'Cashier subscription type, e.g. "default"']
  stripe_id varchar [unique, not null]
  stripe_status varchar [not null, note: 'Laravel Cashier package schema exception, see Notes & Conventions']
  stripe_price varchar [null]
  quantity int [null]
  trial_ends_at timestamp [null]
  ends_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

Table subscription_items {
  id bigint [pk, increment]
  subscription_id bigint [ref: > subscriptions.id, not null]
  stripe_id varchar [unique, not null]
  stripe_product varchar [not null]
  stripe_price varchar [not null]
  quantity int [null]
  created_at timestamp
  updated_at timestamp
}

// ===== Tenant =====

Table restaurants {
  id bigint [pk, increment]
  plan_id bigint [ref: > plans.id, not null]
  status_id bigint [ref: > restaurant_statuses.id, not null]
  name varchar [not null]
  slug varchar [unique, not null, note: 'feed URL: /r/{slug}']
  logo_path varchar [null]
  accent_color varchar [null]
  font varchar [null]
  stripe_id varchar [null, unique, note: 'Cashier billable id']
  created_at timestamp
  updated_at timestamp
}

// ===== Menu =====

Table categories {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, not null]
  name varchar [not null]
  display_order int [not null, default: 0]
  is_visible boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table dishes {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, not null]
  category_id bigint [ref: > categories.id, not null]
  status_id bigint [ref: > dish_statuses.id, not null]
  active_video_id bigint [ref: > videos.id, null, note: 'the single approved video shown in the feed; nullable until one is approved']
  name varchar [not null]
  price decimal(10,2) [not null]
  short_description varchar [null]
  description text [null]
  display_order int [not null, default: 0]
  created_at timestamp
  updated_at timestamp
}

Table dish_variants {
  id bigint [pk, increment]
  dish_id bigint [ref: > dishes.id, not null]
  name varchar [not null]
  price decimal(10,2) [not null]
  created_at timestamp
  updated_at timestamp
}

Table dish_photos {
  id bigint [pk, increment]
  dish_id bigint [ref: > dishes.id, not null]
  file_path varchar [not null]
  display_order int [not null, default: 0]
  created_at timestamp
  updated_at timestamp
}

Table badge_dish {
  badge_id bigint [ref: > badges.id, not null]
  dish_id bigint [ref: > dishes.id, not null]

  indexes {
    (badge_id, dish_id) [pk]
  }
}

// ===== AI generation =====

Table ai_presets {
  id bigint [pk, increment]
  provider_id bigint [ref: > ai_providers.id, not null]
  name varchar [not null]
  prompt text [not null]
  camera_movement varchar [not null]
  duration_seconds smallint [not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table video_generations {
  id bigint [pk, increment]
  dish_id bigint [ref: > dishes.id, not null]
  preset_id bigint [ref: > ai_presets.id, not null]
  provider_id bigint [ref: > ai_providers.id, not null, note: 'snapshot of the provider actually used, independent of later preset edits']
  status_id bigint [ref: > generation_statuses.id, not null]
  variations_requested smallint [not null, default: 2]
  cost_usd decimal(10,4) [null, note: 'null while pending; not charged to balance if the job errors']
  created_at timestamp
  updated_at timestamp
}

Table video_generation_photos {
  id bigint [pk, increment]
  video_generation_id bigint [ref: > video_generations.id, not null]
  dish_photo_id bigint [ref: > dish_photos.id, not null]
  angle_order smallint [not null, default: 1, note: '1 photo = no 360 turn; 3-4 photos = wider turn (US-3.1/US-3.2)']
}

Table videos {
  id bigint [pk, increment]
  dish_id bigint [ref: > dishes.id, not null]
  origin_id bigint [ref: > video_origins.id, not null]
  generation_id bigint [ref: > video_generations.id, null, note: 'set only when origin = ia']
  status_id bigint [ref: > video_statuses.id, not null]
  mux_asset_id varchar [null]
  mux_playback_id varchar [null]
  cover_path varchar [null]
  duration_seconds smallint [null]
  created_at timestamp
  updated_at timestamp
}

// ===== Generation balance =====

Table generation_balances {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, unique, not null]
  monthly_balance int [not null, default: 0]
  addon_balance int [not null, default: 0]
  renews_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

Table generation_ledger {
  id bigint [pk, increment]
  restaurant_id bigint [ref: > restaurants.id, not null]
  type_id bigint [ref: > generation_ledger_types.id, not null]
  quantity int [not null, note: 'positive for credit (renovacao/compra/estorno), negative for debit (uso)']
  reference varchar [null, note: 'e.g. video_generations.id or a Stripe payment id, free-form for traceability']
  created_at timestamp
}

// ===== Analytics =====

Table dish_views {
  id bigint [pk, increment]
  dish_id bigint [ref: > dishes.id, not null]
  restaurant_id bigint [ref: > restaurants.id, not null]
  session_token varchar [not null]
  seconds_watched int [not null, default: 0]
  viewed_on date [not null, note: 'aggregated per day to keep write volume low, per project description']
  created_at timestamp
}

// ===== Admin audit =====

Table admin_logs {
  id bigint [pk, increment]
  user_id bigint [ref: > users.id, not null, note: 'the super_admin who performed the action']
  action_id bigint [ref: > admin_actions.id, not null]
  target varchar [null, note: 'free-form identifier of the affected record, e.g. "restaurant:42"']
  data json [null]
  created_at timestamp
}
```

## Relationships

- Um **restaurant** tem muitos **users** (dono); um `user` com papel `super_admin` não pertence a nenhum restaurante (`restaurant_id` nulo).
- Um **restaurant** pertence a um **plan** e a um **restaurant_status**.
- Um **restaurant** tem muitas **categories**, muitos **dishes**, um **generation_balances**, muitos **generation_ledger**, muitas **subscriptions** (histórico Cashier) e muitos **dish_views**.
- Uma **category** pertence a um **restaurant** e tem muitos **dishes**.
- Um **dish** pertence a um **restaurant**, uma **category** e um **dish_status**; tem muitos **dish_variants**, muitos **dish_photos**, muitos **videos** e muitos **video_generations**; aponta para no máximo um **video** ativo via `active_video_id`.
- Um **dish** tem muitos **badges** via a pivot **badge_dish** (muitos-para-muitos).
- Um **video_generation** pertence a um **dish**, usa um **ai_preset** e um **ai_provider** (snapshot), tem um **generation_status**, e referencia de 1 a 4 **dish_photos** via **video_generation_photos**.
- Um **video** pertence a um **dish**, tem um **video_origin**, um **video_status** e, quando gerado por IA, um **video_generation** (`generation_id`).
- Um **ai_preset** pertence a um **ai_provider**.
- Um **generation_balances** pertence a um **restaurant** (um-para-um).
- Um **generation_ledger** pertence a um **restaurant** e a um **generation_ledger_type**.
- Um **plan** tem um **metrics_level**; um **coupon** tem um **discount_type**.
- Uma **subscription** (Cashier) pertence a um **restaurant** e tem muitos **subscription_items**.
- Um **dish_view** pertence a um **dish** e a um **restaurant** (desnormalizado para consultas agregadas sem join).
- Um **admin_log** pertence a um **user** (super_admin) e a um **admin_action**.

## Lookup Table Seeds

- **roles**: dono, super_admin
- **restaurant_statuses**: ativo, suspenso
- **dish_statuses**: ativo, esgotado, oculto
- **video_statuses**: processando, aguardando_aprovacao, aprovado, rejeitado
- **video_origins**: ia, upload
- **generation_statuses**: fila, gerando, pronto, erro
- **generation_ledger_types**: renovacao, compra, uso, estorno
- **metrics_levels**: cardapio_total, por_prato, por_prato_com_tempo_assistido
- **discount_types**: percentual, valor_fixo
- **badges**: novo, mais_pedido, vegetariano
- **admin_actions**: restaurante_suspenso, restaurante_reativado, restaurante_impersonado, conteudo_removido, plano_atualizado, cupom_criado
- **ai_providers**: nenhum valor fixo no seed — cadastrados pelo super admin (US-7.4); a tabela existe para tornar o provedor plugável, não para travar uma lista fechada

## Notes & Conventions

- **Sem soft delete:** nenhuma tabela usa `deleted_at`. Ocultar/desativar é sempre feito por uma coluna `status_id` (`dishes.status_id`, `restaurants.status_id`), conforme US-2.3 ("ocultar... sem apagar seus dados") e US-7.2 (suspender/reativar restaurante).
- **Exceção de convenção — Cashier:** `subscriptions` e `subscription_items` seguem o schema padrão publicado pelo pacote Laravel Cashier, incluindo a coluna `stripe_status` como `varchar` livre (valores vêm da própria Stripe: active, trialing, past_due, canceled, unpaid, incomplete...). Isso quebra a regra geral de "todo campo categórico vira lookup table" de propósito: é um pacote de terceiros, não código nosso, e normalizar essa coluna exigiria sobrescrever migrations do Cashier sem ganho real.
- **Billable = restaurant, não user:** o campo Cashier `stripe_id` fica em `restaurants` (não em `users`), porque quem assina é o restaurante, e `subscriptions.restaurant_id` foi renomeado a partir do padrão `user_id` do stub do Cashier para refletir isso.
- **Referência circular `dishes.active_video_id` ↔ `videos.dish_id`:** ambas as colunas são nulináveis, então a ordem de criação é: criar o `dish` sem vídeo ativo → criar o `video` apontando para o `dish` → só então popular `dishes.active_video_id` na aprovação (US-3.3).
- **`video_generation_photos` não é um pivot puro:** tem coluna própria (`angle_order`) para diferenciar 1 foto (sem giro 360°) de 3–4 fotos/ângulos (giro maior), conforme US-3.1/US-3.2 — por isso tem `id` próprio em vez de chave composta.
- **`badge_dish`** é a única pivot pura (muitos-para-muitos sem colunas extras), nomeada em ordem alfabética das tabelas singulares (`badge` + `dish`) pela convenção Eloquent.
- **`dish_views` é desnormalizada** (`restaurant_id` repetido, que já daria pra obter via `dish_id`) para permitir agregações de métricas por restaurante sem join, e para suportar a gravação em lote mencionada na project description.
- **Preços de prato em `decimal(10,2)`** (reais, exibidos ao cliente); preços de plano/pacote em `price_cents int` (formato que a Stripe usa nativamente), evitando conversões de ponto flutuante na integração de cobrança.
- **`ai_providers` é lookup "aberta"**, sem seed fixo: a spec exige um provedor de IA plugável (Tech Stack da project description), então a lista real de provedores é dado, não código.

## Coverage

| Key Concept | Table(s) |
|-------------|----------|
| Restaurante | restaurants |
| Prato | dishes, dish_variants, dish_photos, badges, badge_dish |
| Categoria | categories |
| Feed | — not persisted: renderização do cliente sobre categories/dishes/videos, sem tabela própria |
| Vídeo | videos |
| Geração de vídeo por IA | video_generations, video_generation_photos, ai_presets, ai_providers |
| Preset de IA | ai_presets, ai_providers |
| Saldo de gerações | generation_balances, generation_ledger, generation_ledger_types |
| Plano | plans, video_addon_packages, coupons, discount_types, metrics_levels, subscriptions, subscription_items |
| Painel do restaurante | — not persisted: é uma área de UI (Livewire), não uma entidade de dados |
| Painel administrativo (super admin) | — not persisted como entidade própria: dados de suporte ficam em admin_logs, admin_actions, users, restaurants |
| Visualização | dish_views |
