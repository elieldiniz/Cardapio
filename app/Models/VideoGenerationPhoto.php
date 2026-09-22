<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['video_generation_id', 'dish_photo_id', 'angle_order'])]
class VideoGenerationPhoto extends Model
{
    public $timestamps = false;

    public function videoGeneration(): BelongsTo
    {
        return $this->belongsTo(VideoGeneration::class);
    }

    public function dishPhoto(): BelongsTo
    {
        return $this->belongsTo(DishPhoto::class);
    }
}
