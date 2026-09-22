<?php

namespace App\Jobs;

use App\Actions\Ai\DebitGenerationBalance;
use App\Contracts\MuxClient;
use App\Models\GenerationStatus;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoOrigin;
use App\Models\VideoStatus;
use App\Services\Ai\VideoGenerationProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs one AI generation (US-3.1): calls the provider bound to the generation's
 * provider snapshot, sends every resulting MP4 to Mux, creates one `videos` row
 * per variation (processando) and debits the balance. Any provider or Mux error
 * marks the generation `erro` and leaves the balance untouched (US-3.4).
 */
class GenerateDishVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Generations cost real money per attempt; retries go through the super
     * admin's explicit "reprocessar" (US-7.4), never automatically.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $generationId) {}

    public function handle(VideoGenerationProviderResolver $resolver, MuxClient $mux, DebitGenerationBalance $debit): void
    {
        $generation = VideoGeneration::with(['preset', 'provider', 'photos.dishPhoto', 'status'])->find($this->generationId);

        if ($generation === null || $generation->status->slug === 'pronto') {
            return;
        }

        $generation->update(['status_id' => GenerationStatus::idFor('gerando')]);

        try {
            $photos = $generation->photos
                ->sortBy('angle_order')
                ->map(fn ($photo) => Storage::disk('public')->url($photo->dishPhoto->file_path))
                ->values()
                ->all();

            $variations = $resolver->resolve($generation->provider->slug)->generate($photos, [
                'prompt' => $generation->preset->prompt,
                'camera_movement' => $generation->preset->camera_movement,
                'duration_seconds' => $generation->preset->duration_seconds,
                'aspect_ratio' => '9:16',
                'variations' => $generation->variations_requested,
                'turn' => VideoGeneration::turnFor(count($photos)),
            ]);

            $variations = array_values(array_filter($variations, fn ($variation) => ! empty($variation['video_url'])));

            if ($variations === []) {
                throw new RuntimeException('The provider returned no video.');
            }

            // Ingest every clip first, so a Mux failure never leaves half a generation behind.
            $assets = array_map(fn (array $variation) => $mux->createAsset($variation['video_url']), $variations);
        } catch (Throwable $exception) {
            $this->markAsErrored($generation, $exception);

            return;
        }

        DB::transaction(function () use ($generation, $variations, $assets, $debit) {
            foreach ($assets as $asset) {
                Video::create([
                    'dish_id' => $generation->dish_id,
                    'origin_id' => VideoOrigin::idFor('ia'),
                    'generation_id' => $generation->id,
                    'status_id' => VideoStatus::idFor('processando'),
                    'mux_asset_id' => $asset['id'] ?? null,
                ]);
            }

            $generation->update([
                'status_id' => GenerationStatus::idFor('pronto'),
                'cost_usd' => array_sum(array_map(fn (array $variation) => (float) ($variation['cost_usd'] ?? 0), $variations)),
            ]);

            $debit->handle($generation, count($assets));
        });
    }

    public function failed(?Throwable $exception): void
    {
        $generation = VideoGeneration::find($this->generationId);

        if ($generation !== null) {
            $this->markAsErrored($generation, $exception);
        }
    }

    private function markAsErrored(VideoGeneration $generation, ?Throwable $exception): void
    {
        $generation->update(['status_id' => GenerationStatus::idFor('erro')]);

        Log::warning('AI video generation failed', [
            'video_generation_id' => $generation->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
