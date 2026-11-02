<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the cash register: a sale, an expense, a withdrawal, a change top-up or a
 * refund (RF-10). The amount is always positive; the type says which way the money goes.
 * `reference` makes recording the same event twice harmless (RNF-04).
 */
#[Fillable(['restaurant_id', 'cash_session_id', 'type_id', 'payment_method_id', 'order_id', 'user_id', 'amount', 'category', 'description', 'reference', 'occurred_at'])]
class CashMovement extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CashMovementType::class, 'type_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
