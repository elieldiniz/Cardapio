<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * What the customer's public tracking page may show (RF-07, RF-07a): the timeline with
 * the time of each step, the items and the total. Only the first name and a partial
 * address (street and neighborhood, no number or complement); never the WhatsApp,
 * the surname or the notes. Shared by the HTML page and the JSON polling endpoint.
 */
class TrackingPresenter
{
    public function present(Order $order): array
    {
        $order->loadMissing(['restaurant', 'status', 'items', 'history.status']);

        $slug = $order->status->slug;
        $delivery = $order->isDelivery();
        $timezone = $order->restaurant->orderSettings()->timezone;
        $at = fn (?CarbonInterface $time) => $time?->copy()->setTimezone($timezone)->format('H:i');

        $first = $order->history->groupBy(fn ($row) => $row->status->slug)->map(fn ($rows) => $rows->first()->changed_at);

        $times = [
            0 => $at($first['novo'] ?? $order->created_at),
            1 => $at($order->accepted_at ?? ($first['em_preparo'] ?? null)),
            2 => $at($order->dispatched_at ?? ($first['em_rota'] ?? null)),
            3 => $at($order->finished_at ?? ($first['finalizado'] ?? null)),
        ];

        $labels = [
            0 => 'Pedido enviado',
            1 => 'Confirmado e em preparo',
            2 => $delivery ? 'Saiu para entrega' : 'Pronto para retirada',
            3 => $delivery ? 'Entregue' : 'Retirado',
        ];

        $current = ['novo' => 0, 'em_preparo' => 1, 'em_rota' => 2, 'finalizado' => 3][$slug] ?? null;
        $bad = in_array($slug, ['recusado', 'cancelado'], true);

        $steps = [];

        foreach ($labels as $index => $label) {
            $steps[] = [
                'label' => $label,
                'done' => $current !== null && $index <= $current,
                'current' => $index === $current,
                'at' => $current !== null && $index <= $current ? $times[$index] : null,
            ];
        }

        return [
            'code' => $order->code,
            'status' => $slug,
            'ended' => $order->isEnded(),
            'bad' => $bad,
            'headline' => $this->headline($slug, $delivery),
            'steps' => $bad ? [] : $steps,
            'first_name' => Str::before(trim((string) $order->customer_name), ' ') ?: null,
            'address' => $delivery ? $this->partialAddress($order) : null,
            'fulfillment' => $order->fulfillment,
            'items' => $order->items->map(fn ($item) => [
                'quantity' => $item->quantityLabel(),
                'name' => $item->name,
                'choices' => SelectionDescriber::lines($item->selections),
            ])->all(),
            'total' => Money::brl($order->total),
            'fee_pending' => (bool) $order->delivery_fee_pending,
        ];
    }

    private function headline(string $slug, bool $delivery): string
    {
        return match ($slug) {
            'novo' => 'Pedido enviado. Aguardando a loja confirmar.',
            'em_preparo' => 'Estamos preparando o seu pedido.',
            'em_rota' => $delivery ? 'Seu pedido saiu para entrega!' : 'Seu pedido está pronto para retirada!',
            'finalizado' => $delivery ? 'Pedido entregue. Bom apetite!' : 'Pedido retirado. Bom apetite!',
            'recusado' => 'A loja não pôde atender este pedido. Fale com a loja para entender.',
            'cancelado' => 'Este pedido foi cancelado.',
            default => 'Acompanhe o seu pedido.',
        };
    }

    private function partialAddress(Order $order): ?string
    {
        $parts = array_filter([$order->address_street, $order->neighborhood]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
