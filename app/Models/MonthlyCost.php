<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['month', 'mux_cost_usd', 'usd_brl_rate'])]
class MonthlyCost extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'mux_cost_usd' => 'decimal:2',
            'usd_brl_rate' => 'decimal:4',
        ];
    }

    public static function forMonth(CarbonInterface $month): ?self
    {
        return static::query()->whereDate('month', $month->copy()->startOfMonth()->toDateString())->first();
    }
}
