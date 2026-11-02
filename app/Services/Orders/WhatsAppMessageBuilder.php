<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;

/**
 * Builds the ready-to-send WhatsApp message of an order: items with their choices,
 * subtotal, delivery, total, how it will be received and paid, the address and the
 * public tracking link (RF-06, RF-06c). The wa.me link carries it, so the customer
 * only taps "send" — and the dono reads the whole order in one message.
 */
class WhatsAppMessageBuilder
{
    private const PAYMENT_LABELS = [
        'pix' => '⚡ Pagamento: Pix',
        'dinheiro' => '💵 Pagamento: Dinheiro',
        'debito' => '💳 Pagamento: Débito (maquininha na entrega)',
        'credito' => '💳 Pagamento: Crédito (maquininha na entrega)',
    ];

    public function build(Order $order, string $trackingUrl): string
    {
        $order->loadMissing(['items', 'paymentMethod']);

        $lines = ['Olá! Gostaria de fazer um pedido:', '🧾 Pedido '.$order->code, ''];

        foreach ($order->items as $item) {
            $icon = $item->selections['icon'] ?? '🍽️';
            $lines[] = $icon.' '.$item->name.' — '.$this->quantity($item).' ('.Money::brl($item->total).')';

            foreach (SelectionDescriber::lines($item->selections) as $selection) {
                $lines[] = '   ↳ '.$selection;
            }

            if ($item->notes) {
                $lines[] = '   ↳ Obs.: '.$item->notes;
            }
        }

        $lines[] = '';
        $lines[] = 'Subtotal: '.Money::brl($order->subtotal);

        if ($order->isDelivery()) {
            $lines[] = '🛵 Entrega: '.match (true) {
                $order->delivery_fee_pending => 'a combinar',
                (float) $order->delivery_fee === 0.0 => 'grátis 🎉',
                default => Money::brl($order->delivery_fee),
            };
        }

        if ((float) $order->discount > 0) {
            $lines[] = '🏷️ Desconto: -'.Money::brl($order->discount);
        }

        $lines[] = '💰 Total estimado: '.Money::brl($order->total);
        $lines[] = '';
        $lines[] = '📦 Forma de recebimento: '.($order->isDelivery() ? 'Entrega' : 'Retirada na loja');

        $slug = $order->paymentMethod?->slug;

        if ($slug !== null) {
            $payment = self::PAYMENT_LABELS[$slug] ?? '💳 Pagamento: '.$order->paymentMethod->name;

            if ($slug === 'dinheiro' && $order->change_for !== null) {
                $payment .= ' — troco para '.Money::brl($order->change_for);
            }

            $lines[] = $payment;

            if ($slug === 'pix') {
                $lines[] = '📎 Pagamento via Pix: mandar comprovante!';
            }
        }

        if ($order->isDelivery()) {
            $lines[] = '';
            $lines[] = '📍 Endereço de entrega';
            $lines[] = 'Quem recebe: '.$order->customer_name;
            $lines[] = trim(($order->address_street ?? '').($order->address_number ? ', '.$order->address_number : '').($order->address_complement ? ' — '.$order->address_complement : ''));

            if ($order->neighborhood) {
                $lines[] = 'Bairro: '.$order->neighborhood;
            }

            if ($order->address_reference) {
                $lines[] = 'Referência: '.$order->address_reference;
            }
        } elseif ($order->customer_name) {
            $lines[] = '';
            $lines[] = '👤 Nome: '.$order->customer_name;
        }

        if ($order->notes) {
            $lines[] = '';
            $lines[] = '📝 Observação: '.$order->notes;
        }

        if ($order->placed_off_hours) {
            $lines[] = '';
            $lines[] = '⏰ Pedido feito fora do horário de funcionamento.';
        }

        $lines[] = '';
        $lines[] = '📍 Acompanhe seu pedido: '.$trackingUrl;
        $lines[] = '';
        $lines[] = 'Aguardo confirmação. 😊';

        return implode("\n", $lines);
    }

    /**
     * The wa.me link that opens WhatsApp with the message already written.
     */
    public function link(string $number, string $message): string
    {
        return 'https://api.whatsapp.com/send?phone='.$number.'&text='.rawurlencode($message);
    }

    private function quantity(OrderItem $item): string
    {
        if ($item->sale_type === 'kg') {
            return $item->quantityLabel();
        }

        $quantity = (int) $item->quantity;

        return $quantity.' '.($quantity === 1 ? 'unidade' : 'unidades');
    }
}
