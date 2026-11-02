<?php

namespace Database\Seeders;

use App\Models\OrderStatus;

class OrderStatusSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return OrderStatus::class;
    }

    protected function rows(): array
    {
        return [
            'novo' => 'Novo',
            'em_preparo' => 'Em preparo',
            'em_rota' => 'Pronto / Em rota',
            'finalizado' => 'Finalizado',
            'recusado' => 'Recusado',
            'cancelado' => 'Cancelado',
        ];
    }
}
