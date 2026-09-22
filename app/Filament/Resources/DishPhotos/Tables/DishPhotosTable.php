<?php

namespace App\Filament\Resources\DishPhotos\Tables;

use App\Actions\Admin\RemoveContent;
use App\Filament\Support\ModerationTable;
use App\Models\DishPhoto;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DishPhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('dish.restaurant'))
            ->columns([
                ImageColumn::make('file_path')->label('Foto')->disk('public')->imageSize(72),
                TextColumn::make('dish.name')->label('Prato')->searchable(),
                TextColumn::make('dish.restaurant.name')->label('Restaurante'),
                TextColumn::make('created_at')->label('Enviada em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                ModerationTable::restaurantFilter(),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remover')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('A foto é apagada do prato. A ação fica registrada no log de auditoria.')
                    ->schema([TextInput::make('reason')->label('Motivo (opcional)')->maxLength(255)])
                    ->action(function (DishPhoto $record, array $data) {
                        app(RemoveContent::class)->photo(auth()->user(), $record, $data['reason'] ?? null);
                        Notification::make()->title('Foto removida')->success()->send();
                    }),
            ]);
    }
}
