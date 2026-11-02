<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A delivery person of one store (RF-22). The token opens the courier's own link and can be
 * revoked (and replaced) by the dono at any time.
 */
#[Fillable(['restaurant_id', 'name', 'phone', 'fee_per_delivery', 'token', 'revoked_at', 'is_active'])]
class Courier extends Model
{
    protected $attributes = ['is_active' => true, 'fee_per_delivery' => '0.00'];

    protected static function booted(): void
    {
        static::creating(function (Courier $courier) {
            $courier->token ??= static::newToken();
        });
    }

    protected function casts(): array
    {
        return [
            'fee_per_delivery' => 'decimal:2',
            'revoked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(32);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function linkIsValid(): bool
    {
        return $this->revoked_at === null && $this->is_active;
    }

    public function firstName(): string
    {
        return Str::before(trim($this->name), ' ');
    }
}
