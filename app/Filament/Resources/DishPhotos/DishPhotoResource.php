<?php

namespace App\Filament\Resources\DishPhotos;

use App\Filament\Resources\DishPhotos\Pages\ListDishPhotos;
use App\Filament\Resources\DishPhotos\Tables\DishPhotosTable;
use App\Models\DishPhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Photos uploaded by donos, for moderation (US-7.5).
 */
class DishPhotoResource extends Resource
{
    protected static ?string $model = DishPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'fotos enviadas';

    protected static string|UnitEnum|null $navigationGroup = 'Moderação';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DishPhotosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDishPhotos::route('/'),
        ];
    }
}
