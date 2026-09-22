<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Editing the discount or expiry replaces the Stripe coupon (see CouponSync).
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Cupom salvo e sincronizado com a Stripe';
    }
}
