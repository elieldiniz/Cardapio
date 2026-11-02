<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment registered on an order; an order may have several (split payment) (RF-09).
 * `reference` makes registering the same payment twice harmless (RNF-04).
 */
#[Fillable(['order_id', 'payment_method_id', 'amount', 'received', 'change', 'reference'])]
class OrderPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received' => 'decimal:2',
            'change' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
