<?php

namespace App\Actions\Ai;

use App\Exceptions\InsufficientGenerationBalanceException;
use App\Exceptions\VideoGenerationUnavailableException;
use App\Jobs\GenerateDishVideo;
use App\Models\AiPreset;
use App\Models\Dish;
use App\Models\GenerationStatus;
use App\Models\VideoGeneration;
use Illuminate\Support\Collection;
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

    /** The provider animates a single photo, so a request takes exactly one. */
    public const MAX_PHOTOS = 1;

    /** The dono always asks for one video; the action still supports up to MAX_VARIATIONS for internal uses. */
    public const MIN_VARIATIONS = 1;

    public const MAX_VARIATIONS = 3;

    public const PROMPT_MIN = 10;

    public const PROMPT_MAX = 600;

    /**
     * @param  array<int, int>  $photoIds  dish_photos ids in angle order.
     * @param  int|null  $presetId  the style the dono picked (null = the default one).
     * @param  string|null  $customPrompt  the dono's own description; null/blank = the preset's prompt as registered.
     */
    public function handle(Dish $dish, array $photoIds, int $variations = self::MIN_VARIATIONS, ?int $presetId = null, ?string $customPrompt = null): VideoGeneration
    {
        $photoIds = array_values(array_unique(array_map('intval', $photoIds)));

        if (count($photoIds) < self::MIN_PHOTOS || count($photoIds) > self::MAX_PHOTOS) {
            throw new InvalidArgumentException('Escolha 1 foto do prato.');
        }

        if ($variations < self::MIN_VARIATIONS || $variations > self::MAX_VARIATIONS) {
            throw new InvalidArgumentException('Cada pedido gera de '.self::MIN_VARIATIONS.' a '.self::MAX_VARIATIONS.' vídeos.');
        }

        if ($dish->photos()->whereIn('id', $photoIds)->count() !== count($photoIds)) {
            throw new InvalidArgumentException('As fotos precisam ser deste prato.');
        }

        $preset = $presetId === null
            ? (static::activePreset() ?? throw VideoGenerationUnavailableException::noActivePreset())
            : (static::availablePresets()->firstWhere('id', $presetId) ?? throw new InvalidArgumentException('Este estilo não está disponível.'));

        $customPrompt = static::cleanPrompt($customPrompt);

        return DB::transaction(function () use ($dish, $photoIds, $variations, $preset, $customPrompt) {
            $restaurant = $dish->restaurant()->lockForUpdate()->first();
            $available = $restaurant->availableGenerations();

            if ($available < $variations) {
                throw new InsufficientGenerationBalanceException($variations, $available);
            }

            $generation = $dish->videoGenerations()->create([
                'preset_id' => $preset->id,
                'custom_prompt' => $customPrompt,
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
     * The styles the dono can pick from: every active preset whose provider is active, oldest first.
     *
     * @return Collection<int, AiPreset>
     */
    public static function availablePresets(): Collection
    {
        return AiPreset::query()
            ->where('is_active', true)
            ->whereHas('provider', fn ($provider) => $provider->where('is_active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Whitespace-normalised custom prompt, or null when blank. Throws when it is too short or too long.
     */
    public static function cleanPrompt(?string $prompt): ?string
    {
        $prompt = trim((string) preg_replace('/\s+/u', ' ', (string) $prompt));

        if ($prompt === '') {
            return null;
        }

        $length = mb_strlen($prompt);

        if ($length < self::PROMPT_MIN || $length > self::PROMPT_MAX) {
            throw new InvalidArgumentException('Descreva o vídeo com '.self::PROMPT_MIN.' a '.self::PROMPT_MAX.' caracteres.');
        }

        return $prompt;
    }

    /**
     * The default preset: the first available one.
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
