<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone who ordered from a store, identified by their WhatsApp (RF-19). Personal
 * data lives here only until the dono erases it or two years pass without an order.
 */
#[Fillable(['restaurant_id', 'name', 'whatsapp', 'orders_count', 'first_order_at', 'last_order_at', 'erased_at'])]
class Customer extends Model
{
    public const LOYAL_ORDERS = 5;

    protected function casts(): array
    {
        return [
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isLoyal(): bool
    {
        return $this->orders_count >= self::LOYAL_ORDERS;
    }
}
