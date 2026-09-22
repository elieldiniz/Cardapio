<?php

namespace App\Filament\Resources\VideoAddonPackages\Pages;

use App\Filament\Resources\VideoAddonPackages\VideoAddonPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideoAddonPackages extends ListRecords
{
    protected static string $resource = VideoAddonPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
