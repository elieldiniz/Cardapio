<?php

namespace Database\Seeders;

use App\Models\GenerationStatus;

class GenerationStatusSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return GenerationStatus::class;
    }

    protected function rows(): array
    {
        return [
            'fila' => 'Fila',
            'gerando' => 'Gerando',
            'pronto' => 'Pronto',
            'erro' => 'Erro',
        ];
    }
}
