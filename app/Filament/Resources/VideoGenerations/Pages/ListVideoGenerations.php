<?php

namespace App\Filament\Resources\VideoGenerations\Pages;

use App\Filament\Resources\VideoGenerations\VideoGenerationResource;
use App\Filament\Widgets\AiCostThisMonth;
use Filament\Resources\Pages\ListRecords;

class ListVideoGenerations extends ListRecords
{
    protected static string $resource = VideoGenerationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AiCostThisMonth::class];
    }
}
