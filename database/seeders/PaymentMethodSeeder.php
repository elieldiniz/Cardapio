<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;

class PaymentMethodSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return PaymentMethod::class;
    }

    protected function rows(): array
    {
        return [
            'pix' => 'Pix',
            'dinheiro' => 'Dinheiro',
            'debito' => 'Débito',
            'credito' => 'Crédito',
            'cartao' => 'Cartão',
            'fiado' => 'Fiado',
        ];
    }
}
