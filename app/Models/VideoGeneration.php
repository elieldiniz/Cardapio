<?php

namespace App\Models;

use Database\Factories\VideoGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dish_id', 'preset_id', 'provider_id', 'status_id', 'variations_requested', 'provider_operations', 'cost_usd'])]
class VideoGeneration extends Model
{
    /** @use HasFactory<VideoGenerationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'provider_operations' => 'array',
        ];
    }

    public const TURN_LIMITED = 'limited';

    public const TURN_WIDE = 'wide';

    /**
     * 1–2 photos never get a full turn (the AI would invent the back of the
     * dish); 3–4 angles allow a wider one (US-3.1, US-3.2).
     */
    public static function turnFor(int $photoCount): string
    {
        return $photoCount >= 3 ? self::TURN_WIDE : self::TURN_LIMITED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status?->slug, ['pronto', 'erro'], true);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(AiPreset::class, 'preset_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(GenerationStatus::class, 'status_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'generation_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VideoGenerationPhoto::class);
    }
}
