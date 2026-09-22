<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price_cents', 'stripe_price_id', 'monthly_generations', 'initial_generations', 'dish_limit', 'removes_branding', 'metrics_level_id', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'removes_branding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function metricsLevel(): BelongsTo
    {
        return $this->belongsTo(MetricsLevel::class);
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
