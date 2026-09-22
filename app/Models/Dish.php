<?php

namespace App\Models;

use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'category_id', 'status_id', 'name', 'price', 'short_description', 'description', 'display_order'])]
class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DishStatus::class, 'status_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DishVariant::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DishPhoto::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function videoGenerations(): HasMany
    {
        return $this->hasMany(VideoGeneration::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_dish');
    }
}
