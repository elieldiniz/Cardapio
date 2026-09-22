<?php

namespace App\Filament\Resources\VideoAddonPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VideoAddonPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nome')->required()->maxLength(60),
                TextInput::make('generations_count')->label('Gerações')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('price_cents')->label('Preço (centavos)')->helperText('Ex.: 2900 = R$ 29,00')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('stripe_price_id')->label('Stripe price ID')->helperText('Preço de pagamento único em BRL criado na Stripe (price_...).')->maxLength(255)->unique(ignoreRecord: true)->nullable(),
                Toggle::make('is_active')->label('Ativo (à venda)')->default(true)->inline(false),
            ]);
    }
}
