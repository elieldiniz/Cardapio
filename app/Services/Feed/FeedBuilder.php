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
            ->with(['activeVideo', 'status', 'badges', 'variants', 'photos' => fn ($query) => $query->orderBy('display_order')]);
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
     * Every feed-eligible dish, in menu order, for the opening grid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function gridFor(Restaurant $restaurant): array
    {
        return $this->eligibleDishes($restaurant)
            ->get()
            ->map(fn (Dish $dish) => [
                'id' => $dish->id,
                'category_id' => $dish->category_id,
                'name' => $dish->name,
                'sold_out' => $dish->isSoldOut(),
                'thumb_url' => $this->present($dish)['thumb_url'],
            ])
            ->all();
    }

    /**
     * View data for feed/show.blade.php. The page opens on the grid of every
     * dish (mockup); the first category's dishes are also rendered so opening
     * one of them needs no request.
     *
     * A shared link (`?prato={id}`) opens straight on that dish: its category is
     * pre-rendered instead of the first one, and the link preview shows the dish.
     *
     * @return array<string, mixed>
     */
    public function page(Restaurant $restaurant, ?int $sharedDishId = null): array
    {
        $restaurant->loadMissing('plan');
        $categories = $this->categories($restaurant);
        $shared = $sharedDishId ? $this->eligibleDishes($restaurant)->where('dishes.id', $sharedDishId)->first() : null;
        $first = $shared ? $categories->firstWhere('id', $shared->category_id) : $categories->first();
        $sharedDish = $shared ? $this->present($shared) : null;
        $coverUrl = $restaurant->cover_path ? Storage::disk('public')->url($restaurant->cover_path) : null;

        return [
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'url' => $restaurant->feedUrl(),
                'description' => $restaurant->description,
                'logo_url' => $restaurant->logo_path ? Storage::disk('public')->url($restaurant->logo_path) : null,
                'cover_url' => $coverUrl,
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
            'grid' => $this->gridFor($restaurant),
            'trackUrl' => route('feed.views', $restaurant),
            'openDishId' => $sharedDish['id'] ?? null,
            'meta' => $sharedDish
                ? [
                    'title' => "{$sharedDish['name']} · {$restaurant->name}",
                    'description' => $sharedDish['short_description'] ?: "{$sharedDish['name']} por {$sharedDish['price']} no cardápio do {$restaurant->name}.",
                    'image' => $sharedDish['cover_url'] ?? $sharedDish['thumb_url'],
                    'url' => $restaurant->feedUrl().'?prato='.$sharedDish['id'],
                ]
                : [
                    'title' => "{$restaurant->name} · Cardápio",
                    'description' => $restaurant->description ?: "Veja o cardápio em vídeo do {$restaurant->name}.",
                    'image' => $coverUrl,
                    'url' => $restaurant->feedUrl(),
                ],
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

        $cover = $video?->cover_path ?: ($playbackId ? MuxUrls::thumbnail($playbackId) : null);
        $photo = $dish->photos->first();

        return [
            'id' => $dish->id,
            'category_id' => $dish->category_id,
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
            'cover_url' => $cover,
            // The grid shows the dish's own photo, falling back to the video cover.
            'thumb_url' => $photo ? Storage::disk('public')->url($photo->file_path) : $cover,
            'video_url' => $playbackId ? MuxUrls::mp4($playbackId, '480p') : null,
            'video_url_hd' => $playbackId ? MuxUrls::mp4($playbackId, '720p') : null,
        ];
    }
}
