<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\FunnelEvent;
use App\Models\Restaurant;
use App\Support\BusinessHours;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The public funnel endpoint (CT-04): the cardápio reports "visited", "added to the cart" and
 * "started checkout". Always 204; a repeated step of the same session and day is ignored, so
 * the numbers are not inflated (RF-21, RNF-05).
 */
class FunnelEventController extends Controller
{
    public function store(Request $request, Restaurant $restaurant): Response
    {
        if ($restaurant->isSuspended()) {
            return response()->noContent(403);
        }

        // sendBeacon posts text/plain; accept JSON either way.
        $payload = $request->json()->all() ?: (json_decode($request->getContent(), true) ?? []);

        $data = validator($payload, [
            'session_token' => ['required', 'string', 'min:8', 'max:64'],
            'event' => ['required', 'string', 'in:'.implode(',', FunnelEvent::EVENTS)],
            'dish_id' => ['nullable', 'integer'],
        ])->validate();

        $dishId = 0;

        if ($data['event'] === 'add' && ! empty($data['dish_id'])) {
            $dishId = $restaurant->dishes()->whereKey($data['dish_id'])->value('id') ?? 0;
        }

        $now = BusinessHours::localNow($restaurant);

        DB::table('funnel_events')->insertOrIgnore([
            'restaurant_id' => $restaurant->id,
            'session_token' => $data['session_token'],
            'event' => $data['event'],
            'dish_id' => $dishId,
            'occurred_on' => $now->toDateString(),
            'hour' => (int) $now->format('G'),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
