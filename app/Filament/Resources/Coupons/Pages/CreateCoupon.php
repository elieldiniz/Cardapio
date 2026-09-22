<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Models\AdminLog;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function afterCreate(): void
    {
        AdminLog::record(auth()->user(), 'cupom_criado', "coupon:{$this->record->id}", ['code' => $this->record->code]);
    }
}
