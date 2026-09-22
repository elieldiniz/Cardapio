<?php

namespace Database\Seeders;

use App\Models\MetricsLevel;

class MetricsLevelSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return MetricsLevel::class;
    }

    protected function rows(): array
    {
        return [
            'cardapio_total' => 'Cardápio Total',
            'por_prato' => 'Por Prato',
            'por_prato_com_tempo_assistido' => 'Por Prato com Tempo Assistido',
        ];
    }
}
