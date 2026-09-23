<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Models\Restaurant;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRestaurants extends ListRecords
{
    protected static string $resource = RestaurantResource::class;

    public function getTabs(): array
    {
        return [
            'ativos' => Tab::make('Restaurantes')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('restaurants.deleted_at')),
            'excluidos' => Tab::make('Excluídos pelo dono')
                ->badge(fn () => Restaurant::onlyTrashed()->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('restaurants.deleted_at')),
        ];
    }
}
