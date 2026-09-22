<?php

namespace App\Filament\Resources\VideoAddonPackages\Tables;

use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideoAddonPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Pacote')->searchable(),
                TextColumn::make('generations_count')->label('Gerações')->sortable(),
                TextColumn::make('price_cents')->label('Preço')->formatStateUsing(fn (int $state) => Money::brlFromCents($state))->sortable(),
                TextColumn::make('stripe_price_id')->label('Stripe')->placeholder('—')->copyable(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->defaultSort('generations_count')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
