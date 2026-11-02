<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'name', 'icon', 'display_order', 'is_visible', 'shows_from', 'shows_until'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Whether the category's own time window (RF-02b, "cardápio do almoço") allows it
     * to show at the given store-local time. No window means always shown; a window
     * whose end is before its start runs past midnight.
     */
    public function isShownAt(CarbonInterface $localTime): bool
    {
        if (! $this->shows_from || ! $this->shows_until) {
            return true;
        }

        $now = $localTime->format('H:i');
        $from = substr($this->shows_from, 0, 5);
        $until = substr($this->shows_until, 0, 5);

        if ($from === $until) {
            return true;
        }

        return $from < $until
            ? $now >= $from && $now < $until
            : $now >= $from || $now < $until;
    }
}
