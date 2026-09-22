<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->copyable(),
                TextColumn::make('discount_value')
                    ->label('Desconto')
                    ->formatStateUsing(fn ($state, Coupon $record) => $record->discountType?->slug === 'percentual'
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%'
                        : Money::brl($state)),
                TextColumn::make('expires_at')->label('Expira em')->dateTime('d/m/Y H:i')->placeholder('Sem validade'),
                TextColumn::make('stripe_coupon_id')->label('Stripe')->placeholder('—'),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
