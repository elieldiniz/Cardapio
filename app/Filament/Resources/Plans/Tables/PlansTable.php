<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Plano')->searchable(),
                TextColumn::make('price_cents')->label('Preço/mês')->formatStateUsing(fn (int $state) => Money::brlFromCents($state))->sortable(),
                TextColumn::make('monthly_generations')->label('Gerações/mês'),
                TextColumn::make('initial_generations')->label('Iniciais'),
                TextColumn::make('dish_limit')->label('Pratos')->placeholder('Ilimitado'),
                TextColumn::make('metricsLevel.name')->label('Métricas'),
                IconColumn::make('removes_branding')->label('Sem marca')->boolean(),
                TextColumn::make('stripe_price_id')->label('Stripe')->placeholder('—')->copyable(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
                TextColumn::make('restaurants_count')->counts('restaurants')->label('Restaurantes'),
            ])
            ->defaultSort('price_cents')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
