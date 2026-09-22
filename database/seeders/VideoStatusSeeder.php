<?php

namespace Database\Seeders;

use App\Models\VideoStatus;

class VideoStatusSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return VideoStatus::class;
    }

    protected function rows(): array
    {
        return [
            'processando' => 'Processando',
            'aguardando_aprovacao' => 'Aguardando Aprovação',
            'aprovado' => 'Aprovado',
            'rejeitado' => 'Rejeitado',
        ];
    }
}
