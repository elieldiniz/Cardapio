<?php

namespace Database\Seeders;

use App\Models\RestaurantStatus;

class RestaurantStatusSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return RestaurantStatus::class;
    }

    protected function rows(): array
    {
        return [
            'ativo' => 'Ativo',
            'suspenso' => 'Suspenso',
        ];
    }
}
