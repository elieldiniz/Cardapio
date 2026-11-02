<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\TrackingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The customer's public tracking page and its polling endpoint (CT-03, RF-07, RF-07a).
 * The token is the only key; an unknown or expired token is a plain 404.
 */
class OrderTrackingController extends Controller
{
    public function show(string $token, TrackingPresenter $presenter): Response
    {
        $order = $this->find($token);
        $restaurant = $order->restaurant;
        $settings = $restaurant->orderSettings();
        $fonts = config('feed.fonts');
        $font = array_key_exists($restaurant->font ?? '', $fonts) ? $restaurant->font : config('feed.default_font');

        return response()
            ->view('order.track', [
                'tracking' => $presenter->present($order),
                'restaurant' => [
                    'name' => $restaurant->name,
                    'accent' => $restaurant->accentColor(),
                    'font' => $font,
                    'style' => $restaurant->menuStyle(),
                    'font_slug' => $fonts[$font] ?? null,
                    'url' => route('feed.show', $restaurant),
                    'whatsapp_url' => $settings->whatsapp_number
                        ? 'https://api.whatsapp.com/send?phone='.$settings->whatsapp_number.'&text='.rawurlencode('Olá! Sobre o meu pedido '.$order->code)
                        : null,
                ],
                'statusUrl' => route('order.track.status', $token),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function status(string $token, TrackingPresenter $presenter): JsonResponse
    {
        return response()
            ->json($presenter->present($this->find($token)))
            ->header('Cache-Control', 'no-store, private');
    }

    private function find(string $token): Order
    {
        $order = Order::query()->where('tracking_token', $token)->with(['restaurant', 'status'])->first();

        abort_if($order === null || $order->trackingExpired(), 404);
        abort_if($order->restaurant->isSuspended(), 404);

        return $order;
    }
}
