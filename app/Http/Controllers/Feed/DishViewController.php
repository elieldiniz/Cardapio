<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\DishView;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Batched watch-time reports from the feed (US-1.4, US-6.3). Each report is
 * folded into one dish_views row per (dish, session, day).
 */
class DishViewController extends Controller
{
    /**
     * Upper bound for a single report, so a misbehaving client can't inflate metrics.
     */
    public const MAX_SECONDS_PER_REPORT = 600;

    public function store(Request $request, Restaurant $restaurant): Response
    {
        // sendBeacon posts text/plain; accept JSON either way.
        $payload = $request->json()->all() ?: (json_decode($request->getContent(), true) ?? []);

        $data = validator($payload, [
            'session_token' => ['required', 'string', 'max:64'],
            'views' => ['required', 'array', 'max:100'],
            'views.*.dish_id' => ['required', 'integer'],
            'views.*.seconds' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $dishIds = $restaurant->dishes()
            ->whereIn('id', collect($data['views'])->pluck('dish_id'))
            ->pluck('id')
            ->flip();

        $today = now()->toDateString();

        DB::transaction(function () use ($data, $dishIds, $restaurant, $today) {
            foreach (collect($data['views'])->groupBy('dish_id') as $dishId => $reports) {
                if (! $dishIds->has($dishId)) {
                    continue;
                }

                $seconds = (int) min(self::MAX_SECONDS_PER_REPORT, round($reports->sum('seconds')));

                $view = DishView::query()
                    ->where('dish_id', $dishId)
                    ->where('session_token', $data['session_token'])
                    ->whereDate('viewed_on', $today)
                    ->first();

                if ($view === null) {
                    DishView::create([
                        'dish_id' => $dishId,
                        'restaurant_id' => $restaurant->id,
                        'session_token' => $data['session_token'],
                        'seconds_watched' => $seconds,
                        'viewed_on' => $today,
                    ]);
                } elseif ($seconds > 0) {
                    $view->increment('seconds_watched', $seconds);
                }
            }
        });

        return response()->noContent();
    }
}
