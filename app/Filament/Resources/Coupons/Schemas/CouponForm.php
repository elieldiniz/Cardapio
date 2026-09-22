<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label('Código')
                    ->helperText('O que o dono digita no checkout. Letras e números.')
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[A-Za-z0-9_-]+$/')
                    ->dehydrateStateUsing(fn (string $state) => strtoupper($state))
                    ->unique(ignoreRecord: true),
                Select::make('discount_type_id')->label('Tipo de desconto')->relationship('discountType', 'name')->required()->live(),
                TextInput::make('discount_value')
                    ->label('Valor do desconto')
                    ->helperText('Percentual (ex.: 20) ou valor fixo em reais (ex.: 15,00).')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
                DateTimePicker::make('expires_at')->label('Expira em')->nullable(),
                Toggle::make('is_active')->label('Ativo')->default(true)->inline(false),
                TextInput::make('stripe_coupon_id')->label('Cupom na Stripe')->disabled()->dehydrated(false)->placeholder('Criado ao salvar'),
            ]);
    }
}
