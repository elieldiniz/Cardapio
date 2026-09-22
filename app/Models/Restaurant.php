<?php

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

#[Fillable(['plan_id', 'status_id', 'name', 'slug', 'logo_path', 'accent_color', 'font'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use Billable, HasFactory;

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RestaurantStatus::class, 'status_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function generationBalance(): HasOne
    {
        return $this->hasOne(GenerationBalance::class);
    }

    public function generationLedger(): HasMany
    {
        return $this->hasMany(GenerationLedger::class);
    }

    public function dishViews(): HasMany
    {
        return $this->hasMany(DishView::class);
    }
}
