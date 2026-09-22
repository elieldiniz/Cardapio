<?php

namespace App\Services\Feed;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Support\Money;
use App\Support\MuxUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the public feed's data (US-1.1–US-1.4). Only dishes with an approved
 * active video, not hidden, in a visible category are feed-eligible.
 */
class FeedBuilder
{
    /**
     * Feed-eligible dishes, ordered by category then display_order.
     */
    public function eligibleDishes(Restaurant $restaurant): Builder
    {
        return Dish::query()
            ->visible()
            ->where('dishes.restaurant_id', $restaurant->id)
            ->whereNotNull('dishes.active_video_id')
            ->whereHas('activeVideo.status', fn (Builder $status) => $status->where('slug', 'aprovado'))
            ->join('categories', 'categories.id', '=', 'dishes.category_id')
            ->orderBy('categories.display_order')
            ->orderBy('categories.id')
            ->orderBy('dishes.display_order')
            ->orderBy('dishes.id')
            ->select('dishes.*')
            ->with(['activeVideo', 'status', 'badges', 'variants']);
    }

    /**
     * Visible categories that have at least one feed-eligible dish, in bar order.
     *
     * @return Collection<int, Category>
     */
    public function categories(Restaurant $restaurant): Collection
    {
        $categoryIds = $this->eligibleDishes($restaurant)->pluck('dishes.category_id')->unique();

        return $restaurant->categories()
            ->visible()
            ->whereIn('id', $categoryIds)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dishesFor(Restaurant $restaurant, Category $category): array
    {
        return $this->eligibleDishes($restaurant)
            ->where('dishes.category_id', $category->id)
            ->get()
            ->map(fn (Dish $dish) => $this->present($dish))
            ->all();
    }

    /**
     * View data for feed/show.blade.php: the first category's dishes are
     * rendered server-side so the first cover and video need no JS (US-1.1).
     *
     * @return array<string, mixed>
     */
    public function page(Restaurant $restaurant): array
    {
        $restaurant->loadMissing('plan');
        $categories = $this->categories($restaurant);
        $first = $categories->first();

        return [
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo_url' => $restaurant->logo_path ? Storage::disk('public')->url($restaurant->logo_path) : null,
                'accent' => $restaurant->accentColor(),
                'font' => $restaurant->font,
                'show_branding' => ! ($restaurant->plan?->removes_branding ?? false),
            ],
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'url' => route('feed.category', [$restaurant, $category]),
            ])->all(),
            'activeCategoryId' => $first?->id,
            'dishes' => $first ? $this->dishesFor($restaurant, $first) : [],
            'trackUrl' => route('feed.views', $restaurant),
        ];
    }

    /**
     * The FeedDish shape consumed by the Blade partial and feed.js.
     *
     * @return array<string, mixed>
     */
    public function present(Dish $dish): array
    {
        $video = $dish->activeVideo;
        $playbackId = $video?->mux_playback_id;

        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => Money::brl($dish->price),
            'short_description' => $dish->short_description,
            'description' => $dish->description,
            'badges' => $dish->badges->pluck('name')->all(),
            'sold_out' => $dish->isSoldOut(),
            'variants' => $dish->variants->map(fn ($variant) => [
                'name' => $variant->name,
                'price' => Money::brl($variant->price),
            ])->all(),
            'cover_url' => $video?->cover_path ?: ($playbackId ? MuxUrls::thumbnail($playbackId) : null),
            'video_url' => $playbackId ? MuxUrls::mp4($playbackId, '480p') : null,
            'video_url_hd' => $playbackId ? MuxUrls::mp4($playbackId, '720p') : null,
        ];
    }
}
