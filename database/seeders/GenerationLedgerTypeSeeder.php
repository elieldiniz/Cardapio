<?php

namespace Database\Seeders;

use App\Models\GenerationLedgerType;

class GenerationLedgerTypeSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return GenerationLedgerType::class;
    }

    protected function rows(): array
    {
        return [
            'renovacao' => 'Renovação',
            'compra' => 'Compra',
            'uso' => 'Uso',
            'estorno' => 'Estorno',
        ];
    }
}
