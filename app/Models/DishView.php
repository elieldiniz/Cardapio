<?php

namespace App\Models;

use Database\Factories\DishViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dish_id', 'restaurant_id', 'session_token', 'seconds_watched', 'viewed_on'])]
class DishView extends Model
{
    /** @use HasFactory<DishViewFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
        ];
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
