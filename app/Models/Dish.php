<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'category_id', 'status_id', 'active_video_id', 'name', 'price', 'sale_type', 'kind', 'promo_price', 'promo_ends_at', 'is_featured', 'short_description', 'description', 'display_order'])]
class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'promo_price' => 'decimal:2',
            'promo_ends_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    public function isPizza(): bool
    {
        return $this->kind === 'pizza';
    }

    public function isWeighed(): bool
    {
        return $this->sale_type === 'kg';
    }

    /**
     * The promotional price while it applies: set, lower than the price and not past its end (RF-16).
     */
    public function activePromoPrice(?CarbonInterface $now = null): ?string
    {
        if ($this->promo_price === null || (float) $this->promo_price >= (float) $this->price) {
            return null;
        }

        if ($this->promo_ends_at !== null && ($now ?? now())->greaterThan($this->promo_ends_at)) {
            return null;
        }

        return $this->promo_price;
    }

    /**
     * What a customer pays per unit (or per kg) today, before choices.
     */
    public function currentPrice(?CarbonInterface $now = null): string
    {
        return $this->activePromoPrice($now) ?? $this->price;
    }

    public function pizzaSizes(): HasMany
    {
        return $this->hasMany(PizzaSize::class)->orderBy('display_order')->orderBy('id');
    }

    public function pizzaFlavors(): HasMany
    {
        return $this->hasMany(PizzaFlavor::class)->orderBy('display_order')->orderBy('id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DishStatus::class, 'status_id');
    }

    public function activeVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'active_video_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DishVariant::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DishPhoto::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function videoGenerations(): HasMany
    {
        return $this->hasMany(VideoGeneration::class);
    }

    /**
     * Dishes the dono has not hidden, inside a visible category (US-2.1, US-2.3).
     * "Esgotado" dishes stay listed — they are shown flagged as unavailable.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereHas('status', fn (Builder $status) => $status->whereIn('slug', ['ativo', 'esgotado']))
            ->whereHas('category', fn (Builder $category) => $category->where('is_visible', true));
    }

    public function isSoldOut(): bool
    {
        return $this->status?->slug === 'esgotado';
    }

    /**
     * Complement groups the customer chooses from before adding this product (RF-03),
     * in the order the dono arranged them.
     */
    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'dish_option_group')
            ->withPivot('display_order')
            ->orderBy('dish_option_group.display_order');
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_dish');
    }
}
