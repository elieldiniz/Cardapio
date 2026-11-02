<?php

namespace App\Actions\Orders;

use App\Models\Order;

/**
 * Records what the customer did with the WhatsApp step of the checkout (RF-06a):
 * "opened" when the message window was opened, "confirmed" when they answered "Sim,
 * enviei". "Ainda não" records nothing. A timestamp is written once and never moved,
 * so repeating the call is harmless.
 */
class RecordWhatsAppEvent
{
    public const EVENTS = ['opened', 'confirmed', 'not_sent'];

    public function handle(Order $order, string $event): Order
    {
        $column = match ($event) {
            'opened' => 'whatsapp_opened_at',
            'confirmed' => 'customer_confirmed_at',
            default => null,
        };

        if ($column !== null && $order->{$column} === null) {
            $updates = [$column => now()];

            // Confirming implies the window was opened, even if the opened ping was lost.
            if ($event === 'confirmed' && $order->whatsapp_opened_at === null) {
                $updates['whatsapp_opened_at'] = now();
            }

            $order->update($updates);
        }

        return $order;
    }
}
