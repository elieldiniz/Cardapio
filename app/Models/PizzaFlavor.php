<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pizza flavor, grouped in a section ("Tradicionais", "Especiais") with a price per size (RF-14).
 */
#[Fillable(['dish_id', 'name', 'section', 'ingredients', 'is_available', 'display_order'])]
class PizzaFlavor extends Model
{
    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
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
