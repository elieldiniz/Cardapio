<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * The ready-made WhatsApp message the dono sends to the customer when the order changes
 * stage (RF-08a): confirmed, out for delivery or ready to pick up, finished, refused.
 * Every message carries the tracking link, so the customer can follow the rest alone.
 */
class CustomerNoticeBuilder
{
    /**
     * @return string|null null when there is nothing to say at this stage ("novo")
     */
    public function message(Order $order): ?string
    {
        $order->loadMissing('status');

        $name = Str::before(trim((string) $order->customer_name), ' ') ?: 'tudo bem';
        $greeting = "Olá, {$name}!";
        $link = "\n\n📍 Acompanhe: ".route('order.track', $order->tracking_token);
        $delivery = $order->isDelivery();

        return match ($order->status->slug) {
            'em_preparo' => "{$greeting} 👨‍🍳 Seu pedido {$order->code} foi confirmado e já está sendo preparado.{$link}",
            'em_rota' => $delivery
                ? "{$greeting} 🛵 Seu pedido {$order->code} saiu para entrega!{$link}"
                : "{$greeting} ✅ Seu pedido {$order->code} está pronto para retirada!{$link}",
            'finalizado' => "{$greeting} Obrigado pelo pedido {$order->code}! 😊 Volte sempre.",
            'recusado' => "{$greeting} Infelizmente não conseguimos atender o pedido {$order->code}. Quer que a gente te ajude com outra opção?",
            'cancelado' => "{$greeting} O pedido {$order->code} foi cancelado. Qualquer dúvida é só chamar por aqui.",
            default => null,
        };
    }

    /**
     * The wa.me link with the message, or null without a customer number or a message.
     */
    public function link(Order $order): ?string
    {
        $message = $this->message($order);

        if ($message === null || blank($order->customer_phone)) {
            return null;
        }

        return 'https://api.whatsapp.com/send?phone='.$order->customer_phone.'&text='.rawurlencode($message);
    }
}
