<?php

namespace App\Models;

use App\Services\Billing\CouponSync;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'stripe_coupon_id', 'discount_type_id', 'discount_value', 'is_active', 'expires_at'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (Coupon $coupon) => app(CouponSync::class)->created($coupon));
        static::updated(fn (Coupon $coupon) => app(CouponSync::class)->updated($coupon));
    }

    public function discountType(): BelongsTo
    {
        return $this->belongsTo(DiscountType::class);
    }
}
