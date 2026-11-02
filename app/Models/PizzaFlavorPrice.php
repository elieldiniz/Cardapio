<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The price of one flavor in one size. No row = the flavor does not exist in that size.
 */
#[Fillable(['pizza_flavor_id', 'pizza_size_id', 'price'])]
class PizzaFlavorPrice extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function flavor(): BelongsTo
    {
        return $this->belongsTo(PizzaFlavor::class, 'pizza_flavor_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(PizzaSize::class, 'pizza_size_id');
    }
}
