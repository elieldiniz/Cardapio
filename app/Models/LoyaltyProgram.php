<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The store's loyalty program (RF-18): after `stamps_required` qualifying orders the customer
 * earns a reward — a percentage, an amount off the next order, or a free item ("brinde").
 */
#[Fillable(['restaurant_id', 'is_active', 'stamps_required', 'min_order', 'reward_type', 'reward_value', 'reward_label'])]
class LoyaltyProgram extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stamps_required' => 'integer',
            'min_order' => 'decimal:2',
            'reward_value' => 'decimal:2',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    /** What the customer wins, in words ("10% de desconto", "R$ 15,00 de desconto", "Sobremesa grátis"). */
    public function rewardText(): string
    {
        return match ($this->reward_type) {
            'percentual' => rtrim(rtrim((string) $this->reward_value, '0'), '.').'% de desconto',
            'valor' => Money::brl($this->reward_value).' de desconto',
            default => $this->reward_label ?: 'Um brinde da casa',
        };
    }
}
