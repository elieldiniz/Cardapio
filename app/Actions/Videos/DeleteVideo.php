<?php

namespace App\Actions\Videos;

use App\Contracts\MuxClient;
use App\Models\Video;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The dono clears a video they no longer want (replaced, refused or a
 * discarded variation). The Mux asset goes too, so it stops costing storage.
 * One still processing can't be deleted, and neither can the video on the
 * cardápio — unless the dono explicitly takes it off the air ($unpublish),
 * which leaves the dish without a video (and out of the feed).
 */
class DeleteVideo
{
    public function __construct(private readonly MuxClient $mux) {}

    public function handle(Video $video, bool $unpublish = false): void
    {
        $isLive = $video->dish()->where('active_video_id', $video->id)->exists();

        if ($isLive && ! $unpublish) {
            throw new InvalidArgumentException('Este vídeo está no cardápio. Aprove outro antes de excluí-lo.');
        }

        if ($video->status?->slug === 'processando') {
            throw new InvalidArgumentException('Espere o vídeo terminar de processar para excluí-lo.');
        }

        if ($isLive) {
            $video->dish()->update(['active_video_id' => null]);
        }

        $assetId = $video->mux_asset_id;
        $video->delete();

        if ($assetId) {
            try {
                $this->mux->deleteAsset($assetId);
            } catch (Throwable $exception) {
                Log::error('Could not delete the Mux asset of a deleted video', ['video_id' => $video->id, 'error' => $exception->getMessage()]);
            }
        }
    }
}
