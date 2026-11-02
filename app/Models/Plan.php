<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price_cents', 'stripe_price_id', 'monthly_generations', 'initial_generations', 'first_subscription_bonus', 'dish_limit', 'removes_branding', 'qr_customization', 'features', 'metrics_level_id', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'removes_branding' => 'boolean',
            'qr_customization' => 'boolean',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether this plan includes a feature (RF-02d). A key the plan does not
     * override falls back to config/plan_features.php.
     */
    public function hasFeature(string $feature): bool
    {
        $override = $this->features[$feature] ?? null;

        return (bool) ($override ?? config("plan_features.defaults.{$feature}", false));
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
