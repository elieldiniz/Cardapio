<?php

namespace Database\Seeders;

use App\Models\DishStatus;

class DishStatusSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return DishStatus::class;
    }

    protected function rows(): array
    {
        return [
            'ativo' => 'Ativo',
            'esgotado' => 'Esgotado',
            'oculto' => 'Oculto',
        ];
    }
}
