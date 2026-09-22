<?php

namespace App\Models;

use Database\Factories\AiPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider_id', 'name', 'prompt', 'camera_movement', 'duration_seconds', 'is_active'])]
class AiPreset extends Model
{
    /** @use HasFactory<AiPresetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function videoGenerations(): HasMany
    {
        return $this->hasMany(VideoGeneration::class, 'preset_id');
    }
}
