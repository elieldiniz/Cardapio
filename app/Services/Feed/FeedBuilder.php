<?php

namespace App\Services\Feed;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Support\BusinessHours;
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
            ->whereIn('dishes.category_id', $this->shownCategoryIds($restaurant))
            ->whereNotNull('dishes.active_video_id')
            ->whereHas('activeVideo.status', fn (Builder $status) => $status->where('slug', 'aprovado'))
            ->join('categories', 'categories.id', '=', 'dishes.category_id')
            ->orderBy('categories.display_order')
            ->orderBy('categories.id')
            ->orderBy('dishes.display_order')
            ->orderBy('dishes.id')
            ->select('dishes.*')
            ->with(['activeVideo', 'status', 'badges', 'variants', 'category', 'optionGroups.options', 'pizzaSizes', 'pizzaFlavors.prices', 'photos' => fn ($query) => $query->orderBy('display_order')]);
    }

    /**
     * Ids of the visible categories whose own time window (RF-02b, "cardápio do almoço")
     * allows them to show right now in the store's timezone.
     *
     * @return array<int, int>
     */
    public function shownCategoryIds(Restaurant $restaurant): array
    {
        $local = BusinessHours::localNow($restaurant);

        return $restaurant->categories()
            ->visible()
            ->get()
            ->filter(fn (Category $category) => $category->isShownAt($local))
            ->pluck('id')
            ->all();
    }

    /**
     * What the cardápio shows about the store being open (RF-02, UI-04): the state label
     * and, when automatic hours are on, the week for "Ver horários".
     *
     * @return array{open: bool, label: string, accepts_when_closed: bool, hours: ?array<int, mixed>}
     */
    public function storePresentation(Restaurant $restaurant): array
    {
        $settings = $restaurant->orderSettings();
        $state = BusinessHours::state($restaurant);
        $grid = $settings->hours_enabled ? BusinessHours::weekGrid($restaurant) : null;

        return [
            'open' => $state['open'],
            'label' => $state['label'],
            'accepts_when_closed' => (bool) $settings->accepts_when_closed,
            'hours' => $grid !== null && collect($grid)->contains(fn (array $day) => $day['shifts'] !== []) ? $grid : null,
        ];
    }

    /**
     * Whether this cardápio takes orders: the plan includes them and the store has a
     * WhatsApp number to receive them (RF-04, RF-06). Without it the feed stays view-only,
     * exactly as before.
     */
    public function ordersEnabled(Restaurant $restaurant): bool
    {
        $restaurant->loadMissing('plan');

        return $restaurant->hasFeature('orders') && filled($restaurant->orderSettings()->whatsapp_number);
    }

    /**
     * The promotional banner while it is live, in the store's own calendar day (RF-16). "Ver ofertas"
     * only appears when some product is actually on sale.
     *
     * @return array{title: string, text: ?string, image_url: ?string, has_offers: bool}|null
     */
    public function banner(Restaurant $restaurant): ?array
    {
        $banner = $restaurant->promoBanner;

        if ($banner === null || ! $banner->isLive(BusinessHours::localNow($restaurant)->toDateString())) {
            return null;
        }

        return [
            'title' => $banner->title,
            'text' => $banner->text,
            'image_url' => $banner->image_path ? Storage::disk('public')->url($banner->image_path) : null,
            'has_offers' => $this->eligibleDishes($restaurant)->get()->contains(fn (Dish $dish) => $dish->activePromoPrice() !== null),
        ];
    }

    /**
     * Everything the cart needs to run in the browser, embedded in the page as JSON
     * (RF-04, RF-06b): the orderable products with their variations and complement
     * groups, and the store rules for delivery and payment. Prices here are only for
     * showing totals — the server prices the order again on checkout (RF-05).
     *
     * @return array<string, mixed>
     */
    public function orderPayload(Restaurant $restaurant, ?Collection $dishes = null): array
    {
        $settings = $restaurant->orderSettings();
        $state = BusinessHours::state($restaurant);
        $accepting = $state['open'] || ($state['reason'] === 'hours' && $settings->accepts_when_closed);

        $catalog = [];

        foreach ($dishes ?? $this->eligibleDishes($restaurant)->get() as $dish) {
            $catalog[$dish->id] = $this->orderEntry($dish);
        }

        return [
            'enabled' => true,
            'orders_url' => route('feed.orders.store', $restaurant),
            'coupon_url' => route('feed.orders.coupon', $restaurant),
            'events_url' => route('feed.events', $restaurant),
            'whatsapp_url' => route('feed.orders.whatsapp', [$restaurant, '__TOKEN__']),
            'store_slug' => $restaurant->slug,
            'privacy_url' => route('legal.privacy', ['loja' => $restaurant->slug]),
            'accepting' => $accepting,
            'closed_label' => $state['label'],
            'off_hours' => ! $state['open'] && $accepting,
            'delivery' => [
                'mode' => $settings->delivery_mode,
                'zones' => $settings->delivery_mode === 'bairro'
                    ? $restaurant->deliveryZones()->where('is_active', true)->get()->map(fn ($zone) => ['id' => $zone->id, 'name' => $zone->name, 'fee' => $zone->fee])->all()
                    : [],
                'tiers' => $settings->delivery_mode === 'distancia' && $settings->lat !== null && $settings->lng !== null
                    ? $restaurant->distanceTiers()->get()->map(fn ($tier) => ['from' => $tier->from_km, 'to' => $tier->to_km, 'fee' => $tier->fee])->all()
                    : [],
                'store_lat' => $settings->delivery_mode === 'distancia' ? $settings->lat : null,
                'store_lng' => $settings->delivery_mode === 'distancia' ? $settings->lng : null,
                'accepts_other' => (bool) $settings->accepts_other_neighborhood,
                'free_over' => $settings->free_delivery_over,
                'min_order' => $settings->min_order_delivery,
            ],
            'pix' => filled($settings->pix_key),
            'pizza_rule' => $settings->pizza_pricing_rule === 'media' ? 'media' : 'maior',
            'dishes' => $catalog,
        ];
    }

    /**
     * One product as the cart sees it. Sold out or hidden products are not orderable.
     *
     * @return array<string, mixed>
     */
    public function orderEntry(Dish $dish): array
    {
        $sizes = $dish->pizzaSizes->where('is_available', true);

        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->isPizza() ? $dish->price : $dish->currentPrice(),
            'old_price' => $dish->activePromoPrice() !== null ? $dish->price : null,
            'kind' => $dish->kind ?? 'common',
            'sizes' => $dish->isPizza() ? $sizes->map(fn ($size) => [
                'id' => $size->id, 'name' => $size->name, 'slices' => $size->slices, 'max' => $size->max_flavors,
            ])->values()->all() : [],
            'flavors' => $dish->isPizza() ? $dish->pizzaFlavors->where('is_available', true)->map(fn ($flavor) => [
                'id' => $flavor->id,
                'name' => $flavor->name,
                'section' => $flavor->section,
                'ingredients' => $flavor->ingredients,
                'prices' => $flavor->prices->whereIn('pizza_size_id', $sizes->pluck('id'))->mapWithKeys(fn ($price) => [$price->pizza_size_id => $price->price])->all(),
            ])->values()->all() : [],
            'sale_type' => $dish->sale_type ?? 'unit',
            'icon' => $dish->category?->icon,
            'orderable' => $dish->status?->slug === 'ativo' && (! $dish->isPizza() || $sizes->isNotEmpty()),
            'variants' => $dish->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => $variant->price,
            ])->values()->all(),
            'groups' => $dish->optionGroups->where('is_active', true)->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'mode' => $group->mode,
                'max' => $group->mode === 'one' ? 1 : $group->max_choices,
                'required' => (bool) $group->is_required,
                'repeat' => (bool) $group->allow_repeat,
                'options' => $group->options->where('is_available', true)->map(fn ($option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price,
                ])->values()->all(),
            ])->values()->all(),
        ];
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
                'featured' => (bool) $dish->is_featured,
                'on_sale' => $dish->activePromoPrice() !== null,
                'thumb_url' => $this->present($dish)['thumb_url'],
            ])
            // Featured products come first on the grid (RF-16); the menu order is kept inside each group.
            ->sortBy([['featured', 'desc']])
            ->values()
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
                'style' => $restaurant->menuStyle(),
                'show_branding' => ! ($restaurant->plan?->removes_branding ?? false),
            ],
            'store' => $this->storePresentation($restaurant),
            'banner' => $this->banner($restaurant),
            'order' => $this->ordersEnabled($restaurant) ? $this->orderPayload($restaurant) : null,
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
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

        $cover = $video?->coverUrl() ?: ($playbackId ? MuxUrls::thumbnail($playbackId) : null);
        $photo = $dish->photos->first();

        return [
            'id' => $dish->id,
            'category_id' => $dish->category_id,
            'name' => $dish->name,
            'price' => Money::brl($dish->isPizza() ? $dish->price : $dish->currentPrice()).($dish->isWeighed() ? ' / kg' : ''),
            'old_price' => $dish->activePromoPrice() !== null ? Money::brl($dish->price).($dish->isWeighed() ? ' / kg' : '') : null,
            'featured' => (bool) $dish->is_featured,
            'on_sale' => $dish->activePromoPrice() !== null,
            'short_description' => $dish->short_description,
            'description' => $dish->description,
            'badges' => $dish->badges->pluck('name')->all(),
            'sold_out' => $dish->isSoldOut(),
            'variants' => $dish->variants->map(fn ($variant) => [
                'name' => $variant->name,
                'price' => Money::brl($variant->price),
            ])->all(),
            'has_choices' => $dish->variants->isNotEmpty() || $dish->optionGroups->where('is_active', true)->isNotEmpty(),
            'cover_url' => $cover,
            // The grid shows the cover the dono chose for the video; older dishes fall back to their own photo, then to a frame of the video.
            'thumb_url' => $video?->cover_photo_path
                ? Storage::disk('public')->url($video->cover_photo_path)
                : ($photo ? Storage::disk('public')->url($photo->file_path) : $cover),
            'video_url' => $playbackId ? MuxUrls::mp4($playbackId, '480p') : null,
            'video_url_hd' => $playbackId ? MuxUrls::mp4($playbackId, '720p') : null,
        ];
    }
}
