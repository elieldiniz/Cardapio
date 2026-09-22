<?php

namespace App\Actions\Admin;

use App\Contracts\MuxClient;
use App\Models\AdminLog;
use App\Models\DishPhoto;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Content moderation (US-7.5). Removed content leaves the public feed at
 * once and every removal is audited.
 */
class RemoveContent
{
    public function __construct(private readonly MuxClient $mux) {}

    /**
     * The video is rejected and, if it was the dish's active video, the
     * pointer is cleared so the dish drops out of the feed. The Mux asset is
     * deleted too, so the content is no longer reachable by its public URL.
     */
    public function video(User $admin, Video $video, ?string $reason = null): void
    {
        DB::transaction(function () use ($admin, $video, $reason) {
            $video->dish()->where('active_video_id', $video->id)->update(['active_video_id' => null]);
            $video->update(['status_id' => VideoStatus::idFor('rejeitado')]);

            AdminLog::record($admin, 'conteudo_removido', "video:{$video->id}", array_filter([
                'dish_id' => $video->dish_id,
                'restaurant_id' => $video->dish?->restaurant_id,
                'reason' => $reason,
            ]));
        });

        if ($video->mux_asset_id) {
            try {
                $this->mux->deleteAsset($video->mux_asset_id);
            } catch (Throwable $exception) {
                Log::error('Moderation could not delete the Mux asset', ['video_id' => $video->id, 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * The photo file is deleted and the photo removed from the dish (and from
     * the generations that used it as a source).
     */
    public function photo(User $admin, DishPhoto $photo, ?string $reason = null): void
    {
        DB::transaction(function () use ($admin, $photo, $reason) {
            AdminLog::record($admin, 'conteudo_removido', "dish_photo:{$photo->id}", array_filter([
                'dish_id' => $photo->dish_id,
                'restaurant_id' => $photo->dish?->restaurant_id,
                'file_path' => $photo->file_path,
                'reason' => $reason,
            ]));

            $photo->videoGenerationPhotos()->delete();
            $photo->delete();
        });

        Storage::disk('public')->delete($photo->file_path);
    }
}
