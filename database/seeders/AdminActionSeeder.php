<?php

namespace Database\Seeders;

use App\Models\AdminAction;

class AdminActionSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return AdminAction::class;
    }

    protected function rows(): array
    {
        return [
            'restaurante_suspenso' => 'Restaurante Suspenso',
            'restaurante_reativado' => 'Restaurante Reativado',
            'restaurante_impersonado' => 'Restaurante Impersonado',
            'conteudo_removido' => 'Conteúdo Removido',
            'plano_atualizado' => 'Plano Atualizado',
            'cupom_criado' => 'Cupom Criado',
            'conta_restaurada' => 'Conta Restaurada',
            'conta_excluida_definitivamente' => 'Conta Excluída Definitivamente',
        ];
    }
}
