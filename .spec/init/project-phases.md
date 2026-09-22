# Degustou Cardápio — Project Phases

<!-- inputs: project-description.md@sha256:de789e216b36 user-stories.md@sha256:8ee3dfe566da database-schema.md@sha256:d9424af1fca0 -->

## Overview

O build é organizado foundation-first: **Fases 1–6** montam a base (bootstrap da aplicação Laravel, todas as 33 tabelas do schema com seus models e relações já completas) e **Fases 7–9** montam a base de frontend (shell do painel do dono em Livewire, shell do feed em HTML+JS puro, shell do painel admin em Filament) antes de qualquer fluxo de negócio começar. **Fases 10–20** implementam os fluxos de produto na ordem de dependência real: autenticação → cardápio → geração de vídeo por IA → vídeo próprio → feed do cliente → assinatura/cobrança → aparência/QR/métricas → administração do sistema (dashboard/restaurantes, planos/cupons, operação de IA, moderação/financeiro).

São **20 fases** (Fase 5.x, 10.x etc. quando há sub-fases), referenciáveis por número para agentes de IA. Como o repositório está vazio (sem `composer.json`, sem `artisan`), toda tarefa começa `[ ]`. **Não há fase deferida**: tudo neste documento é o MVP (Fase 2 da project description) — os itens "fora do escopo" (pedido/carrinho, pagamento, app nativo, modelo 3D) não têm story nem tabela, então foram excluídos por completo, não adiados para uma fase futura.

**Conventions:**
- `[ ]` pending · `[x]` done in the codebase.
- Phases and sub-phases are numbered (`Phase 1`, `Phase 5.3`) for reference by AI agents.
- Business-logic tasks list the **feature tests** to generate; frontend-only tasks list validatable **acceptance criteria** and a **Design ref**.
- Design refs apontam para `Docs/Desegner/Shared artifact link/` (os 2 mockups existentes), não para `.spec/init/design/` (vazio) — decisão do desenvolvedor nesta sessão.

---

## Phase 1: Application Bootstrap

**Goal:** stand up the Laravel application and every shared infrastructure piece later phases depend on. · **Depends on:** none · **Covers:** Tech Stack (project-description)

- [ ] **Task:** Install the Laravel application skeleton
  - **Acceptance criteria:**
    - `composer.json` requires a current Laravel LTS release.
    - `.env.example` includes placeholders for DB, queue, mail, Mux, and Stripe credentials.
    - `php artisan serve` boots without error on a fresh clone.
  - **Traces:** Tech Stack — Laravel + Livewire (project-description)

- [ ] **Task:** Install and configure Livewire
  - **Acceptance criteria:**
    - `livewire/livewire` is in `composer.json`.
    - A smoke-test Livewire component renders successfully on a test route.
  - **Traces:** Tech Stack — Laravel + Livewire

- [ ] **Task:** Install and configure Filament for the admin panel
  - **Acceptance criteria:**
    - `filament/filament` installed; a Filament panel is registered at `/admin`.
    - The default Filament login screen resolves at `/admin/login`.
  - **Traces:** Tech Stack — Filament (painel administrativo); US-8.2; users, roles

- [ ] **Task:** Install and configure Laravel Cashier (Stripe billing)
  - **Acceptance criteria:**
    - `laravel/cashier` installed; `STRIPE_KEY`/`STRIPE_SECRET` env vars wired.
    - Cashier's stub migrations are published (customized and finalized in Phase 5, not duplicated here).
  - **Traces:** Tech Stack — Pagamentos/assinaturas (Stripe via Laravel Cashier); subscriptions, subscription_items

- [ ] **Task:** Configure Laravel Queues
  - **Acceptance criteria:**
    - A queue connection (database or redis) is configured; `jobs`/`failed_jobs` tables are migrated.
    - A smoke-test job runs successfully under `php artisan queue:work` in a test.
  - **Traces:** Tech Stack — Fila de jobs (Laravel Queues); workflow "Dono cadastra um prato e gera vídeo por IA"

- [ ] **Task:** Build the Mux client wrapper
  - **Acceptance criteria:**
    - An app service wraps Mux's asset-creation, direct-upload, and webhook-signature-verification calls behind a single interface.
    - Credentials (`MUX_TOKEN_ID`, `MUX_TOKEN_SECRET`, `MUX_WEBHOOK_SECRET`) are read from env, never hardcoded.
  - **Feature tests:** `MuxClientTest::it_creates_an_asset_with_the_given_input_url`, `MuxClientTest::it_verifies_a_valid_webhook_signature_and_rejects_an_invalid_one`
  - **Traces:** Tech Stack — Hospedagem/streaming de vídeo (Mux)

- [ ] **Task:** Build the pluggable AI video-provider interface
  - **Acceptance criteria:**
    - A `VideoGenerationProvider` interface defines a `generate(photos, preset): variations[]` contract.
    - A config-driven resolver (`config/ai.php`) binds the concrete provider implementation by slug, so swapping providers is a config change, not a code change.
    - A fake/null provider implementation exists for use in tests.
  - **Feature tests:** `AiProviderResolverTest::it_resolves_the_configured_provider_by_slug`, `AiProviderResolverTest::it_throws_a_clear_error_when_the_configured_slug_has_no_bound_implementation`
  - **Traces:** Tech Stack — Geração de vídeo por IA (provedor genérico/plugável); ai_providers, ai_presets

- [ ] **Task:** Configure the testing foundation
  - **Acceptance criteria:**
    - Pest is installed and configured as the test runner.
    - A dedicated testing database connection is isolated from dev data; `RefreshDatabase` (or equivalent) is wired into the base `TestCase`.
    - `php artisan test` runs a trivial passing test.
  - **Traces:** Tech Stack (testing convention underlying every Feature tests block in this document)

---

## Phase 2: Database — Lookup Tables & Seeders

**Goal:** every categorical field in the schema gets its lookup table, model, and seeded values. · **Depends on:** Phase 1 · **Covers:** database-schema.md lookup tables

- [ ] **Task:** `roles` migration, model and seeder
  - **Acceptance criteria:**
    - Columns: `id`, `name`, `slug` (unique), timestamps.
    - Seeder idempotently creates exactly `dono` and `super_admin`.
  - **Traces:** roles; US-8.2

- [ ] **Task:** `restaurant_statuses` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `ativo` and `suspenso`.
  - **Traces:** restaurant_statuses; US-7.2

