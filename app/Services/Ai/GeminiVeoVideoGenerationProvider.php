<?php

namespace App\Services\Ai;

use App\Contracts\AsyncVideoGenerationProvider;
use App\Models\VideoGeneration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Google Gemini API — Veo 3.1 image-to-video (ai.google.dev/gemini-api/docs/veo).
 *
 * Veo returns one clip per request, so each variation is its own long-running
 * operation. The first photo is the opening frame; the finished MP4 is only
 * reachable with the API key, so poll() downloads it to a local file for the
 * job to hand to Mux (a URL with the key in it must never reach Mux).
 */
class GeminiVeoVideoGenerationProvider implements AsyncVideoGenerationProvider
{
    /** Clip lengths Veo accepts. */
    public const DURATIONS = [4, 6, 8];

    public function start(array $photos, array $preset): array
    {
        if ($photos === []) {
            throw new RuntimeException('Veo needs at least one photo.');
        }

        $model = (string) config('services.gemini.video_model');
        $resolution = (string) config('services.gemini.video_resolution');
        $seconds = static::durationFor((int) $preset['duration_seconds']);
        [$mimeType, $bytes] = $this->photo($photos[0]);

        $body = [
            'instances' => [[
                'prompt' => static::promptFor($preset, count($photos)),
                // predictLongRunning takes the Vertex-style image, not generateContent's inlineData.
                'image' => ['bytesBase64Encoded' => base64_encode($bytes), 'mimeType' => $mimeType],
            ]],
            'parameters' => [
                'aspectRatio' => '9:16',
                'durationSeconds' => $seconds,
                'resolution' => $resolution,
            ],
        ];

        $operations = [];

        foreach (range(1, max(1, (int) $preset['variations'])) as $ignored) {
            try {
                $name = $this->client()->timeout(60)
                    ->post("models/{$model}:predictLongRunning", $body)
                    ->throw()
                    ->json('name');

                if (! is_string($name) || $name === '') {
                    throw new RuntimeException('Veo did not return an operation.');
                }
            } catch (Throwable $exception) {
                // Operations already submitted keep running (and are billed) at Google:
                // deliver fewer variations rather than throw paid clips away.
                if ($operations !== []) {
                    Log::warning('Veo accepted only part of the variations', ['started' => count($operations), 'error' => $exception->getMessage()]);

                    break;
                }

                throw $exception;
            }

            $operations[] = json_encode(compact('name', 'model', 'resolution', 'seconds'));
        }

        return $operations;
    }

    public function poll(array $operations): ?array
    {
        $finished = [];

        // Nothing is downloaded until every variation is done, so a slow one never causes re-downloads.
        $clips = [];

        // A hiccup while checking or downloading must not throw away clips Google already
        // billed: transient errors just mean "check again" (the job's deadline still applies).
        try {
            foreach ($operations as $handle) {
                $operation = json_decode($handle, true);
                $state = $this->client()->timeout(30)->get($operation['name'])->throw()->json();

                if (! ($state['done'] ?? false)) {
                    return null;
                }

                $finished[] = [$operation, $state];
            }

            $problems = [];

            foreach ($finished as [$operation, $state]) {
                $uri = $state['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;

                if (isset($state['error']) || ! is_string($uri)) {
                    // Safety-filtered or failed clips are not charged by Google.
                    $problems[] = $state['error']['message']
                        ?? json_encode($state['response']['generateVideoResponse']['raiMediaFilteredReasons'] ?? 'no video returned');

                    continue;
                }

                $clips[] = [
                    'video_file' => $this->download($uri),
                    'cost_usd' => round($operation['seconds'] * static::pricePerSecond($operation['model'], $operation['resolution']), 4),
                ];
            }
        } catch (ConnectionException|RequestException $exception) {
            if (! static::isTransient($exception)) {
                throw $exception;
            }

            foreach ($clips as $clip) {
                @unlink($clip['video_file']);
            }

            return null;
        }

        if ($clips === []) {
            throw new RuntimeException('Veo produced no video: '.implode('; ', $problems));
        }

        return $clips;
    }

    /**
     * Network failures, rate limits and Google-side errors are worth retrying; a 4xx is not.
     */
    public static function isTransient(ConnectionException|RequestException $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $status = $exception->response->status();

        return $status === 429 || $status >= 500;
    }

    /**
     * The nearest clip length Veo accepts for the preset's 5–10s.
     */
    public static function durationFor(int $presetSeconds): int
    {
        foreach (self::DURATIONS as $seconds) {
            if ($presetSeconds <= $seconds) {
                return $seconds;
            }
        }

        return self::DURATIONS[array_key_last(self::DURATIONS)];
    }

    public static function pricePerSecond(string $model, string $resolution): float
    {
        // Model names contain dots, so the table is indexed directly rather than via dot notation.
        return (float) (config('ai.gemini_price_per_second')[$model][$resolution] ?? 0);
    }

    /**
     * The fixed preset, plus the turn limit: a single photo never shows the back of the dish.
     *
     * @param  array<string, mixed>  $preset
     */
    public static function promptFor(array $preset, int $photoCount): string
    {
        $turn = ($preset['turn'] ?? null) === VideoGeneration::TURN_WIDE
            ? 'The camera may orbit further around the dish.'
            : 'Keep the rotation subtle (30 to 45 degrees at most) and never reveal the back of the dish.';

        return trim(implode(' ', array_filter([
            (string) ($preset['prompt'] ?? ''),
            'Camera movement: '.($preset['camera_movement'] ?? 'slow orbit with a gentle push-in').'.',
            $turn,
            'Keep the dish exactly as in the photo: same ingredients, plating and colors. Vertical 9:16 food video, no people, no hands, no text, no logos.',
        ])));
    }

    /**
     * Photo bytes: straight from the app's public disk when the URL is ours
     * (the worker may not be able to reach its own URL), otherwise over HTTP.
     *
     * @return array{0: string, 1: string}
     */
    private function photo(string $url): array
    {
        $disk = Storage::disk('public');
        $base = rtrim($disk->url(''), '/').'/';

        $bytes = str_starts_with($url, $base)
            ? $disk->get(rawurldecode(Str::after($url, $base)))
            : Http::timeout(30)->get($url)->throw()->body();

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException("Could not read photo [{$url}].");
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';

        return [$mimeType, $bytes];
    }

    private function download(string $uri): string
    {
        $path = sys_get_temp_dir().'/veo-'.Str::uuid().'.mp4';

        Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->timeout(120)
            ->sink($path)
            ->get($uri)
            ->throw();

        return $path;
    }

    private function client(): PendingRequest
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not set.');
        }

        return Http::baseUrl((string) config('services.gemini.base_url'))
            ->withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()
            ->asJson();
    }
}
