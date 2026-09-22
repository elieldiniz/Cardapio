<?php

namespace App\Filament\Resources\Videos\Tables;

use App\Actions\Admin\RemoveContent;
use App\Filament\Support\ModerationTable;
use App\Models\Video;
use App\Support\MuxUrls;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['dish.restaurant', 'status', 'origin']))
            ->columns([
                ImageColumn::make('cover_path')->label('Capa')->imageHeight(72),
                TextColumn::make('dish.name')->label('Prato')->searchable()
                    ->description(fn (Video $record) => $record->dish?->active_video_id === $record->id ? 'Ativo no cardápio' : null),
                TextColumn::make('dish.restaurant.name')->label('Restaurante'),
                TextColumn::make('origin.name')->label('Origem')->badge(),
                TextColumn::make('status.name')->label('Status')->badge()
                    ->color(fn (Video $record) => match ($record->status?->slug) {
                        'aprovado' => 'success',
                        'rejeitado' => 'danger',
                        'aguardando_aprovacao' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('duration_seconds')->label('Duração')->suffix('s')->placeholder('—'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                ModerationTable::restaurantFilter(),
                SelectFilter::make('status')->label('Status')->relationship('status', 'name'),
            ])
            ->recordActions([
                Action::make('watch')
                    ->label('Assistir')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (Video $record) => $record->mux_playback_id !== null)
                    ->url(fn (Video $record) => MuxUrls::mp4($record->mux_playback_id), shouldOpenInNewTab: true),
                Action::make('remove')
                    ->label('Remover')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Video $record) => $record->status?->slug !== 'rejeitado')
                    ->requiresConfirmation()
                    ->modalDescription('O vídeo sai do cardápio imediatamente e é apagado do Mux. A ação fica registrada no log de auditoria.')
                    ->schema([TextInput::make('reason')->label('Motivo (opcional)')->maxLength(255)])
                    ->action(function (Video $record, array $data) {
                        app(RemoveContent::class)->video(auth()->user(), $record, $data['reason'] ?? null);
                        Notification::make()->title('Vídeo removido')->success()->send();
                    }),
            ]);
    }
}
