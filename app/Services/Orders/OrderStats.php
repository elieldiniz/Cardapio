<?php

namespace App\Services\Orders;

use App\Models\FunnelEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Support\BusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The numbers of the "Visualizações" screen about orders (RF-21). `counters` are open to every
 * plan; `advanced` (funnel, rankings, peak hour) is only for plans whose metrics level is
 * `por_prato` or above — the screen decides, this class just computes.
 */
class OrderStats
{
    /**
     * The last `$days` days in the store's own time, as a UTC range.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(Restaurant $restaurant, int $days): array
    {
        $today = BusinessHours::localNow($restaurant)->startOfDay();

        return ['from' => $today->subDays(max(1, $days) - 1)->utc(), 'to' => $today->addDay()->utc()];
    }

    /**
     * @return array{orders: int, sales: string, by_origin: array<string, int>}
     */
    public function counters(Restaurant $restaurant, int $days): array
    {
        $range = $this->range($restaurant, $days);

        $placed = $this->placed($restaurant, $range);

        $sales = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereHas('status', fn ($status) => $status->where('slug', 'finalizado'))
            ->whereBetween('finished_at', [$range['from'], $range['to']])
            ->sum('total');

        return [
            'orders' => (clone $placed)->count(),
            'sales' => number_format((float) $sales, 2, '.', ''),
            'by_origin' => (clone $placed)->select('origin', DB::raw('COUNT(*) as total'))->groupBy('origin')->orderByDesc('total')->orderBy('origin')->pluck('total', 'origin')->map(fn ($total) => (int) $total)->all(),
        ];
    }

    /**
     * @return array{funnel: array<int, array{key: string, label: string, count: int, rate: ?int}>, most_added: array<int, array{name: string, count: int}>, most_ordered: array<int, array{name: string, count: int}>, top_options: array<int, array{name: string, count: int}>, peak_hours: array<int, array{hour: int, orders: int}>}
     */
    public function advanced(Restaurant $restaurant, int $days): array
    {
        $range = $this->range($restaurant, $days);
        $placed = $this->placed($restaurant, $range);

        $events = FunnelEvent::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('occurred_on', '>=', $range['from']->setTimezone($restaurant->orderSettings()->timezone)->toDateString())
            ->select('event', DB::raw('COUNT(DISTINCT session_token) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        $steps = [
            'visit' => ['Visitaram o cardápio', (int) ($events['visit'] ?? 0)],
            'add' => ['Adicionaram ao pedido', (int) ($events['add'] ?? 0)],
            'checkout' => ['Iniciaram o pedido', (int) ($events['checkout'] ?? 0)],
            'order' => ['Enviaram o pedido', (clone $placed)->count()],
            'whatsapp' => ['Abriram o WhatsApp', (clone $placed)->whereNotNull('whatsapp_opened_at')->count()],
            'confirmed' => ['Confirmaram o envio', (clone $placed)->whereNotNull('customer_confirmed_at')->count()],
        ];

        $funnel = [];
        $previous = null;

        foreach ($steps as $key => [$label, $count]) {
            $funnel[] = ['key' => $key, 'label' => $label, 'count' => $count, 'rate' => $previous ? (int) round($count / $previous * 100) : null];
            $previous = $count > 0 ? $count : null;
        }

        return [
            'funnel' => $funnel,
            'most_added' => $this->mostAdded($restaurant, $range),
            'most_ordered' => $this->mostOrdered($restaurant, $range),
            'top_options' => $this->topOptions($restaurant, $range),
            'peak_hours' => $this->peakHours($restaurant, $range),
        ];
    }

    /** Orders that reached the kitchen's attention: not refused, not cancelled. */
    private function placed(Restaurant $restaurant, array $range)
    {
        return Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('channel', 'feed')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->whereHas('status', fn ($status) => $status->whereNotIn('slug', ['recusado', 'cancelado']));
    }

    /** @return array<int, array{name: string, count: int}> */
    private function mostAdded(Restaurant $restaurant, array $range): array
    {
        return FunnelEvent::query()
            ->where('funnel_events.restaurant_id', $restaurant->id)
            ->where('event', 'add')
            ->where('dish_id', '>', 0)
            ->where('occurred_on', '>=', $range['from']->setTimezone($restaurant->orderSettings()->timezone)->toDateString())
            ->join('dishes', 'dishes.id', '=', 'funnel_events.dish_id')
            ->groupBy('dishes.name')
            ->select('dishes.name', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total])->all();
    }

    /** @return array<int, array{name: string, count: int}> */
    private function mostOrdered(Restaurant $restaurant, array $range): array
    {
        return OrderItem::query()
            ->whereIn('order_id', (clone $this->placed($restaurant, $range))->select('orders.id'))
            ->groupBy('name')
            ->select('name', DB::raw('SUM(quantity) as total'))
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) round((float) $row->total)])->all();
    }

    /** @return array<int, array{name: string, count: int}> */
    private function topOptions(Restaurant $restaurant, array $range): array
    {
        $counts = [];

        OrderItem::query()
            ->whereIn('order_id', (clone $this->placed($restaurant, $range))->select('orders.id'))
            ->whereNotNull('selections')
            ->select('id', 'selections')
            ->chunkById(500, function ($items) use (&$counts) {
                foreach ($items as $item) {
                    foreach ($item->selections['complements'] ?? [] as $complement) {
                        $counts[$complement['name']] = ($counts[$complement['name']] ?? 0) + max(1, (int) ($complement['quantity'] ?? 1));
                    }
                }
            });

        arsort($counts);

        return collect($counts)->take(5)->map(fn ($count, $name) => ['name' => (string) $name, 'count' => $count])->values()->all();
    }

    /** @return array<int, array{hour: int, orders: int}> */
    private function peakHours(Restaurant $restaurant, array $range): array
    {
        $timezone = $restaurant->orderSettings()->timezone;
        $hours = [];

        (clone $this->placed($restaurant, $range))->select('created_at')->orderBy('id')->each(function (Order $order) use (&$hours, $timezone) {
            $hour = (int) $order->created_at->copy()->setTimezone($timezone)->format('G');
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        });

        arsort($hours);

        return collect($hours)->take(3)->map(fn ($orders, $hour) => ['hour' => (int) $hour, 'orders' => $orders])->values()->all();
    }
}
