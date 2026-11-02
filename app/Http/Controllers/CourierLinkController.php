<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The courier's own page (CT-05, RF-22): the deliveries that left with them today, with the
 * first name and the address needed to deliver — never the WhatsApp or the surname — and a
 * button to mark each one delivered. Anything wrong with the token is a plain 404.
 */
class CourierLinkController extends Controller
{
    public function show(string $token): Response
    {
        $courier = $this->courier($token);
        $timezone = $courier->restaurant->orderSettings()->timezone;
        $startOfDay = now($timezone)->startOfDay()->utc();

        $orders = Order::query()
            ->where('courier_id', $courier->id)
            ->with(['status', 'paymentMethod', 'payments.method'])
            ->where(fn ($query) => $query
                ->whereHas('status', fn ($status) => $status->where('slug', 'em_rota'))
                ->orWhere(fn ($done) => $done->whereNotNull('delivered_at')->where('delivered_at', '>=', $startOfDay)))
            ->whereHas('status', fn ($status) => $status->whereNotIn('slug', ['cancelado', 'recusado']))
            ->orderBy('dispatched_at')->orderBy('id')
            ->get();

        return response()->view('courier.show', ['courier' => $courier, 'orders' => $orders, 'token' => $token])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function deliver(string $token, int $order): RedirectResponse
    {
        $courier = $this->courier($token);

        $found = Order::query()->where('courier_id', $courier->id)->whereKey($order)->firstOrFail();

        if ($found->delivered_at === null) {
            $found->update(['delivered_at' => now()]);
        }

        return redirect()->route('courier.show', $token);
    }

    private function courier(string $token): Courier
    {
        $courier = Courier::query()->with('restaurant')->where('token', $token)->first();

        abort_unless($courier && $courier->linkIsValid(), 404);

        return $courier;
    }
}
