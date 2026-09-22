<?php

namespace App\Filament\Resources\AiPresets\Pages;

use App\Filament\Resources\AiPresets\AiPresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiPresets extends ListRecords
{
    protected static string $resource = AiPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
