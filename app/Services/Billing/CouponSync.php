<?php

namespace App\Services\Billing;

use App\Models\Coupon;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * Keeps a local coupon and its Stripe counterpart in step (US-7.3).
 *
 * Stripe coupons are immutable apart from their name, so any change to the
 * discount or expiry replaces the Stripe coupon; deactivating deletes it. A
 * promotion code with the same `code` lets the dono type it at Checkout.
 */
class CouponSync
{
    /**
     * Fields whose change requires a new Stripe coupon.
     */
    public const STRIPE_FIELDS = ['code', 'discount_type_id', 'discount_value', 'expires_at', 'is_active'];

    public function created(Coupon $coupon): void
    {
        if ($coupon->is_active) {
            $this->push($coupon);
        }
    }

    public function updated(Coupon $coupon): void
    {
        if (! $coupon->wasChanged(self::STRIPE_FIELDS)) {
            return;
        }

        $this->remove($coupon->getOriginal('stripe_coupon_id') ?? $coupon->stripe_coupon_id);
        $coupon->forceFill(['stripe_coupon_id' => null])->saveQuietly();

        if ($coupon->is_active) {
            $this->push($coupon);
        }
    }

    private function push(Coupon $coupon): void
    {
        $coupon->loadMissing('discountType');
        $stripe = Cashier::stripe();

        $discount = $coupon->discountType->slug === 'percentual'
            ? ['percent_off' => (float) $coupon->discount_value]
            : ['amount_off' => (int) round($coupon->discount_value * 100), 'currency' => config('cashier.currency', 'brl')];

        $stripeCoupon = $stripe->coupons->create(array_filter([
            'name' => $coupon->code,
            'duration' => 'once',
            'redeem_by' => $coupon->expires_at?->timestamp,
            'metadata' => ['coupon_id' => (string) $coupon->id],
        ] + $discount, fn ($value) => $value !== null));

        $stripe->promotionCodes->create(array_filter([
            'promotion' => ['type' => 'coupon', 'coupon' => $stripeCoupon->id],
            'code' => $coupon->code,
            'expires_at' => $coupon->expires_at?->timestamp,
        ], fn ($value) => $value !== null));

        $coupon->forceFill(['stripe_coupon_id' => $stripeCoupon->id])->saveQuietly();
    }

    private function remove(?string $stripeCouponId): void
    {
        if ($stripeCouponId === null) {
            return;
        }

        try {
            Cashier::stripe()->coupons->delete($stripeCouponId);
        } catch (InvalidRequestException) {
            // Already gone on Stripe's side — nothing left to sync.
        }
    }
}
