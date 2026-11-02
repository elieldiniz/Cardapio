<?php

namespace App\Actions\Menu;

use App\Contracts\MuxClient;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\DishView;
use App\Models\Restaurant;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoGenerationPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Deletes a product with everything that hangs from it: videos (and their Mux assets,
 * which stop costing storage), generations, photos, variations, badges and view counts
 * (RF-06f). Past orders keep their item snapshot; only the link to the product is cleared.
 * A video still processing blocks the deletion.
 */
class DeleteDish
{
    public function __construct(private readonly MuxClient $mux) {}

    /**
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, Dish $dish): void
    {
        abort_unless($dish->restaurant_id === $restaurant->id, 404);

        $videos = Video::query()->where('dish_id', $dish->id)->with('status')->get();

        if ($videos->contains(fn (Video $video) => $video->status?->slug === 'processando')) {
            throw ValidationException::withMessages(['dish' => 'Espere o vídeo terminar de processar para excluir o produto.']);
        }

        $assetIds = $videos->pluck('mux_asset_id')->filter()->all();
        $files = DishPhoto::query()->where('dish_id', $dish->id)->pluck('file_path')->filter()->all();
        $generationIds = VideoGeneration::query()->where('dish_id', $dish->id)->pluck('id');

        DB::transaction(function () use ($dish, $videos, $generationIds) {
            $dish->update(['active_video_id' => null]);

            DishView::query()->where('dish_id', $dish->id)->delete();
            VideoGenerationPhoto::query()->whereIn('video_generation_id', $generationIds)->delete();
            Video::query()->whereIn('id', $videos->pluck('id'))->delete();
            VideoGeneration::query()->whereIn('id', $generationIds)->delete();

            DB::table('badge_dish')->where('dish_id', $dish->id)->delete();
            $dish->variants()->delete();
            DishPhoto::query()->where('dish_id', $dish->id)->delete();
            $dish->optionGroups()->detach();
            $dish->delete();
        });

        foreach ($assetIds as $assetId) {
            try {
                $this->mux->deleteAsset($assetId);
            } catch (Throwable $exception) {
                Log::error('Could not delete the Mux asset of a deleted product', ['asset_id' => $assetId, 'error' => $exception->getMessage()]);
            }
        }

        Storage::disk('public')->delete($files);
    }
}
