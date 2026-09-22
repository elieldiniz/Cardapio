<?php

namespace App\Filament\Resources\VideoGenerations\Tables;

use App\Actions\Ai\ReprocessGeneration;
use App\Models\VideoGeneration;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VideoGenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['dish.restaurant', 'status', 'provider', 'preset'])->withCount(['videos', 'photos']))
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('dish.name')->label('Prato')->description(fn (VideoGeneration $record) => $record->dish?->restaurant?->name)->searchable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (VideoGeneration $record) => match ($record->status?->slug) {
                        'erro' => 'danger',
                        'pronto' => 'success',
                        'gerando' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('variations_requested')->label('Variações')->description(fn (VideoGeneration $record) => $record->videos_count.' vídeos · '.$record->photos_count.' fotos'),
                TextColumn::make('provider.name')->label('Provedor'),
                TextColumn::make('preset.name')->label('Preset'),
                TextColumn::make('cost_usd')->label('Custo (US$)')->money('USD')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Pedido em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->relationship('status', 'name'),
                SelectFilter::make('provider')->label('Provedor')->relationship('provider', 'name'),
            ])
            ->recordActions([
                Action::make('reprocess')
                    ->label('Reprocessar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (VideoGeneration $record) => $record->status?->slug === 'erro')
                    ->requiresConfirmation()
                    ->modalDescription('A geração volta para a fila. O saldo do restaurante só é debitado uma vez, quando ficar pronta.')
                    ->action(function (VideoGeneration $record) {
                        app(ReprocessGeneration::class)->handle($record);
                        Notification::make()->title('Geração enviada de volta para a fila')->success()->send();
                    }),
            ]);
    }
}
