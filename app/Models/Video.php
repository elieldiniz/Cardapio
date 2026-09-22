<?php

namespace App\Models;

use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dish_id', 'origin_id', 'generation_id', 'status_id', 'mux_asset_id', 'mux_playback_id', 'cover_path', 'duration_seconds'])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(VideoOrigin::class, 'origin_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(VideoStatus::class, 'status_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(VideoGeneration::class, 'generation_id');
    }
}
