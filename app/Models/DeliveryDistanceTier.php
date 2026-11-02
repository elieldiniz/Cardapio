<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One range of the distance-based delivery fee (RF-24a): from `from_km` (exclusive, except the
 * first range which starts at the store) up to `to_km` (inclusive).
 */
#[Fillable(['restaurant_id', 'from_km', 'to_km', 'fee'])]
class DeliveryDistanceTier extends Model
{
    protected function casts(): array
    {
        return ['from_km' => 'decimal:2', 'to_km' => 'decimal:2', 'fee' => 'decimal:2'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
