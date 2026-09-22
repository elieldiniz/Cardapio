<?php

namespace App\Filament\Resources\AiPresets\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiPresetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Preset')->searchable(),
                TextColumn::make('provider.name')->label('Provedor')->badge(),
                TextColumn::make('camera_movement')->label('Movimento')->limit(40),
                TextColumn::make('duration_seconds')->label('Duração')->suffix('s'),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
