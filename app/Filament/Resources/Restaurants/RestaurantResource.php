<?php

namespace App\Filament\Resources\Restaurants;

use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\Resources\Restaurants\Pages\ViewRestaurant;
use App\Filament\Resources\Restaurants\Schemas\RestaurantInfolist;
use App\Filament\Resources\Restaurants\Tables\RestaurantsTable;
use App\Models\Restaurant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'restaurante';

    protected static ?string $pluralModelLabel = 'restaurantes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    /**
     * Restaurants are created by self-service signup and never deleted.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Includes restaurants the dono deleted (trash), shown in their own tab.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RestaurantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurants::route('/'),
            'view' => ViewRestaurant::route('/{record}'),
        ];
    }
}
