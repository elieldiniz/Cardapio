<?php

namespace App\Http\Controllers\Feed;

use App\Actions\Orders\ApplyPromoCode;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RecordWhatsAppEvent;
use App\Exceptions\OrderRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\PlaceOrderRequest;
use App\Models\Restaurant;
use App\Services\Orders\WhatsAppMessageBuilder;
use App\Support\PixPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public checkout of the cardápio (CT-01). Thin: the rules are in PlaceOrder and
 * the pricing in OrderPricer; this only maps the result to the documented responses.
 */
class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, Restaurant $restaurant, PlaceOrder $placeOrder, WhatsAppMessageBuilder $messages): JsonResponse
    {
        abort_if($restaurant->isSuspended(), 403, 'Cardápio indisponível.');
        abort_unless($restaurant->hasFeature('orders'), 403, 'Esta loja não recebe pedidos pelo cardápio.');

        try {
            $order = $placeOrder->handle($restaurant, $request->validated());
        } catch (OrderRejectedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'issues' => $exception->issues,
                'items' => $exception->items(),
            ], 422);
        }

        $settings = $restaurant->orderSettings();
        $trackingUrl = route('order.track', $order->tracking_token);
        $message = $messages->build($order, $trackingUrl);

        return response()->json([
            'code' => $order->code,
            'tracking_token' => $order->tracking_token,
            'tracking_url' => $trackingUrl,
            'status' => 'novo',
            'total' => $order->total,
            'delivery_fee' => $order->delivery_fee,
            'delivery_fee_pending' => $order->delivery_fee_pending,
            'placed_off_hours' => $order->placed_off_hours,
            'message' => $message,
            'whatsapp_url' => $messages->link($settings->whatsapp_number, $message),
            'pix_payload' => $order->paymentMethod?->slug === 'pix' && filled($settings->pix_key)
                ? PixPayload::build($settings->pix_key, (string) $settings->pix_holder, (string) $settings->pix_city, $order->total, $order->code)
                : null,
        ], 201)->header('Cache-Control', 'no-store');
    }

    /**
     * Checks a discount code against the cart subtotal so the checkout can show the discount
     * before the order is sent. The real order recalculates it (RF-17).
     */
    public function coupon(Request $request, Restaurant $restaurant, ApplyPromoCode $apply): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30'], 'subtotal' => ['required', 'numeric', 'min:0', 'max:1000000']]);

        try {
            $result = $apply->check($restaurant, $data['code'], (int) round($data['subtotal'] * 100));
        } catch (OrderRejectedException $exception) {
            return response()->json(['message' => $exception->issues[0]['message']], 422);
        }

        return response()->json([
            'code' => $result['promo']->code,
            'discount' => number_format($result['discount'] / 100, 2, '.', ''),
            'description' => $result['promo']->description,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * CT-02: the customer opened WhatsApp ("opened") or answered "Sim, enviei" ("confirmed").
     */
    public function whatsapp(Request $request, Restaurant $restaurant, string $token, RecordWhatsAppEvent $record): Response
    {
        $event = (string) $request->input('event', 'opened');

        abort_unless(in_array($event, RecordWhatsAppEvent::EVENTS, true), 422, 'Evento inválido.');

        $order = $restaurant->orders()->where('tracking_token', $token)->firstOrFail();

        abort_if($order->trackingExpired(), 404);

        $record->handle($order, $event);

        return response()->noContent();
    }
}
