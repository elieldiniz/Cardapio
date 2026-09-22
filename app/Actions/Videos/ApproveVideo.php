<?php

namespace App\Actions\Videos;

use App\Models\Dish;
use App\Models\Video;
use App\Models\VideoStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The dono's explicit approval (US-3.3): the chosen video becomes the dish's
 * single active video; any previously approved video is demoted, so a dish
 * never has two approved videos at once.
 */
class ApproveVideo
{
    public function handle(Video $video): Dish
    {
        if ($video->status?->slug !== 'aguardando_aprovacao') {
            throw new InvalidArgumentException('Só é possível aprovar um vídeo pronto e aguardando aprovação.');
        }

        return DB::transaction(function () use ($video) {
            $dish = Dish::query()->lockForUpdate()->findOrFail($video->dish_id);
            $approvedId = VideoStatus::idFor('aprovado');

            Video::query()
                ->where('dish_id', $dish->id)
                ->where('status_id', $approvedId)
                ->whereKeyNot($video->id)
                ->update(['status_id' => VideoStatus::idFor('rejeitado')]);

            $video->update(['status_id' => $approvedId]);
            $dish->update(['active_video_id' => $video->id]);

            return $dish;
        });
    }
}
