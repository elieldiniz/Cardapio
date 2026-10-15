<?php

namespace App\Models;

use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['dish_id', 'origin_id', 'generation_id', 'status_id', 'mux_asset_id', 'mux_playback_id', 'cover_path', 'cover_photo_path', 'duration_seconds', 'approved_at'])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

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

    /**
     * The video's cover: the photo the dono chose (the one a generated video was made from, or the
     * cover sent with an own video), otherwise the Mux frame saved when the video got ready.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_photo_path
            ? Storage::disk('public')->url($this->cover_photo_path)
            : $this->cover_path;
    }

    /**
     * Copies a photo into the covers folder, so the cover keeps working even if the original is removed.
     */
    public static function storeCoverCopy(?string $sourcePath, int $restaurantId): ?string
    {
        $disk = Storage::disk('public');

        if ($sourcePath === null || ! $disk->exists($sourcePath)) {
            return null;
        }

        $target = "video-covers/{$restaurantId}/".Str::uuid().'.'.(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
        $disk->copy($sourcePath, $target);

        return $target;
    }

    /**
     * A video that was on the cardápio and got replaced. Refused uploads and
     * clips Mux failed to process never got a playback id, so they don't count.
     */
    public function canBeRestored(): bool
    {
        return $this->status?->slug === 'rejeitado' && $this->mux_playback_id !== null;
    }
}
