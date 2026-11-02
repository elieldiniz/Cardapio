<?php

namespace Database\Seeders;

use App\Models\CashMovementType;

class CashMovementTypeSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return CashMovementType::class;
    }

    protected function rows(): array
    {
        return [
            'venda' => 'Venda',
            'recebimento_fiado' => 'Recebimento de fiado',
            'despesa' => 'Despesa',
            'retirada' => 'Retirada',
            'reforco' => 'Reforço de troco',
            'estorno' => 'Estorno',
        ];
    }
}
