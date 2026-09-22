<?php

namespace App\Filament\Resources\Videos;

use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Tables\VideosTable;
use App\Models\Video;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Generated and uploaded videos, for moderation (US-7.5).
 */
class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?string $modelLabel = 'vídeo';

    protected static ?string $pluralModelLabel = 'vídeos';

    protected static string|UnitEnum|null $navigationGroup = 'Moderação';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return VideosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
        ];
    }
}
