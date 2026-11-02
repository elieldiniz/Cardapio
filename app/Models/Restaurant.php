<?php

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

#[Fillable(['plan_id', 'status_id', 'name', 'description', 'slug', 'logo_path', 'cover_path', 'accent_color', 'font', 'menu_style', 'trial_ends_at', 'qr_settings', 'business_type'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use Billable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'qr_settings' => 'array',
        ];
    }

    /**
     * Accent color used by the panel and the feed until the dono picks one (US-6.1).
     */
    public const DEFAULT_ACCENT_COLOR = '#FF6B3D';

    /**
     * The public feed URL encoded in the table QR code. It only depends on the
     * slug, so it stays valid across any content or appearance edit (US-6.2).
     */
    public function feedUrl(): string
    {
        return route('feed.show', $this);
    }

    public function isSuspended(): bool
    {
        $suspendedId = RestaurantStatus::query()->where('slug', 'suspenso')->value('id');

        return $suspendedId !== null && $this->status_id === $suspendedId;
    }

    /**
     * Free plan during its 14-day trial: every feature is on (clock starts at signup).
     */
    public function onTrial(): bool
    {
        return $this->plan?->price_cents === 0 && $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** Whole days left of the trial, counting today (0 when it is over). */
    public function trialDaysLeft(): int
    {
        return $this->onTrial() ? max(1, (int) ceil(now()->diffInSeconds($this->trial_ends_at, false) / 86400)) : 0;
    }

    /** True for a free plan whose trial has ended (what the "assine" notices look at). */
    public function trialEnded(): bool
    {
        return $this->plan?->price_cents === 0 && $this->trial_ends_at !== null && ! $this->trial_ends_at->isFuture();
    }

    /**
     * Whether the restaurant can use a feature: everything during the trial, otherwise what its plan includes.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->onTrial() || (bool) $this->plan?->hasFeature($feature);
    }

    /** A caixa still open or a fiado still owed: the caixa screens stay reachable so the money is never stuck. */
    public function hasCashToWrapUp(): bool
    {
        return $this->openCashSession() !== null
            || $this->receivables()->whereNull('paid_at')->whereNull('cancelled_at')->exists();
    }

    /** Metrics level in effect: the highest one during the trial. */
    public function metricsLevelSlug(): string
    {
        return $this->onTrial() ? 'por_prato_com_tempo_assistido' : ($this->plan?->metricsLevel?->slug ?? 'cardapio_total');
    }

    /** The cheapest active plan that includes a feature, to say what to subscribe to. */
    public function planFor(string $feature): ?Plan
    {
        return Plan::query()->where('is_active', true)->orderBy('price_cents')->get()->first(fn (Plan $plan) => $plan->hasFeature($feature));
    }

    /** The cardápio's visual style, falling back to the original glass look. */
    public function menuStyle(): string
    {
        return array_key_exists((string) $this->menu_style, config('feed.styles')) ? $this->menu_style : 'vidro';
    }

    public function accentColor(): string
    {
        return $this->accent_color ?: self::DEFAULT_ACCENT_COLOR;
    }

    /**
     * Whether the current plan's dish limit still allows another dish (US-5.1).
     */
    public function canAddDish(): bool
    {
        $limit = $this->plan?->dish_limit;

        return $limit === null || $this->dishes()->count() < $limit;
    }

    /**
     * Generations already promised to requests still in the queue — they are
     * debited on completion, so they are held back from new requests.
     */
    public function reservedGenerations(): int
    {
        return (int) VideoGeneration::query()
            ->whereIn('dish_id', $this->dishes()->select('id'))
            ->whereHas('status', fn ($status) => $status->whereIn('slug', ['fila', 'gerando']))
            ->sum('variations_requested');
    }

    /**
     * Monthly + addon balance minus pending reservations (US-3.4).
     */
    public function availableGenerations(): int
    {
        $balance = $this->generationBalance;

        if ($balance === null) {
            return 0;
        }

        return max(0, $balance->monthly_balance + $balance->addon_balance - $this->reservedGenerations());
    }

    /**
     * Visible (non-hidden) dishes still fit the plan's limit — used when a dono
     * un-hides a dish after a downgrade (US-5.5).
     */
    public function canShowAnotherDish(): bool
    {
        $limit = $this->plan?->dish_limit;

        return $limit === null || $this->dishes()->whereHas('status', fn ($status) => $status->where('slug', '!=', 'oculto'))->count() < $limit;
    }

    /**
     * The dono's e-mail identifies the restaurant as a Stripe customer.
     */
    public function stripeEmail(): ?string
    {
        return $this->users()->whereHas('role', fn ($role) => $role->where('slug', 'dono'))->orderBy('id')->value('email');
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RestaurantStatus::class, 'status_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function generationBalance(): HasOne
    {
        return $this->hasOne(GenerationBalance::class);
    }

    public function generationLedger(): HasMany
    {
        return $this->hasMany(GenerationLedger::class);
    }

    public function dishViews(): HasMany
    {
        return $this->hasMany(DishView::class);
    }

    public function distanceTiers(): HasMany
    {
        return $this->hasMany(DeliveryDistanceTier::class)->orderBy('from_km');
    }

    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function loyaltyProgram(): HasOne
    {
        return $this->hasOne(LoyaltyProgram::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function promoBanner(): HasOne
    {
        return $this->hasOne(PromoBanner::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * The open cash session, if the store has one (RF-10).
     */
    public function openCashSession(): ?CashSession
    {
        return $this->cashSessions()->open()->latest('id')->first();
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class)->orderBy('display_order')->orderBy('id');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class);
    }

    public function orderSetting(): HasOne
    {
        return $this->hasOne(RestaurantOrderSetting::class);
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /**
     * The order settings, or an unsaved row carrying the defaults (RF-01, RF-02e) so
     * callers never have to null-check a store that has not opened the settings yet.
     */
    public function orderSettings(): RestaurantOrderSetting
    {
        return $this->orderSetting ?? new RestaurantOrderSetting([
            'restaurant_id' => $this->id,
            'timezone' => RestaurantOrderSetting::DEFAULT_TIMEZONE,
            'accepts_orders' => true,
            'hours_enabled' => false,
            'accepts_when_closed' => false,
            'delivery_mode' => 'a_combinar',
            'accepts_other_neighborhood' => true,
            'pizza_pricing_rule' => 'maior',
        ]);
    }
}
