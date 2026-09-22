<?php

namespace App\Actions\Ai;

use App\Exceptions\InsufficientGenerationBalanceException;
use App\Exceptions\VideoGenerationUnavailableException;
use App\Jobs\GenerateDishVideo;
use App\Models\AiPreset;
use App\Models\Dish;
use App\Models\GenerationStatus;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Starts a photo-to-video generation for a dish (US-3.1, US-3.2, US-3.4, US-3.5):
 * validates the photo set, gates on balance *before* anything is created, then
 * records the request and queues the job. Balance is only debited on completion.
 */
class RequestVideoGeneration
{
    public const MIN_PHOTOS = 1;

    public const MAX_PHOTOS = 4;

    public const MIN_VARIATIONS = 2;

    public const MAX_VARIATIONS = 3;

    /**
     * @param  array<int, int>  $photoIds  dish_photos ids in angle order.
     */
    public function handle(Dish $dish, array $photoIds, int $variations): VideoGeneration
    {
        $photoIds = array_values(array_unique(array_map('intval', $photoIds)));

        if (count($photoIds) < self::MIN_PHOTOS || count($photoIds) > self::MAX_PHOTOS) {
            throw new InvalidArgumentException('Escolha de 1 a 4 fotos do prato.');
        }

        if ($variations < self::MIN_VARIATIONS || $variations > self::MAX_VARIATIONS) {
            throw new InvalidArgumentException('Peça de 2 a 3 variações.');
        }

        if ($dish->photos()->whereIn('id', $photoIds)->count() !== count($photoIds)) {
            throw new InvalidArgumentException('As fotos precisam ser deste prato.');
        }

        $preset = static::activePreset() ?? throw VideoGenerationUnavailableException::noActivePreset();

        return DB::transaction(function () use ($dish, $photoIds, $variations, $preset) {
            $restaurant = $dish->restaurant()->lockForUpdate()->first();
            $available = $restaurant->availableGenerations();

            if ($available < $variations) {
                throw new InsufficientGenerationBalanceException($variations, $available);
            }

            $generation = $dish->videoGenerations()->create([
                'preset_id' => $preset->id,
                // Snapshot of the provider actually used, independent of later preset edits.
                'provider_id' => $preset->provider_id,
                'status_id' => GenerationStatus::idFor('fila'),
                'variations_requested' => $variations,
            ]);

            foreach ($photoIds as $index => $photoId) {
                $generation->photos()->create([
                    'dish_photo_id' => $photoId,
                    'angle_order' => $index + 1,
                ]);
            }

            GenerateDishVideo::dispatch($generation->id)->afterCommit();

            return $generation;
        });
    }

    /**
     * The fixed preset every generation uses: the active preset whose provider is active.
     */
    public static function activePreset(): ?AiPreset
    {
        return AiPreset::query()
            ->where('is_active', true)
            ->whereHas('provider', fn ($provider) => $provider->where('is_active', true))
            ->orderBy('id')
            ->first();
    }
}