- [ ] **Task:** `dish_statuses` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `ativo`, `esgotado`, `oculto`.
  - **Traces:** dish_statuses; US-2.3

- [ ] **Task:** `video_statuses` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `processando`, `aguardando_aprovacao`, `aprovado`, `rejeitado`.
  - **Traces:** video_statuses; US-3.3

- [ ] **Task:** `video_origins` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `ia`, `upload`.
  - **Traces:** video_origins; US-3.1, US-4.1

- [ ] **Task:** `generation_statuses` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `fila`, `gerando`, `pronto`, `erro`.
  - **Traces:** generation_statuses; US-3.4

- [ ] **Task:** `generation_ledger_types` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `renovacao`, `compra`, `uso`, `estorno`.
  - **Traces:** generation_ledger_types; US-5.3

- [ ] **Task:** `metrics_levels` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `cardapio_total`, `por_prato`, `por_prato_com_tempo_assistido`.
  - **Traces:** metrics_levels; US-6.3

- [ ] **Task:** `discount_types` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `percentual`, `valor_fixo`.
  - **Traces:** discount_types; US-7.3

- [ ] **Task:** `badges` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `novo`, `mais_pedido`, `vegetariano`.
  - **Traces:** badges; US-1.4

- [ ] **Task:** `admin_actions` migration, model and seeder
  - **Acceptance criteria:** seeder idempotently creates exactly `restaurante_suspenso`, `restaurante_reativado`, `restaurante_impersonado`, `conteudo_removido`, `plano_atualizado`, `cupom_criado`.
  - **Traces:** admin_actions; US-7.2, US-7.5

- [ ] **Task:** `ai_providers` migration and model (no fixed seed)
  - **Acceptance criteria:**
    - Table created empty by design — the roster is data cadastrada pelo super admin, not a fixed vocabulary.
    - A factory exists so tests can create fake providers.
  - **Traces:** ai_providers; US-7.4

- [ ] **Task:** Lookup-seed integrity test
  - **Acceptance criteria:** none beyond the feature test below (pure verification task).
  - **Feature tests:** `LookupSeedersTest::each_lookup_table_seeds_exactly_the_documented_slugs`, `LookupSeedersTest::re_running_every_seeder_does_not_create_duplicate_rows`
  - **Traces:** roles, restaurant_statuses, dish_statuses, video_statuses, video_origins, generation_statuses, generation_ledger_types, metrics_levels, discount_types, badges, admin_actions

---

## Phase 3: Database — Auth & Tenant Foundation

**Goal:** the accounts and the tenant they belong to. · **Depends on:** Phase 2 · **Covers:** users, password_reset_tokens, restaurants

- [ ] **Task:** Extend the default `users` migration and model
  - **Acceptance criteria:**
    - Adds `restaurant_id` (nullable FK → restaurants, null only for `super_admin`) and `role_id` (FK → roles, not null).
    - Model relationships: `belongsTo(Restaurant)`, `belongsTo(Role)`; helpers `isSuperAdmin()` / `isOwner()`.
  - **Traces:** users; US-8.1, US-8.2

- [ ] **Task:** Confirm the default `password_reset_tokens` migration
  - **Acceptance criteria:** the framework-provided stub migration is present and unmodified; it backs the reset flow built in Phase 10.3.
  - **Traces:** password_reset_tokens; US-8.3

- [ ] **Task:** `restaurants` migration and model
  - **Acceptance criteria:**
    - Columns per schema: `plan_id` (FK), `status_id` (FK), `name`, `slug` (unique), `logo_path` (nullable), `accent_color` (nullable), `font` (nullable), `stripe_id` (nullable, unique).
    - Relationships: `belongsTo(Plan)`, `belongsTo(RestaurantStatus)`, `hasMany(User)`, `hasMany(Category)`, `hasMany(Dish)`, `hasOne(GenerationBalance)`, `hasMany(GenerationLedger)`, `hasMany(DishView)`.
  - **Traces:** restaurants; US-5.1, US-7.2

- [ ] **Task:** Wire the `Restaurant` ↔ Cashier billable relationship
  - **Acceptance criteria:**
    - `Restaurant` uses Cashier's `Billable` trait; `restaurants.stripe_id` is the billable id column.
  - **Feature tests:** `RestaurantBillableTest::it_can_be_created_as_a_stripe_customer_against_a_faked_stripe_client`
  - **Traces:** restaurants; subscriptions; US-5.2

- [ ] **Task:** Factories for `User` and `Restaurant`
  - **Acceptance criteria:** factories produce valid rows including role/status/plan associations, reused by every downstream feature test in this document.
  - **Traces:** users, restaurants

---

## Phase 4: Database — Menu & Media Foundation

**Goal:** the cardápio data model. · **Depends on:** Phase 3 · **Covers:** categories, dishes, dish_variants, dish_photos, badge_dish

- [ ] **Task:** `categories` migration and model
  - **Acceptance criteria:**
    - `restaurant_id` (FK), `name`, `display_order` (default 0), `is_visible` (default true).
    - Relationships: `belongsTo(Restaurant)`, `hasMany(Dish)`; a `visible()` query scope.
  - **Traces:** categories; US-2.1

- [ ] **Task:** `dishes` migration and model (without the `active_video_id` FK — added in Phase 6 to resolve the circular reference with `videos`)
  - **Acceptance criteria:**
    - `restaurant_id` (FK), `category_id` (FK), `status_id` (FK), `name`, `price` decimal(10,2), `short_description` (nullable), `description` (nullable), `display_order`.
    - Relationships: `belongsTo(Restaurant/Category/DishStatus)`, `hasMany(DishVariant/DishPhoto/Video/VideoGeneration)`, `belongsToMany(Badge)` through `badge_dish`.
  - **Traces:** dishes; US-2.2, US-2.3

- [ ] **Task:** `dish_variants` migration and model
  - **Acceptance criteria:** `dish_id` (FK), `name`, `price` decimal(10,2); `belongsTo(Dish)`.
  - **Traces:** dish_variants; US-2.2

- [ ] **Task:** `dish_photos` migration and model
  - **Acceptance criteria:** `dish_id` (FK), `file_path`, `display_order`; `belongsTo(Dish)`; `hasMany(VideoGenerationPhoto)` for Phase 6's usage.
  - **Traces:** dish_photos; US-2.2, US-3.1

