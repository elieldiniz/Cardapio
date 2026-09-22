<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'monthly_balance', 'addon_balance', 'renews_at'])]
class GenerationBalance extends Model
{
    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function hasEnoughBalance(int $needed): bool
    {
        return ($this->monthly_balance + $this->addon_balance) >= $needed;
    }
}
