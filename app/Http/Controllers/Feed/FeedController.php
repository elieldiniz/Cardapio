<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Restaurant;
use App\Services\Feed\FeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The public, no-login feed the QR Code points to (US-1.1, US-1.3).
 */
class FeedController extends Controller
{
    public function show(Restaurant $restaurant, FeedBuilder $feed): Response
    {
        if ($restaurant->isSuspended()) {
            return response()->view('feed.unavailable', ['restaurant' => $restaurant], 403);
        }

        // Price/status edits must show on the very next visit (US-2.3): never cache the HTML.
        return response()
            ->view('feed.show', $feed->page($restaurant))
            ->header('Cache-Control', 'no-cache, private');
    }

    public function category(Restaurant $restaurant, Category $category, FeedBuilder $feed): JsonResponse
    {
        abort_if($restaurant->isSuspended(), 403, 'Cardápio indisponível.');
        abort_unless($category->restaurant_id === $restaurant->id && $category->is_visible, 404);

        return response()
            ->json(['category_id' => $category->id, 'dishes' => $feed->dishesFor($restaurant, $category)])
            ->header('Cache-Control', 'no-cache, private');
    }
}
