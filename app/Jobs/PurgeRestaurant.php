<?php

namespace App\Jobs;

use App\Contracts\MuxClient;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\DishVariant;
use App\Models\DishView;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\Restaurant;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoGenerationPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Erases a trashed restaurant for good: its Mux videos, stored files and
 * every database row that belongs to it.
 */
class PurgeRestaurant implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $restaurantId) {}

    public function handle(MuxClient $mux): void
    {
        $restaurant = Restaurant::onlyTrashed()->find($this->restaurantId);

        if ($restaurant === null) {
            return;
        }

        $dishIds = Dish::query()->where('restaurant_id', $restaurant->id)->pluck('id');
        $generationIds = VideoGeneration::query()->whereIn('dish_id', $dishIds)->pluck('id');
        $videos = Video::query()->whereIn('dish_id', $dishIds)->get();

        foreach ($videos->pluck('mux_asset_id')->filter() as $assetId) {
            try {
                $mux->deleteAsset($assetId);
            } catch (Throwable $exception) {
                Log::error('Purge could not delete a Mux asset', ['asset_id' => $assetId, 'error' => $exception->getMessage()]);
            }
        }

        $files = DishPhoto::query()->whereIn('dish_id', $dishIds)->pluck('file_path')
            ->push($restaurant->logo_path, $restaurant->cover_path)
            ->filter()
            ->all();

        DB::transaction(function () use ($restaurant, $dishIds, $generationIds, $videos) {
            Dish::query()->whereIn('id', $dishIds)->update(['active_video_id' => null]);

            DishView::query()->where('restaurant_id', $restaurant->id)->delete();
            GenerationLedger::query()->where('restaurant_id', $restaurant->id)->delete();
            GenerationBalance::query()->where('restaurant_id', $restaurant->id)->delete();

            VideoGenerationPhoto::query()->whereIn('video_generation_id', $generationIds)->delete();
            Video::query()->whereIn('id', $videos->pluck('id'))->delete();
            VideoGeneration::query()->whereIn('id', $generationIds)->delete();

            DB::table('badge_dish')->whereIn('dish_id', $dishIds)->delete();
            DishVariant::query()->whereIn('dish_id', $dishIds)->delete();
            DishPhoto::query()->whereIn('dish_id', $dishIds)->delete();
            Dish::query()->whereIn('id', $dishIds)->delete();
            Category::query()->where('restaurant_id', $restaurant->id)->delete();

            $subscriptionIds = $restaurant->subscriptions()->pluck('id');
            SubscriptionItem::query()->whereIn('subscription_id', $subscriptionIds)->delete();
            $restaurant->subscriptions()->delete();

            $users = User::withTrashed()->where('restaurant_id', $restaurant->id)->get();

            DatabaseNotification::query()
                ->where('notifiable_type', (new User)->getMorphClass())
                ->whereIn('notifiable_id', $users->pluck('id'))
                ->delete();
            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $users->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
            $users->each(fn (User $user) => $user->forceDelete());

            $restaurant->forceDelete();
        });

        Storage::disk('public')->delete($files);
    }
}
