<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order: a snapshot of what was bought, so editing or deleting the
 * product later never changes a past order (RF-06).
 */
#[Fillable(['order_id', 'dish_id', 'name', 'sale_type', 'quantity', 'unit_price', 'total', 'notes', 'selections', 'display_order'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'selections' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * "2" for units, "0,5 kg" for weighed products.
     */
    public function quantityLabel(): string
    {
        $quantity = (float) $this->quantity;

        return $this->sale_type === 'kg'
            ? rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',').' kg'
            : (string) (int) $quantity;
    }
}
