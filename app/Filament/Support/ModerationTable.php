<?php

namespace App\Filament\Support;

use App\Models\Restaurant;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared pieces of the moderation lists (US-7.5).
 */
class ModerationTable
{
    public static function restaurantFilter(): SelectFilter
    {
        return SelectFilter::make('restaurant')
            ->label('Restaurante')
            ->options(fn () => Restaurant::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->query(fn (Builder $query, array $data) => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, $restaurantId) => $query->whereHas('dish', fn (Builder $dish) => $dish->where('restaurant_id', $restaurantId)),
            ));
    }
}
