<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An amount a customer owes the store ("fiado", RF-11), created when an order is finalized
 * on credit. It stays open until paid or cancelled.
 */
#[Fillable(['restaurant_id', 'order_id', 'customer_id', 'cash_session_id', 'customer_name', 'customer_phone', 'amount', 'paid_at', 'cancelled_at', 'reference'])]
class Receivable extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->paid_at === null && $this->cancelled_at === null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
