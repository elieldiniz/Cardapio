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

#[Fillable(['plan_id', 'status_id', 'name', 'description', 'slug', 'logo_path', 'cover_path', 'accent_color', 'font'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use Billable, HasFactory, SoftDeletes;

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
}
