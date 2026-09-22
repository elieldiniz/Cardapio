<?php

namespace App\Filament\Resources\DishPhotos\Pages;

use App\Filament\Resources\DishPhotos\DishPhotoResource;
use Filament\Resources\Pages\ListRecords;

class ListDishPhotos extends ListRecords
{
    protected static string $resource = DishPhotoResource::class;
}
