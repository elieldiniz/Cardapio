<?php

namespace App\Filament\Resources\Restaurants\Tables;

use App\Filament\Resources\Restaurants\Actions\RestaurantActions;
use App\Models\Restaurant;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestaurantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['plan', 'status', 'generationBalance'])->withCount('dishes'))
            ->columns([
                TextColumn::make('name')
                    ->label('Restaurante')
                    ->description(fn (Restaurant $record) => '/r/'.$record->slug)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('Plano')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Restaurant $record) => $record->isSuspended() ? 'danger' : 'success'),
                TextColumn::make('dishes_count')
                    ->label('Pratos')
                    ->formatStateUsing(fn (int $state, Restaurant $record) => $state.' / '.($record->plan?->dish_limit ?? '∞'))
                    ->sortable(),
                TextColumn::make('generation_balance')
                    ->label('Saldo de gerações')
                    ->state(fn (Restaurant $record) => ($record->generationBalance?->monthly_balance ?? 0) + ($record->generationBalance?->addon_balance ?? 0))
                    ->description(fn (Restaurant $record) => 'mensal '.($record->generationBalance?->monthly_balance ?? 0).' · avulso '.($record->generationBalance?->addon_balance ?? 0)),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Excluído pelo dono')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Restaurant $record) => $record->deleted_at?->diffForHumans())
                    ->sortable()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'excluidos'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('plan')
                    ->label('Plano')
                    ->relationship('plan', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->relationship('status', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    RestaurantActions::impersonate(),
                    RestaurantActions::suspend(),
                    RestaurantActions::reactivate(),
                    RestaurantActions::restore(),
                    RestaurantActions::purge(),
                ]),
            ]);
    }
}
