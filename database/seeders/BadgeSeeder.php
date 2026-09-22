<?php

namespace Database\Seeders;

use App\Models\Badge;

class BadgeSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return Badge::class;
    }

    protected function rows(): array
    {
        return [
            'novo' => 'Novo',
            'mais_pedido' => 'Mais Pedido',
            'vegetariano' => 'Vegetariano',
        ];
    }
}