- [ ] **Task:** `badge_dish` pivot migration and model wiring
  - **Acceptance criteria:**
    - Composite primary key `(badge_id, dish_id)`, both FKs.
    - `Dish::badges()` and `Badge::dishes()` belongsToMany each other.
  - **Traces:** badge_dish, badges; US-1.4

- [ ] **Task:** Factories for `Category`, `Dish`, `DishVariant`, `DishPhoto`
  - **Acceptance criteria:** factories build a coherent category → dish → variants/photos tree for use across Phase 11–14 feature tests.
  - **Traces:** categories, dishes, dish_variants, dish_photos

---

## Phase 5: Database — Billing & Generation Balance Foundation

**Goal:** everything money and generation-credit related. · **Depends on:** Phase 3, Phase 1 (Cashier) · **Covers:** plans, video_addon_packages, coupons, subscriptions, subscription_items, generation_balances, generation_ledger

- [ ] **Task:** `plans` migration and model
  - **Acceptance criteria:**
    - `name`, `price_cents` (default 0), `stripe_price_id` (nullable, unique), `monthly_generations` (default 0), `initial_generations` (default 0), `dish_limit` (nullable = unlimited), `removes_branding` (default false), `metrics_level_id` (FK), `is_active` (default true).
    - `belongsTo(MetricsLevel)`, `hasMany(Restaurant)`.
  - **Traces:** plans; US-5.1, US-5.2, US-7.3

- [ ] **Task:** Seed the baseline Grátis/Básico/Pro plans
  - **Acceptance criteria:**
    - Seeder creates exactly 3 plans; Grátis has `initial_generations = 5`, `monthly_generations = 0`, `dish_limit = 15`, `removes_branding = false`.
    - Básico/Pro get placeholder price/limits from the project description's reference table, editable later through Phase 18's admin CRUD (US-7.3) — not hardcoded business rules, just seed defaults.
    - Seeder is idempotent (safe to re-run).
  - **Feature tests:** `PlanSeederTest::it_seeds_a_free_plan_with_five_one_time_initial_generations_and_no_monthly_renewal`
  - **Traces:** plans; US-5.1

- [ ] **Task:** `video_addon_packages` migration and model
  - **Acceptance criteria:** `name`, `generations_count`, `price_cents`, `stripe_price_id` (nullable, unique), `is_active` (default true).
  - **Traces:** video_addon_packages; US-5.3, US-7.3

- [ ] **Task:** `coupons` migration and model
  - **Acceptance criteria:** `code` (unique), `stripe_coupon_id` (nullable, unique), `discount_type_id` (FK), `discount_value` decimal(10,2), `is_active` (default true), `expires_at` (nullable); `belongsTo(DiscountType)`.
  - **Traces:** coupons, discount_types; US-7.3

