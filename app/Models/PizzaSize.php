<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pizza size with its slices and how many flavors it accepts (RF-14).
 */
#[Fillable(['dish_id', 'name', 'slices', 'max_flavors', 'is_available', 'display_order'])]
class PizzaSize extends Model
{
    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'slices' => 'integer', 'max_flavors' => 'integer'];
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PizzaFlavorPrice::class);
    }
}
