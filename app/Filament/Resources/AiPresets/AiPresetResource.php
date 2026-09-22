<?php

namespace App\Filament\Resources\AiPresets;

use App\Filament\Resources\AiPresets\Pages\CreateAiPreset;
use App\Filament\Resources\AiPresets\Pages\EditAiPreset;
use App\Filament\Resources\AiPresets\Pages\ListAiPresets;
use App\Filament\Resources\AiPresets\Schemas\AiPresetForm;
use App\Filament\Resources\AiPresets\Tables\AiPresetsTable;
use App\Models\AiPreset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AiPresetResource extends Resource
{
    protected static ?string $model = AiPreset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $modelLabel = 'preset de IA';

    protected static ?string $pluralModelLabel = 'presets de IA';

    protected static string|\UnitEnum|null $navigationGroup = 'Geração por IA';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AiPresetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiPresetsTable::configure($table);
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
            'index' => ListAiPresets::route('/'),
            'create' => CreateAiPreset::route('/create'),
            'edit' => EditAiPreset::route('/{record}/edit'),
        ];
    }
}
