<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The store's promotional banner at the top of the cardápio (RF-16): a title, a text, a
 * photo and an optional last day. It disappears by itself the day after `ends_on`.
 */
#[Fillable(['restaurant_id', 'title', 'text', 'image_path', 'ends_on', 'is_active'])]
class PromoBanner extends Model
{
    protected function casts(): array
    {
        return ['ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Shown while it is switched on and its last day (in the store's timezone) has not passed.
     */
    public function isLive(?string $today = null): bool
    {
        $today ??= now()->toDateString();

        return $this->is_active && ($this->ends_on === null || $this->ends_on->toDateString() >= $today);
    }
}
