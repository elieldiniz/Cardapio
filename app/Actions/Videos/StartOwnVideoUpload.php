<?php

namespace App\Actions\Videos;

use App\Contracts\MuxClient;
use App\Exceptions\OwnVideoRejectedException;
use App\Models\Dish;
use App\Models\Video;
use App\Models\VideoOrigin;
use App\Models\VideoStatus;

/**
 * Own-video path (US-4.1, US-4.2): validates the file's format, creates the
 * `videos` row and a Mux direct upload the browser sends the file to — the
 * file never passes through the app server, the AI, or the generation balance.
 */
class StartOwnVideoUpload
{
    public const MIN_SECONDS = 5;

    public const MAX_SECONDS = 15;

    /**
     * Reason a duration is refused, or null when it is within 5–15s.
     */
    public static function durationProblem(float $seconds): ?string
    {
        $rounded = (int) round($seconds);

        if ($rounded < self::MIN_SECONDS) {
            return "O vídeo tem {$rounded}s — o mínimo é ".self::MIN_SECONDS.'s. Envie um vídeo de 5 a 15 segundos.';
        }

        if ($rounded > self::MAX_SECONDS) {
            return "O vídeo tem {$rounded}s — o máximo é ".self::MAX_SECONDS.'s. Corte o vídeo para 5 a 15 segundos e envie de novo.';
        }

        return null;
    }

    public static function isVertical(int $width, int $height): bool
    {
        return $height > $width;
    }

    /**
     * @return array{video: Video, upload_url: string, warnings: array<int, string>}
     */
    public function handle(Dish $dish, float $durationSeconds, int $width, int $height): array
    {
        if ($problem = static::durationProblem($durationSeconds)) {
            throw new OwnVideoRejectedException($problem);
        }

        $warnings = static::isVertical($width, $height)
            ? []
            : ['Este vídeo não está na vertical (9:16). No celular ele vai aparecer cortado nas laterais — confira antes de aprovar.'];

        $video = Video::create([
            'dish_id' => $dish->id,
            'origin_id' => VideoOrigin::idFor('upload'),
            'generation_id' => null,
            'status_id' => VideoStatus::idFor('processando'),
            'duration_seconds' => (int) round($durationSeconds),
        ]);

        $upload = app(MuxClient::class)->createDirectUpload([
            'cors_origin' => config('app.url'),
            'new_asset_settings' => ['passthrough' => "video:{$video->id}"],
        ]);

        return ['video' => $video, 'upload_url' => $upload['url'], 'warnings' => $warnings];
    }
}
