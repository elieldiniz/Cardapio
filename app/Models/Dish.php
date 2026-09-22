<?php

namespace App\Models;

use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'category_id', 'status_id', 'active_video_id', 'name', 'price', 'short_description', 'description', 'display_order'])]
class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
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

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_dish');
    }
}
