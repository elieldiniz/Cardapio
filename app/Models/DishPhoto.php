<?php

namespace App\Models;

use Database\Factories\DishPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dish_id', 'file_path', 'display_order'])]
class DishPhoto extends Model
{
    /** @use HasFactory<DishPhotoFactory> */
    use HasFactory;

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function videoGenerationPhotos(): HasMany
    {
        return $this->hasMany(VideoGenerationPhoto::class);
    }
}
