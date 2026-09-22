<?php

namespace App\Filament\Resources\AiProviders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Provedor')->searchable(),
                TextColumn::make('slug')->label('Integração')->badge(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
