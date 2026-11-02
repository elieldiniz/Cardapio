<?php

namespace App\Services\Ai;

use App\Contracts\AsyncVideoGenerationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Photo → vertical video with Google's Gemini Omni Flash (`POST /interactions`), which replaced the
 * Veo 3.1 preview models that Google shut down on 22 Oct 2026.
 *
 * Omni answers in one call (no operation to wait for), so start() does the whole generation and parks
 * each finished clip on the public disk — never in a local temp file, because poll() may run on another
 * worker of a multi-server deploy. poll() then hands the clips over as local files and cleans the disk.
 */
class GeminiOmniVideoGenerationProvider implements AsyncVideoGenerationProvider
{
    public const TMP_DIRECTORY = 'ai-tmp';

    public function start(array $photos, array $preset): array
    {
        if ($photos === []) {
            throw new RuntimeException('Gemini needs at least one photo.');
        }

        $model = (string) config('services.gemini.omni_model');
        $resolution = (string) config('services.gemini.video_resolution');
        [$mimeType, $bytes] = $this->photo($photos[0]);

        $body = [
            'model' => $model,
            'input' => [
                ['type' => 'image', 'data' => base64_encode($bytes), 'mime_type' => $mimeType],
                ['type' => 'text', 'text' => static::promptFor($preset, count($photos))],
            ],
            'response_format' => ['type' => 'video', 'aspect_ratio' => '9:16', 'resolution' => $resolution],
        ];

        $operations = [];

        foreach (range(1, max(1, (int) $preset['variations'])) as $ignored) {
            try {
                $response = $this->client()->timeout(240)->post('interactions', $body)->throw()->json();
                $video = static::videoFrom($response);

                if ($video === null) {
                    throw new RuntimeException('Gemini produced no video: '.static::problemFrom($response));
                }

                $path = self::TMP_DIRECTORY.'/'.Str::uuid().'.mp4';
                Storage::disk('public')->put($path, $video);
            } catch (Throwable $exception) {
                // A clip already generated is paid for: deliver fewer variations rather than throw it away.
                if ($operations !== []) {
                    Log::warning('Gemini accepted only part of the variations', ['started' => count($operations), 'error' => $exception->getMessage()]);

                    break;
                }

                throw $exception;
            }

            $operations[] = json_encode(['path' => $path, 'model' => $model, 'resolution' => $resolution, 'cost_usd' => static::costFrom($response)]);
        }

        return $operations;
    }

    public function poll(array $operations): ?array
    {
        $clips = [];
        $disk = Storage::disk('public');

        foreach ($operations as $handle) {
            $operation = json_decode($handle, true);

            if (! is_array($operation) || ! isset($operation['path']) || ! $disk->exists($operation['path'])) {
                continue;
            }

            $local = sys_get_temp_dir().'/omni-'.Str::uuid().'.mp4';
            file_put_contents($local, $disk->get($operation['path']));
            $disk->delete($operation['path']);

            $clips[] = ['video_file' => $local, 'cost_usd' => (float) ($operation['cost_usd'] ?? 0)];
        }

        if ($clips === []) {
            throw new RuntimeException('Gemini produced no video: the generated clip was not found.');
        }

        return $clips;
    }

    /**
     * The video bytes of an `interactions` response, or null when it did not return one.
     *
     * @param  array<string, mixed>|null  $response
     */
    public static function videoFrom(?array $response): ?string
    {
        foreach ((array) ($response['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ((array) ($step['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'video' && is_string($content['data'] ?? null) && $content['data'] !== '') {
                    $bytes = base64_decode($content['data'], true);

                    return $bytes === false || $bytes === '' ? null : $bytes;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public static function problemFrom(?array $response): string
    {
        return (string) ($response['error']['message'] ?? $response['status'] ?? 'no video returned');
    }

    /**
     * What the clip cost, from the tokens Google reports (video output + image/text input).
     *
     * @param  array<string, mixed>|null  $response
     */
    public static function costFrom(?array $response): float
    {
        $usage = (array) ($response['usage'] ?? []);
        $videoOutput = collect($usage['output_tokens_by_modality'] ?? [])->where('modality', 'video')->sum('tokens');
        $input = (int) ($usage['total_input_tokens'] ?? 0);
        $price = (array) config('ai.gemini_omni_price_per_million_tokens');

        return round(($videoOutput * (float) ($price['video_output'] ?? 0) + $input * (float) ($price['input'] ?? 0)) / 1_000_000, 4);
    }

    /**
     * The fixed preset, plus the turn limit and the clip length asked for in words (Omni has no duration setting).
     *
     * @param  array<string, mixed>  $preset
     */
    public static function promptFor(array $preset, int $photoCount): string
    {
        $seconds = (int) ($preset['duration_seconds'] ?? 0);

        return trim(GeminiVeoVideoGenerationProvider::promptFor($preset, $photoCount).($seconds > 0 ? " The clip lasts about {$seconds} seconds." : ''));
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
