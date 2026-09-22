<?php

namespace App\Filament\Resources\VideoAddonPackages;

use App\Filament\Resources\VideoAddonPackages\Pages\CreateVideoAddonPackage;
use App\Filament\Resources\VideoAddonPackages\Pages\EditVideoAddonPackage;
use App\Filament\Resources\VideoAddonPackages\Pages\ListVideoAddonPackages;
use App\Filament\Resources\VideoAddonPackages\Schemas\VideoAddonPackageForm;
use App\Filament\Resources\VideoAddonPackages\Tables\VideoAddonPackagesTable;
use App\Models\VideoAddonPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VideoAddonPackageResource extends Resource
{
    protected static ?string $model = VideoAddonPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'pacote de gerações';

    protected static ?string $pluralModelLabel = 'pacotes de gerações';

    protected static string|\UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VideoAddonPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VideoAddonPackagesTable::configure($table);
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
            'index' => ListVideoAddonPackages::route('/'),
            'create' => CreateVideoAddonPackage::route('/create'),
            'edit' => EditVideoAddonPackage::route('/{record}/edit'),
        ];
    }
}
