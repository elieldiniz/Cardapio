<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Feed\FeedBuilder;
use App\Support\FeedSample;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The real feed shell, rendered for the appearance editor's live preview
 * (US-6.1). Unsaved values arrive from the panel via postMessage.
 */
class AppearancePreviewController extends Controller
{
    public function __invoke(Request $request, FeedBuilder $feed): View
    {
        $page = $feed->page($request->user()->restaurant);

        // Before the first dish is published, preview with sample dishes.
        if ($page['dishes'] === []) {
            $sample = FeedSample::page();
            $page['categories'] = array_map(fn (array $category) => ['url' => null] + $category, $sample['categories']);
            $page['activeCategoryId'] = $sample['activeCategoryId'];
            $page['dishes'] = $sample['dishes'];
            $page['grid'] = $sample['grid'];
        }

        return view('feed.show', ['preview' => true, 'trackUrl' => null] + $page);
    }
}
