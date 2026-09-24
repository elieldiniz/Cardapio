<?php

namespace App\Jobs;

use App\Actions\Ai\DebitGenerationBalance;
use App\Contracts\AsyncVideoGenerationProvider;
use App\Contracts\MuxClient;
use App\Models\GenerationStatus;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoOrigin;
use App\Models\VideoStatus;
use App\Services\Ai\VideoGenerationProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Second half of an async generation (US-3.1): checks the provider's
 * operations every few seconds — each check is its own short job, so no job
 * outlives the queue's runtime limit — then sends the finished clips to Mux
 * via direct upload, creates one `videos` row per clip and debits the balance.
 * Any error marks the generation `erro` without debiting (US-3.4).
 */
class PollDishVideoGeneration implements ShouldQueue
{
    use Queueable;

    public const INTERVAL_SECONDS = 20;

    /** Veo takes up to ~6 minutes at peak; anything slower is treated as failed. */
    public const DEADLINE_MINUTES = 30;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public readonly int $generationId) {}

    public function handle(VideoGenerationProviderResolver $resolver, MuxClient $mux, DebitGenerationBalance $debit): void
    {
        // Queues deliver at least once: a duplicate check must never ingest (or bill) twice.
        $lock = Cache::lock("video-generation:{$this->generationId}", $this->timeout + 5);

        if (! $lock->get()) {
            return;
        }

        try {
            $generation = VideoGeneration::with(['provider', 'status'])->find($this->generationId);

            if ($generation === null || $generation->status->slug !== 'gerando' || empty($generation->provider_operations)) {
                return;
            }

            try {
                $provider = $resolver->resolve($generation->provider->slug);

                if (! $provider instanceof AsyncVideoGenerationProvider) {
                    throw new RuntimeException("Provider [{$generation->provider->slug}] is not asynchronous.");
                }

                $clips = $provider->poll($generation->provider_operations);
            } catch (Throwable $exception) {
                $this->markAsErrored($generation, $exception);

                return;
            }

            if ($clips === null) {
                if ($generation->created_at->lt(now()->subMinutes(self::DEADLINE_MINUTES))) {
                    $this->markAsErrored($generation, new RuntimeException('The provider did not finish in time.'));

                    return;
                }

                $pollAgain = true;

                return;
            }

            $this->ingest($generation, $clips, $mux, $debit);
        } finally {
            $lock->release();

            // Scheduled after the lock is released, so the next check can take it.
            if ($pollAgain ?? false) {
                self::dispatch($this->generationId)->delay(now()->addSeconds(self::INTERVAL_SECONDS));
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $generation = VideoGeneration::with('status')->find($this->generationId);

        if ($generation !== null && $generation->status?->slug === 'gerando') {
            $this->markAsErrored($generation, $exception);
        }
    }

    /**
     * @param  array<int, array{video_file: string, cost_usd?: float}>  $clips
     */
    private function ingest(VideoGeneration $generation, array $clips, MuxClient $mux, DebitGenerationBalance $debit): void
    {
        /** @var Collection<int, Video> $videos */
        $videos = DB::transaction(fn () => collect($clips)->map(fn () => Video::create([
            'dish_id' => $generation->dish_id,
            'origin_id' => VideoOrigin::idFor('ia'),
            'generation_id' => $generation->id,
            'status_id' => VideoStatus::idFor('processando'),
        ])));

        try {
            // The Mux webhook matches each asset back to its row through the passthrough.
            foreach ($videos as $index => $video) {
                $upload = $mux->createDirectUpload([
                    'cors_origin' => config('app.url'),
                    'new_asset_settings' => ['passthrough' => "video:{$video->id}"],
                ]);

                Http::timeout(120)
                    ->withBody(file_get_contents($clips[$index]['video_file']), 'video/mp4')
                    ->put($upload['url'])
                    ->throw();
            }
        } catch (Throwable $exception) {
            Video::query()->whereKey($videos->pluck('id'))->delete();
            $this->markAsErrored($generation, $exception);

            return;
        } finally {
            foreach ($clips as $clip) {
                @unlink($clip['video_file']);
            }
        }

        DB::transaction(function () use ($generation, $clips, $videos, $debit) {
            $generation->update([
                'status_id' => GenerationStatus::idFor('pronto'),
                'cost_usd' => array_sum(array_map(fn (array $clip) => (float) ($clip['cost_usd'] ?? 0), $clips)),
            ]);

            $debit->handle($generation, $videos->count());
        });
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
