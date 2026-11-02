<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer's stamp card (RF-18). Open until its reward is redeemed.
 */
#[Fillable(['loyalty_program_id', 'customer_id', 'stamps', 'completed_at', 'redeemed_at'])]
class LoyaltyCard extends Model
{
    protected function casts(): array
    {
        return [
            'stamps' => 'integer',
            'completed_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRedeemable(): bool
    {
        return $this->completed_at !== null && $this->redeemed_at === null;
    }
}