- [ ] **Task:** Customize Cashier's `subscriptions`/`subscription_items` migrations for the `Restaurant` billable
  - **Acceptance criteria:**
    - `subscriptions.restaurant_id` (renamed from Cashier's default `user_id` stub) FK → restaurants.
    - `subscription_items` unchanged from Cashier's stub, FK → subscriptions.
    - Models extend Cashier's base `Subscription`/`SubscriptionItem` classes.
  - **Traces:** subscriptions, subscription_items; US-5.2, US-5.4

- [ ] **Task:** `generation_balances` migration and model
  - **Acceptance criteria:**
    - `restaurant_id` (FK, unique — one-to-one), `monthly_balance` (default 0), `addon_balance` (default 0), `renews_at` (nullable).
    - `belongsTo(Restaurant)`; helper `hasEnoughBalance(int $needed): bool` combining both balances.
  - **Feature tests:** `GenerationBalanceTest::it_reports_enough_balance_only_when_monthly_plus_addon_covers_the_request`
  - **Traces:** generation_balances; US-3.4

- [ ] **Task:** `generation_ledger` migration and model
  - **Acceptance criteria:**
    - `restaurant_id` (FK), `type_id` (FK → generation_ledger_types), `quantity` (signed int), `reference` (nullable), `created_at` only (append-only ledger, no `updated_at`).
    - `belongsTo(Restaurant)`, `belongsTo(GenerationLedgerType)`.
  - **Traces:** generation_ledger, generation_ledger_types; US-5.3, US-3.4

---

## Phase 6: Database — AI Generation, Analytics & Audit Foundation

**Goal:** everything the video pipeline, view tracking, and admin audit trail need. · **Depends on:** Phase 4, Phase 2 · **Covers:** ai_presets, videos, video_generations, video_generation_photos, dish_views, admin_logs

- [ ] **Task:** `ai_presets` migration and model
  - **Acceptance criteria:**
    - `provider_id` (FK → ai_providers), `name`, `prompt` (text), `camera_movement`, `duration_seconds`, `is_active` (default true).
    - `belongsTo(AiProvider)`, `hasMany(VideoGeneration)`.
  - **Traces:** ai_presets, ai_providers; US-3.1, US-7.4

- [ ] **Task:** `videos` migration and model
  - **Acceptance criteria:**
    - `dish_id` (FK), `origin_id` (FK → video_origins), `generation_id` (nullable FK → video_generations, set only when origin = ia), `status_id` (FK → video_statuses), `mux_asset_id`/`mux_playback_id` (nullable), `cover_path` (nullable), `duration_seconds` (nullable).
    - `belongsTo(Dish/VideoOrigin/VideoStatus/VideoGeneration)`.
  - **Traces:** videos, video_origins, video_statuses; US-3.3, US-4.1

- [ ] **Task:** Follow-up migration wiring `dishes.active_video_id` → `videos.id`
  - **Acceptance criteria:**
    - Adds the nullable FK column onto the already-created `dishes` table, resolving the circular dependency noted in database-schema.md by creating `dishes` first, then `videos`, then this constraint.
    - `Dish::activeVideo()` `belongsTo(Video)`.
  - **Feature tests:** `DishActiveVideoTest::a_dish_has_at_most_one_active_video_at_a_time`
  - **Traces:** dishes, videos; US-3.3

- [ ] **Task:** `video_generations` migration and model
  - **Acceptance criteria:**
    - `dish_id` (FK), `preset_id` (FK), `provider_id` (FK, snapshot of the provider actually used), `status_id` (FK → generation_statuses), `variations_requested` (default 2), `cost_usd` (nullable decimal(10,4)).
    - `belongsTo(Dish/AiPreset/AiProvider/GenerationStatus)`, `hasMany(Video)`, `hasMany(VideoGenerationPhoto)`.
  - **Traces:** video_generations, generation_statuses; US-3.1, US-3.2

- [ ] **Task:** `video_generation_photos` migration and model
  - **Acceptance criteria:** `video_generation_id` (FK), `dish_photo_id` (FK), `angle_order` (default 1); `belongsTo(VideoGeneration/DishPhoto)`.
  - **Traces:** video_generation_photos; US-3.1, US-3.2

- [ ] **Task:** `dish_views` migration and model
  - **Acceptance criteria:** `dish_id` (FK), `restaurant_id` (FK, denormalized), `session_token`, `seconds_watched` (default 0), `viewed_on` (date), `created_at` only; `belongsTo(Dish/Restaurant)`.
  - **Traces:** dish_views; US-1.4, US-6.3

- [ ] **Task:** `admin_logs` migration and model
  - **Acceptance criteria:** `user_id` (FK → users, the acting super_admin), `action_id` (FK → admin_actions), `target` (nullable), `data` (nullable json), `created_at` only; `belongsTo(User)`, `belongsTo(AdminAction)`.
  - **Traces:** admin_logs, admin_actions; US-7.2, US-7.5

- [ ] **Task:** Factories for `AiPreset`, `AiProvider`, `VideoGeneration`, `Video`, `DishView`, `AdminLog`
  - **Acceptance criteria:** factories cover every status/origin permutation needed by Phase 12–20 feature tests.
  - **Traces:** ai_presets, video_generations, videos, dish_views, admin_logs

---

## Phase 7: Frontend Foundation — Dono Panel Shell (Livewire)

**Goal:** the shared visual/component base the dono panel's feature screens build on. · **Depends on:** Phase 1 · **Covers:** Painel do restaurante (project-description Overview)

- [ ] **Task:** Base authenticated layout for the dono panel
  - **Acceptance criteria:**
    - Mobile-first responsive shell (nav, header, content slot) matching the mockup's general structure and branding.
    - Renders only for an authenticated `dono` user.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-8.2

- [ ] **Task:** Navigation matching the mockup's information architecture
  - **Acceptance criteria:**
    - Exposes Início, Categorias, Pratos, Aparência, QR Code, Visualizações, Assinatura entries.
    - Active section is visibly highlighted.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** painel do restaurante (project-description Overview)

- [ ] **Task:** Shared Livewire UI components (button, card, form field, modal, toast)
  - **Acceptance criteria:**
    - Visual language (spacing, typography, use of the restaurant's `accent_color`) matches the mockup.
    - A component-preview dev route documents each one.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** painel do restaurante (project-description Overview)

- [ ] **Task:** Empty/loading/error state components
  - **Acceptance criteria:** a consistent empty-state and skeleton-loading component set, reused by every feature screen built in Phases 10–20.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** painel do restaurante (project-description Overview)

---

## Phase 8: Frontend Foundation — Client Feed Shell (HTML+JS)

**Goal:** the non-Livewire, high-performance feed scaffold. · **Depends on:** Phase 1 · **Covers:** workflow "Cliente visualiza o cardápio pelo QR Code"

- [ ] **Task:** Server-rendered feed skeleton (no Livewire)
  - **Acceptance criteria:**
    - Plain Blade/HTML response — no Livewire wrapper on this route.
    - Layout matches the mockup's vertical feed structure.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.1

- [ ] **Task:** Scroll-snap + IntersectionObserver navigation script
  - **Acceptance criteria:**
    - Vanilla JS (no framework) implements CSS scroll-snap paging between dishes.
    - IntersectionObserver detects the active dish and plays its video while pausing all others.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.3

- [ ] **Task:** Fixed category bar component
  - **Acceptance criteria:** horizontally scrollable category bar pinned to the top, matching mockup styling; tapping a category is wired to real data in Phase 14.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.3

- [ ] **Task:** Video player chrome (autoplay/mute/loop/sound toggle)
  - **Acceptance criteria:**
    - HTML5 `<video>` with `autoplay muted loop playsinline`.
    - A sound toggle unmutes/remutes; overlay shows name/price/description per the mockup.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.2

- [ ] **Task:** Bottom detail sheet component
  - **Acceptance criteria:** slides up over the video to show the full dish description, matching the mockup's interaction; dismissible by swipe/tap-outside.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.4

- [ ] **Task:** `preconnect` + MP4 faststart performance scaffolding
  - **Acceptance criteria:**
    - `<link rel="preconnect">` to the Mux CDN origin is present in the feed layout `<head>`.
    - Video URLs point at MP4 (faststart) renditions per the Tech Stack decision.
  - **Traces:** US-1.1; Tech Stack — Vídeo no feed (MP4)

- [ ] **Task:** Service worker for cover/first-video caching
  - **Acceptance criteria:** a service worker registered on the feed page caches dish covers and the first video for a repeat visitor, per the performance goals in the project description.
  - **Traces:** US-1.1

---

## Phase 9: Frontend Foundation — Admin Panel Shell (Filament)

**Goal:** a role-restricted Filament panel for the super admin. · **Depends on:** Phase 1, Phase 3 · **Covers:** painel administrativo (project-description Overview)

- [ ] **Task:** Register the Filament admin panel restricted to `super_admin`
  - **Acceptance criteria:** `canAccessPanel()` returns true only when the authenticated user's role slug is `super_admin`; a `dono` user attempting `/admin` is denied.
  - **Feature tests:** `AdminPanelAccessTest::a_dono_user_cannot_access_the_admin_panel`, `AdminPanelAccessTest::a_super_admin_user_can_access_the_admin_panel`
  - **Design ref:** none — Filament's default panel conventions apply; no custom mockup exists for the admin panel.
  - **Traces:** US-8.2; users, roles

- [ ] **Task:** Base Filament branding (title, color scheme)
  - **Acceptance criteria:** panel title and color scheme are configured; no functional requirement beyond visual identity.
  - **Design ref:** none — Filament defaults.
  - **Traces:** painel administrativo (project-description Overview)

---

## Phase 10: Authentication & Account

**Goal:** get owners and the super admin in and out of their respective panels securely. · **Depends on:** Phase 3, Phase 5 (Grátis plan seeded), Phase 7, Phase 9 · **Covers:** US-8.1–US-8.4, US-5.1

### Phase 10.1: Signup

- [ ] **Task:** Signup form (Livewire)
  - **Acceptance criteria:**
    - Collects name/e-mail/password; e-mail uniqueness enforced; password requires 8+ characters, enforced client- and server-side.
  - **Design ref:** none — no signup screen in the provided mockups; built with the Phase 7 shared component library.
  - **Traces:** US-8.1

- [ ] **Task:** Signup business logic (create restaurant + Free plan + initial balance)
  - **Acceptance criteria:**
    - On success, creates a `Restaurant` (status = ativo, plan = the seeded Grátis plan), a `User` (role = dono, linked to the new restaurant), and a `generation_balances` row (`addon_balance` seeded from the plan's `initial_generations`, `monthly_balance = 0`), all inside a single DB transaction.
  - **Feature tests:** `SignupTest::signing_up_creates_a_restaurant_on_the_free_plan_with_five_one_time_generations`, `SignupTest::signup_fails_with_a_duplicate_email`
  - **Traces:** US-8.1, US-5.1; restaurants, users, plans, generation_balances

### Phase 10.2: Login & Role-Based Routing

- [ ] **Task:** Login form and authentication
  - **Acceptance criteria:**
    - Invalid credentials show one generic error message that never reveals whether the e-mail exists.
    - A `dono` is redirected to the restaurant panel; a `super_admin` is redirected to `/admin`.
  - **Feature tests:** `LoginTest::a_dono_is_redirected_to_the_restaurant_panel_after_login`, `LoginTest::a_super_admin_is_redirected_to_the_admin_panel_after_login`, `LoginTest::invalid_credentials_show_a_generic_error_without_revealing_account_existence`
  - **Design ref:** none — no login screen in the provided mockups; built with the Phase 7 shared component library.
  - **Traces:** US-8.2

### Phase 10.3: Password Reset & Logout

- [ ] **Task:** "Esqueci minha senha" request flow
  - **Acceptance criteria:**
    - Shows the same confirmation message whether or not the e-mail exists.
    - Sends a reset e-mail with a link valid for 60 minutes when the account exists.
  - **Feature tests:** `PasswordResetTest::a_reset_link_is_emailed_for_an_existing_account`, `PasswordResetTest::requesting_a_reset_for_an_unknown_email_shows_the_same_confirmation_message`
  - **Traces:** US-8.3

- [ ] **Task:** Password reset confirmation
  - **Acceptance criteria:**
    - A valid, unexpired token sets the new password and invalidates all of that user's existing sessions.
    - An expired or already-used token is rejected with a clear error.
  - **Feature tests:** `PasswordResetTest::a_valid_token_sets_the_new_password_and_revokes_existing_sessions`, `PasswordResetTest::an_expired_or_reused_token_is_rejected`
  - **Traces:** US-8.3

- [ ] **Task:** Logout (dono and super admin)
  - **Acceptance criteria:** a visible logout action in both panels invalidates the session; a protected route accessed afterward redirects to login.
  - **Feature tests:** `LogoutTest::logging_out_invalidates_the_session_and_protected_routes_redirect_to_login`
  - **Traces:** US-8.4

---

## Phase 11: Cardápio Management — Categories & Dishes

**Goal:** the dono's core content-authoring loop. · **Depends on:** Phase 4, Phase 7, Phase 10 · **Covers:** US-2.1, US-2.2, US-2.3

### Phase 11.1: Categories

- [ ] **Task:** Category CRUD screen
  - **Acceptance criteria:**
    - Create/rename/hide a category; drag-to-reorder persists `display_order`.
    - A hidden category and its dishes are excluded from the restaurant-scoped visible listing consumed later by the feed.
  - **Feature tests:** `CategoryManagementTest::reordering_categories_persists_the_new_display_order`, `CategoryManagementTest::a_hidden_category_is_excluded_from_the_visible_scope`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-2.1; categories

### Phase 11.2: Dishes

- [ ] **Task:** Dish create/edit form
  - **Acceptance criteria:**
    - Requires name/price/category; optional short/full description, badges (multi-select), and repeatable variant (name+price) rows.
    - A newly created dish has no `active_video_id` until one is approved (Phase 12.2).
  - **Feature tests:** `DishManagementTest::creating_a_dish_requires_name_price_and_category`, `DishManagementTest::a_newly_created_dish_has_no_active_video_until_one_is_approved`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-2.2; dishes, dish_variants

- [ ] **Task:** Dish photo upload
  - **Acceptance criteria:** uploads one or more ordered photos to `dish_photos`, used both as the dish's own gallery and as the source images later offered when starting an AI generation (Phase 12).
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-2.2; dish_photos

- [ ] **Task:** Price/status quick-edit (esgotado, ocultar, preço)
  - **Acceptance criteria:**
    - Price/status changes take effect without re-approving the dish's video.
    - "Esgotado" keeps the dish listed but flagged unavailable; "oculto" removes it from the visible scope entirely.
    - A change reflects on the next feed request, no manual cache clear.
  - **Feature tests:** `DishAvailabilityTest::marking_a_dish_out_of_stock_keeps_it_listed_but_flagged_unavailable`, `DishAvailabilityTest::hiding_a_dish_removes_it_from_the_visible_scope`, `DishAvailabilityTest::updating_price_does_not_require_revoking_video_approval`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-2.3; dish_statuses

---

## Phase 12: AI Video Generation

**Goal:** the photo-to-video pipeline with mandatory owner approval. · **Depends on:** Phase 1, Phase 5, Phase 6, Phase 11 · **Covers:** US-3.1–US-3.5

### Phase 12.1: Request a Generation

- [ ] **Task:** Photo-to-video request screen
  - **Acceptance criteria:**
    - Accepts 1 to 4 photos (from `dish_photos` or a fresh upload), tagged with `angle_order`.
    - With exactly 1 photo, the preset applies no full 360° turn; with 3–4 photos it may apply a wider turn.
    - Requests 2–3 variations.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-3.1, US-3.2; video_generation_photos

- [ ] **Task:** Balance check before dispatch
  - **Acceptance criteria:**
    - Shows the balance to be consumed (1 per requested variation) before confirming.
    - Blocks dispatch and offers upgrade/addon links when the balance can't cover it; never creates a `video_generations` row when blocked.
  - **Feature tests:** `GenerationBalanceGateTest::a_request_is_blocked_when_the_balance_cannot_cover_the_requested_variations`, `GenerationBalanceGateTest::the_panel_shows_the_exact_balance_that_will_be_consumed_before_confirming`
  - **Traces:** US-3.4; generation_balances

- [ ] **Task:** Dispatch the generation job
  - **Acceptance criteria:**
    - Creates a `video_generations` row (status = fila) and a queued job calling the bound `VideoGenerationProvider`.
    - Uploads each resulting MP4 to Mux and creates one `videos` row per variation (status = processando), linked via `generation_id`.
    - A provider error marks the generation `erro` and does not touch the balance.
  - **Feature tests:** `GenerationDispatchTest::a_successful_generation_creates_one_video_row_per_variation`, `GenerationDispatchTest::a_provider_error_marks_the_generation_as_erro_and_does_not_debit_the_balance`
  - **Traces:** US-3.1, US-3.4; video_generations, videos, generation_ledger

### Phase 12.2: Review, Approve & Regenerate

- [ ] **Task:** Mux "ready" webhook handler
  - **Acceptance criteria:**
    - Verifies the Mux webhook signature via Phase 1's Mux client.
    - On the asset-ready event, sets the matching `videos` row to `aguardando_aprovacao`, stores `mux_playback_id`/`cover_path`/`duration_seconds`, and notifies the dono.
  - **Feature tests:** `MuxWebhookTest::a_valid_ready_webhook_moves_the_video_to_aguardando_aprovacao`, `MuxWebhookTest::an_invalid_signature_is_rejected_without_changing_any_video`
  - **Traces:** US-3.3; videos, video_statuses

- [ ] **Task:** Variation review and approval screen
  - **Acceptance criteria:**
    - Lists every `aguardando_aprovacao` video for a dish's latest generation.
    - Approving one sets it `aprovado` and sets `dishes.active_video_id` to it, demoting any previously active video for that dish.
  - **Feature tests:** `VideoApprovalTest::approving_a_variation_sets_it_as_the_dishs_only_active_video`, `VideoApprovalTest::a_dish_never_ends_up_with_two_active_videos`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-3.3; dishes, videos

- [ ] **Task:** Debit balance on successful generation
  - **Acceptance criteria:**
    - On completion (not on request), records one `uso` entry per variation actually generated in `generation_ledger`, deducting `monthly_balance` before `addon_balance`.
    - A failed provider call leaves the ledger untouched.
  - **Feature tests:** `GenerationLedgerTest::a_successful_generation_debits_monthly_balance_before_addon_balance`, `GenerationLedgerTest::a_failed_generation_leaves_the_ledger_untouched`
  - **Traces:** US-3.4; generation_ledger, generation_balances

- [ ] **Task:** Regenerate action
  - **Acceptance criteria:**
    - "Gerar de novo" starts a new balance-gated generation request for the same dish (reuses Phase 12.1's flow).
    - Previous non-approved variations remain visible, labelled as discarded rather than silently disappearing.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-3.5; video_generations

---

## Phase 13: Own Video Upload

**Goal:** the no-IA, no-cost upload path. · **Depends on:** Phase 1, Phase 11, Phase 12.2 (shared approval flow) · **Covers:** US-4.1, US-4.2

- [ ] **Task:** Direct-to-Mux upload flow
  - **Acceptance criteria:**
    - The browser uploads straight to a Mux direct-upload URL; no file passes through the app server.
    - Creates a `videos` row with `origin_id` = upload, `status` = processando, no `generation_id`, and never touches `generation_balances`/`generation_ledger`.
  - **Feature tests:** `OwnVideoUploadTest::an_uploaded_video_never_debits_the_generation_balance`, `OwnVideoUploadTest::an_uploaded_video_row_has_no_generation_id`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-4.1; videos, video_origins

- [ ] **Task:** Upload validation (duration/format)
  - **Acceptance criteria:**
    - Rejects or trims videos outside 5–15s or non-vertical aspect ratio at upload time, with a clear on-screen reason.
    - The dono can immediately re-upload without losing the dish's already-entered data.
  - **Feature tests:** `OwnVideoUploadTest::a_video_shorter_than_5s_or_longer_than_15s_is_rejected_with_a_reason`, `OwnVideoUploadTest::a_non_vertical_video_is_flagged_before_publishing`
  - **Traces:** US-4.2

- [ ] **Task:** Reuse the approval flow for uploaded videos
  - **Acceptance criteria:** an uploaded video reaching `aguardando_aprovacao` (via Phase 12.2's Mux webhook) appears in the same review/approve screen and follows the same one-active-video-per-dish rule.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-4.1; videos, dishes

---

## Phase 14: Client Feed

**Goal:** the actual public, no-login experience the QR Code leads to. · **Depends on:** Phase 8, Phase 4, Phase 6, Phase 11, Phase 12, Phase 13 · **Covers:** US-1.1–US-1.4

### Phase 14.1: Feed Data & First Paint

- [ ] **Task:** Public feed route (no auth)
  - **Acceptance criteria:**
    - `GET /r/{restaurant:slug}` renders the Phase 8 shell server-side with the first visible dish's cover and video URL embedded directly in the HTML — no login, no app install.
  - **Feature tests:** `PublicFeedTest::the_feed_route_requires_no_authentication`, `PublicFeedTest::the_initial_response_embeds_the_first_dishs_cover_and_video_url`
  - **Traces:** US-1.1; restaurants, dishes, videos

- [ ] **Task:** Only approved active videos are feed-eligible
  - **Acceptance criteria:** the feed query only includes dishes with a non-null `active_video_id` whose video is `aprovado`, in a visible category, ordered by category then `display_order`.
  - **Feature tests:** `PublicFeedTest::a_dish_without_an_approved_active_video_never_appears_in_the_feed`, `PublicFeedTest::a_dish_in_a_hidden_category_never_appears_in_the_feed`
  - **Traces:** US-1.1, US-2.1, US-2.3; dishes, categories, videos

### Phase 14.2: Navigation & Playback

- [ ] **Task:** Category-switch data endpoint
  - **Acceptance criteria:** a lightweight endpoint returns the dish set for a tapped category, used by Phase 8's category bar script to swap videos and reset to that category's first dish.
  - **Feature tests:** `FeedCategorySwitchTest::switching_category_returns_only_that_categorys_visible_dishes_starting_at_the_first_one`
  - **Traces:** US-1.3; categories

- [ ] **Task:** Wire real playback data into the video player chrome
  - **Acceptance criteria:** connects Phase 8's player component to each dish's `mux_playback_id`/`cover_path`; overlay shows the real name/price/short description.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.2; dishes, videos

### Phase 14.3: Detail, Selos, Status & Tracking

- [ ] **Task:** Wire the detail sheet to real dish data
  - **Acceptance criteria:** shows full `description`, `badges`, and an "esgotado" indicator sourced from `dish_statuses`.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Cardápio Fumaça - Feed.dc.html`
  - **Traces:** US-1.4; dishes, dish_statuses, badges

- [ ] **Task:** Batched view/watch-time tracking
  - **Acceptance criteria:** the client reports seconds-watched per dish per session; the server writes/updates one `dish_views` row per `(dish_id, session_token, viewed_on)` per day instead of one row per second.
  - **Feature tests:** `DishViewTrackingTest::watch_time_for_the_same_session_and_day_accumulates_into_one_row_instead_of_many`
  - **Traces:** US-1.4, US-6.3; dish_views

---

## Phase 15: Subscription & Billing

**Goal:** upgrades, addon purchases, the customer portal, renewal and delinquency handling. · **Depends on:** Phase 1, Phase 5, Phase 10 · **Covers:** US-5.2, US-5.3, US-5.4, US-5.5

### Phase 15.1: Upgrade & Checkout

- [ ] **Task:** Plan upgrade screen
  - **Acceptance criteria:** lists Básico/Pro with their current price/limits from `plans`; "assinar" opens a Stripe Checkout session in subscription mode, BRL, card-only (no Pix/boleto payment method enabled).
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-5.2; plans

- [ ] **Task:** Checkout-success webhook handling
  - **Acceptance criteria:**
    - On Stripe's subscription-created/updated webhook (verified signature), updates `restaurants.plan_id` and mirrors Cashier's state into the local `subscriptions`/`subscription_items` rows.
    - Dish/generation limits switch to the new plan immediately.
  - **Feature tests:** `SubscriptionWebhookTest::a_confirmed_upgrade_updates_the_restaurants_plan_immediately`, `SubscriptionWebhookTest::limits_from_the_previous_plan_no_longer_apply_after_upgrade`
  - **Traces:** US-5.2; subscriptions, plans, restaurants

### Phase 15.2: Addon Packages & Customer Portal

- [ ] **Task:** Addon package purchase
  - **Acceptance criteria:**
    - Lists active `video_addon_packages`; "comprar" opens a Stripe Checkout session in one-time-payment mode.
    - On payment-confirmed webhook, credits `generation_balances.addon_balance` by the package's `generations_count` and records a `compra` entry in `generation_ledger`.
  - **Feature tests:** `AddonPurchaseTest::a_confirmed_addon_purchase_credits_addon_balance_and_logs_a_compra_entry`, `AddonPurchaseTest::addon_balance_never_expires_and_is_spent_only_after_monthly_balance_is_exhausted`
  - **Traces:** US-5.3; video_addon_packages, generation_balances, generation_ledger

- [ ] **Task:** Customer Portal link and plan/usage summary
  - **Acceptance criteria:** "Assinatura" screen shows current plan and month-to-date generation usage in-panel, plus a button opening Stripe's Customer Portal for card/invoice/cancellation management.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-5.4; plans, generation_balances

### Phase 15.3: Renewal & Delinquency

- [ ] **Task:** Monthly balance renewal on invoice-paid webhook
  - **Acceptance criteria:** on Stripe's invoice-paid webhook for a recurring subscription, `monthly_balance` resets (does not accumulate) to the current plan's `monthly_generations`, and `renews_at` advances.
  - **Feature tests:** `BalanceRenewalTest::invoice_paid_resets_monthly_balance_to_the_plans_allowance_without_accumulating_prior_leftover`
  - **Traces:** US-5.2; generation_balances

- [ ] **Task:** Payment-failed downgrade to Grátis
  - **Acceptance criteria:**
    - After Stripe's retry window ends unpaid, the restaurant's `plan_id` reverts to the seeded Grátis plan without changing `restaurant_statuses` (cardápio stays up).
    - Any dish beyond the Grátis `dish_limit` is auto-hidden (`dish_statuses` = oculto) until regularized.
    - The dono sees an in-panel notice explaining why.
  - **Feature tests:** `DelinquencyDowngradeTest::an_unrecovered_failed_payment_reverts_the_restaurant_to_the_free_plan_without_suspending_it`, `DelinquencyDowngradeTest::dishes_beyond_the_free_plans_limit_are_auto_hidden_on_downgrade`
  - **Traces:** US-5.5; restaurants, plans, dish_statuses

---

## Phase 16: Appearance, QR Code & Metrics

**Goal:** branding, distribution, and the retention-critical view metrics. · **Depends on:** Phase 7, Phase 14, Phase 5 · **Covers:** US-6.1, US-6.2, US-6.3

- [ ] **Task:** Appearance editor with live preview
  - **Acceptance criteria:** logo upload, accent color picker, font selector; a live preview renders the Phase 8 feed shell with the chosen values before saving; saved values are what Phase 14 actually renders for real visitors.
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-6.1; restaurants

- [ ] **Task:** QR Code generation and printable table-display template
  - **Acceptance criteria:** generates a QR code encoding the feed URL (`/r/{slug}`), which stays valid across any later content/appearance edit because it only encodes the stable slug; offers a downloadable/printable table-display template.
  - **Feature tests:** `QrCodeTest::the_generated_qr_code_still_resolves_to_the_feed_after_editing_categories_dishes_and_appearance`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-6.2; restaurants

- [ ] **Task:** Views and watch-time dashboard, gated by plan
  - **Acceptance criteria:** aggregates `dish_views` into per-dish view counts and average seconds watched; the level of detail shown matches the restaurant's `metrics_level_id` (cardápio total / por prato / por prato + tempo assistido).
  - **Feature tests:** `ViewMetricsTest::a_free_plan_restaurant_only_sees_the_cardapio_total_metric`, `ViewMetricsTest::a_pro_plan_restaurant_sees_per_dish_view_count_and_average_watch_time`
  - **Design ref:** `Docs/Desegner/Shared artifact link/Painel do Restaurante.dc.html`
  - **Traces:** US-6.3; dish_views, metrics_levels, plans

---

## Phase 17: Super Admin — Dashboard & Restaurant Management

**Goal:** the super admin's day-to-day operating view of the business and its tenants. · **Depends on:** Phase 9, Phase 3, Phase 5 · **Covers:** US-7.1, US-7.2

- [ ] **Task:** Business metrics dashboard widget set
  - **Acceptance criteria:** shows MRR (sum of active subscriptions' plan price), active restaurants segmented by plan, new/canceled restaurants in the current month, and Grátis→pago conversion rate.
  - **Feature tests:** `AdminDashboardTest::mrr_sums_only_currently_active_subscriptions`, `AdminDashboardTest::conversion_rate_divides_upgraded_restaurants_by_total_free_plan_signups_in_the_period`
  - **Traces:** US-7.1; restaurants, plans, subscriptions

- [ ] **Task:** Restaurant listing resource
  - **Acceptance criteria:** a Filament table lists every restaurant with plan, status, and usage (dish count vs. limit, generation balance); filterable by plan/status.
  - **Traces:** US-7.2; restaurants, plans

- [ ] **Task:** Suspend/reactivate action
  - **Acceptance criteria:**
    - Toggles `restaurant_statuses`; a suspended restaurant's dono panel and public feed both become inaccessible (a clear "unavailable" response, not a 404).
    - Every toggle writes an `admin_logs` row (`restaurante_suspenso`/`restaurante_reativado`) with the acting super_admin.
  - **Feature tests:** `RestaurantSuspensionTest::a_suspended_restaurants_public_feed_becomes_inaccessible`, `RestaurantSuspensionTest::suspending_a_restaurant_writes_an_audit_log_entry`
  - **Traces:** US-7.2; restaurant_statuses, admin_logs, admin_actions

- [ ] **Task:** Impersonate action
  - **Acceptance criteria:**
    - "Entrar como o dono" grants the super_admin access to that restaurant's panel without its password, clearly bannered as impersonation, revertible back to the admin panel.
    - Every impersonation writes an `admin_logs` row (`restaurante_impersonado`).
  - **Feature tests:** `ImpersonationTest::impersonating_a_restaurant_grants_access_to_its_panel_and_logs_the_action`, `ImpersonationTest::ending_impersonation_returns_to_the_super_admin_session`
  - **Traces:** US-7.2; admin_logs, admin_actions

---

## Phase 18: Super Admin — Plans, Coupons & Packages

**Goal:** the commercial levers the super admin can pull without a deploy. · **Depends on:** Phase 9, Phase 5 · **Covers:** US-7.3

- [ ] **Task:** Plan CRUD resource
  - **Acceptance criteria:** create/edit `name`, `price_cents`, `monthly_generations`, `initial_generations`, `dish_limit`, `removes_branding`, `metrics_level_id`, linked `stripe_price_id`; editing an existing plan does not retroactively change balances already granted.
  - **Feature tests:** `PlanManagementTest::editing_a_plans_limits_does_not_retroactively_change_already_granted_balances`
  - **Traces:** US-7.3; plans, metrics_levels

- [ ] **Task:** Addon package CRUD resource
  - **Acceptance criteria:** create/edit `name`, `generations_count`, `price_cents`, `stripe_price_id`, `is_active`.
  - **Traces:** US-7.3; video_addon_packages

- [ ] **Task:** Coupon CRUD resource, synced to Stripe
  - **Acceptance criteria:** creating/editing/deactivating a coupon calls the Stripe API to keep `stripe_coupon_id` in sync; `discount_type_id` selects percentual vs. valor_fixo.
  - **Feature tests:** `CouponManagementTest::creating_a_coupon_syncs_a_matching_stripe_coupon`
  - **Traces:** US-7.3; coupons, discount_types

---

## Phase 19: Super Admin — AI Generation Ops

**Goal:** operating and tuning the AI video pipeline without touching code. · **Depends on:** Phase 9, Phase 6 · **Covers:** US-7.4

- [ ] **Task:** Generation queue monitor and reprocess
  - **Acceptance criteria:** lists `video_generations` by status with per-item and monthly total `cost_usd`; "reprocessar" re-dispatches an `erro` generation through Phase 12's job without double-debiting the balance.
  - **Feature tests:** `GenerationOpsTest::reprocessing_an_errored_generation_does_not_double_debit_the_balance`
  - **Traces:** US-7.4; video_generations, generation_ledger

- [ ] **Task:** AI preset CRUD resource
  - **Acceptance criteria:** edit `prompt`, `camera_movement`, `duration_seconds`, and the bound `provider_id`; changing a preset's provider does not rewrite the `provider_id` snapshot already stored on past `video_generations` rows.
  - **Feature tests:** `AiPresetManagementTest::changing_a_presets_provider_does_not_rewrite_past_generations_provider_snapshot`
  - **Traces:** US-7.4; ai_presets

- [ ] **Task:** AI provider registry CRUD resource
  - **Acceptance criteria:** create/activate/deactivate `ai_providers` entries; a deactivated provider can no longer be selected on a preset going forward, but is preserved on historical records.
  - **Traces:** US-7.4; ai_providers

---

## Phase 20: Super Admin — Moderation & Financial Overview

**Goal:** content safety and the profitability view. · **Depends on:** Phase 9, Phase 6, Phase 17 · **Covers:** US-7.5, US-7.6

- [ ] **Task:** Content moderation list and removal
  - **Acceptance criteria:**
    - Browse `dish_photos`/`videos` filterable by restaurant.
    - "Remover" deactivates the item; if it was a dish's `active_video_id`, the pointer is cleared so the dish drops out of the public feed immediately.
    - Every removal writes an `admin_logs` row (`conteudo_removido`).
  - **Feature tests:** `ContentModerationTest::removing_a_dishs_active_video_immediately_drops_it_from_the_public_feed`, `ContentModerationTest::removing_content_writes_an_audit_log_entry`
  - **Traces:** US-7.5; dish_photos, videos, admin_logs, admin_actions

- [ ] **Task:** Cost-vs-revenue dashboard
  - **Acceptance criteria:** shows current-month AI cost (sum of `video_generations.cost_usd`) and current-month Mux cost (entered manually by the super admin for now — no automated import job required), compared against the period's MRR + addon revenue from Phase 17/18's figures.
  - **Feature tests:** `CostRevenueDashboardTest::ai_cost_sums_only_the_current_months_generation_rows`
  - **Traces:** US-7.6; video_generations
