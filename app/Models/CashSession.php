<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One opening of the store's cash register (RF-10): opens with the change in the drawer
 * and closes with the counted cash. Only one session per store is open at a time.
 */
#[Fillable(['restaurant_id', 'opened_by', 'closed_by', 'opening_float', 'opened_at', 'closed_at', 'expected_cash', 'counted_cash', 'difference', 'notes'])]
class CashSession extends Model
{
    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }
}
