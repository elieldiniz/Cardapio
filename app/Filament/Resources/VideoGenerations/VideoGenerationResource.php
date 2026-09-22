<?php

namespace App\Filament\Resources\VideoGenerations;

use App\Filament\Resources\VideoGenerations\Pages\ListVideoGenerations;
use App\Filament\Resources\VideoGenerations\Tables\VideoGenerationsTable;
use App\Models\VideoGeneration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The AI generation queue (US-7.4): status, cost and reprocessing.
 */
class VideoGenerationResource extends Resource
{
    protected static ?string $model = VideoGeneration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'geração';

    protected static ?string $pluralModelLabel = 'fila de gerações';

    protected static string|UnitEnum|null $navigationGroup = 'Geração por IA';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return VideoGenerationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideoGenerations::route('/'),
        ];
    }
}
