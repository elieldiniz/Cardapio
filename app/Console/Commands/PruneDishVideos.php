<?php

namespace App\Console\Commands;

use App\Actions\Videos\DeleteVideo;
use App\Models\Dish;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Notifications\UnreviewedVideosExpiring;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Daily cleanup of videos nobody will watch, so they stop piling up on the
 * dono's screen and in Mux storage. The video on the cardápio is never touched.
 *
 *  - Variations not picked: deleted 7 days after another one of the same round was approved.
 *  - Replaced videos: only the 2 most recent per dish are kept for "Usar de novo".
 *  - Rounds never reviewed: after 30 days (with a panel notice 3 days before) their variations go.
 */
class PruneDishVideos extends Command
{
    protected $signature = 'videos:prune';

    protected $description = 'Delete unchosen, old replaced and never-reviewed dish videos (and their Mux assets)';

    public const UNCHOSEN_GRACE_DAYS = 7;

    public const KEEP_REPLACED = 2;

    public const UNREVIEWED_DAYS = 30;

    public const WARN_DAYS_BEFORE = 3;

    public function handle(DeleteVideo $deleteVideo): int
    {
        $deleted = $this->delete($this->unchosenVariations(), $deleteVideo)
            + $this->delete($this->oldReplaced(), $deleteVideo)
            + $this->delete($this->expiredUnreviewed(), $deleteVideo);

        $warned = $this->warnUnreviewed();

        $this->info("Vídeos excluídos: {$deleted}. Avisos enviados: {$warned}.");

        return self::SUCCESS;
    }

    /**
     * Awaiting variations of a round where another variation was approved a week ago or more.
     *
     * @return Collection<int, Video>
     */
    private function unchosenVariations(): Collection
    {
        return $this->awaiting()
            ->whereNotNull('generation_id')
            ->whereExists(fn ($sibling) => $sibling->from('videos as siblings')
                ->whereColumn('siblings.generation_id', 'videos.generation_id')
                ->whereColumn('siblings.id', '!=', 'videos.id')
                ->where('siblings.approved_at', '<=', now()->subDays(self::UNCHOSEN_GRACE_DAYS)))
            ->get();
    }

    /**
     * Replaced videos beyond the most recent ones of each dish.
     *
     * @return Collection<int, Video>
     */
    private function oldReplaced(): Collection
    {
        return Video::query()
            ->with(['status', 'dish'])
            ->whereHas('status', fn ($status) => $status->where('slug', 'rejeitado'))
            ->whereNotNull('mux_playback_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (Video $video) => $video->dish?->active_video_id === $video->id)
            ->groupBy('dish_id')
            ->flatMap(fn (Collection $videos) => $videos->slice(self::KEEP_REPLACED));
    }

    /**
     * Variations of rounds never reviewed, once the dono has had the notice for WARN_DAYS_BEFORE days.
     *
     * @return Collection<int, Video>
     */
    private function expiredUnreviewed(): Collection
    {
        $generationIds = $this->unreviewedRounds()
            ->where('expiry_warned_at', '<=', now()->subDays(self::WARN_DAYS_BEFORE))
            ->pluck('id');

        return $this->awaiting()->whereIn('generation_id', $generationIds)->get();
    }

    /**
     * Sends the panel notice for rounds reaching the end of their review window.
     */
    private function warnUnreviewed(): int
    {
        $rounds = $this->unreviewedRounds()
            ->whereNull('expiry_warned_at')
            ->where('created_at', '<=', now()->subDays(self::UNREVIEWED_DAYS - self::WARN_DAYS_BEFORE))
            ->whereHas('videos', fn ($videos) => $videos->whereHas('status', fn ($status) => $status->where('slug', 'aguardando_aprovacao')))
            ->with('dish.restaurant')
            ->get();

        foreach ($rounds as $round) {
            $owners = $round->dish->restaurant->users()->whereHas('role', fn ($role) => $role->where('slug', 'dono'))->get();

            Notification::send($owners, new UnreviewedVideosExpiring($round->dish, self::WARN_DAYS_BEFORE));
            $round->update(['expiry_warned_at' => now()]);
        }

        return $rounds->count();
    }

    /**
     * Finished rounds where no variation was ever approved.
     *
     * @return Builder<VideoGeneration>
     */
    private function unreviewedRounds(): Builder
    {
        return VideoGeneration::query()
            ->whereHas('status', fn ($status) => $status->where('slug', 'pronto'))
            ->whereDoesntHave('videos', fn ($videos) => $videos->whereNotNull('approved_at'));
    }

    /**
     * @return Builder<Video>
     */
    private function awaiting(): Builder
    {
        return Video::query()
            ->with('status')
            ->whereHas('status', fn ($status) => $status->where('slug', 'aguardando_aprovacao'))
            ->whereNotIn('id', Dish::query()->whereNotNull('active_video_id')->select('active_video_id'));
    }

    /**
     * @param  Collection<int, Video>  $videos
     */
    private function delete(Collection $videos, DeleteVideo $deleteVideo): int
    {
        $count = 0;

        foreach ($videos as $video) {
            try {
                $deleteVideo->handle($video);
                $count++;
            } catch (InvalidArgumentException) {
                // Became active or is processing again since the query ran; keep it.
            }
        }

        return $count;
    }
}
