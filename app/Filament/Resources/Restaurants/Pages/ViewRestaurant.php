<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\Actions\RestaurantActions;
use App\Filament\Resources\Restaurants\RestaurantResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRestaurant extends ViewRecord
{
    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestaurantActions::impersonate(),
            RestaurantActions::suspend(),
            RestaurantActions::reactivate(),
        ];
    }
}
