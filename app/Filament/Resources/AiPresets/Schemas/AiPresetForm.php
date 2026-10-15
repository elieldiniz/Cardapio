<?php

namespace App\Filament\Resources\AiPresets\Schemas;

use App\Models\AiPreset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AiPresetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nome')->required()->maxLength(80),
                Select::make('provider_id')
                    ->label('Provedor de IA')
                    ->helperText('Trocar o provedor vale para as próximas gerações; o histórico guarda o provedor usado em cada uma.')
                    // A deactivated provider can't be picked going forward, but stays selected on a preset that already uses it.
                    ->relationship('provider', 'name', fn (Builder $query, ?AiPreset $record) => $query->where(
                        fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhere('id', $record->provider_id))
                    ))
                    ->required(),
                Textarea::make('prompt')->label('Prompt')->helperText('Prompt curto e objetivo, só com o movimento de câmera. Não invente nada que não esteja no prato (fumaça, vapor, faíscas, ingredientes). O dono escolhe o estilo e pode ajustar o texto.')->rows(5)->required()->columnSpanFull(),
                TextInput::make('camera_movement')->label('Movimento de câmera')->helperText('Ex.: giro 30–45° com leve aproximação.')->required()->maxLength(255),
                TextInput::make('duration_seconds')->label('Duração (s)')->numeric()->integer()->minValue(5)->maxValue(10)->required(),
                Toggle::make('is_active')->label('Ativo')->helperText('Presets ativos aparecem como estilos para o dono escolher (e ajustar). O primeiro ativo é o padrão.')->default(true)->inline(false),
            ]);
    }
}
