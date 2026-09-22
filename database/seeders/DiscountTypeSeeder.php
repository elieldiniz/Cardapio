<?php

namespace Database\Seeders;

use App\Models\DiscountType;

class DiscountTypeSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return DiscountType::class;
    }

    protected function rows(): array
    {
        return [
            'percentual' => 'Percentual',
            'valor_fixo' => 'Valor Fixo',
        ];
    }
}
