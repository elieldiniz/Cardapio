<?php

namespace App\Services\Cash;

use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Restaurant;
use App\Support\StorePeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The financial report of a period (RF-12): what was sold, what actually came in, what went
 * out and the result — all in the store's calendar days (RNF-09).
 *
 *  - Vendas: finalized orders of the period (a "fiado" counts as sold the day it is finalized).
 *  - Recebido: money that went through the cash register — sales and fiado received, minus
 *    refunds. A "fiado" counts only when it is paid; a delivery finalized with the caixa
 *    closed is a sale but not yet "recebido", because it never entered the register.
 *  - Despesas: expense movements.
 *  - Resultado: recebido − despesas, so it always matches the movements.
 * Everything is computed in integer cents.
 */
class ReportBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Restaurant $restaurant, string $period, ?CarbonInterface $now = null): array
    {
        $range = StorePeriod::range($restaurant, $period, $now);

        $sold = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereHas('status', fn ($status) => $status->where('slug', 'finalizado'))
            ->where('finished_at', '>=', $range['from'])
            ->where('finished_at', '<', $range['to']);

        $salesCents = (int) round(((float) (clone $sold)->sum('total')) * 100);
        $salesCount = (clone $sold)->count();

        $origins = (clone $sold)
            ->select('origin', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total) as total'))
            ->groupBy('origin')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['origin' => $row->origin, 'orders' => (int) $row->orders, 'total' => number_format((float) $row->total, 2, '.', '')])
            ->all();

        $rows = CashMovement::query()
            ->where('cash_movements.restaurant_id', $restaurant->id)
            ->where('occurred_at', '>=', $range['from'])
            ->where('occurred_at', '<', $range['to'])
            ->join('cash_movement_types', 'cash_movement_types.id', '=', 'cash_movements.type_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'cash_movements.payment_method_id')
            ->select('cash_movement_types.slug as type', 'payment_methods.slug as method', DB::raw('SUM(amount) as total'))
            ->groupBy('cash_movement_types.slug', 'payment_methods.slug')
            ->get();

        $byMethod = ['pix' => 0, 'dinheiro' => 0, 'cartao' => 0];
        $expensesCents = 0;

        foreach ($rows as $row) {
            $cents = (int) round(((float) $row->total) * 100);
            $bucket = $row->method === 'dinheiro' ? 'dinheiro' : ($row->method === 'pix' ? 'pix' : (in_array($row->method, ['cartao', 'debito', 'credito'], true) ? 'cartao' : null));

            if ($row->type === 'despesa') {
                $expensesCents += $cents;
            } elseif ($bucket && in_array($row->type, ['venda', 'recebimento_fiado'], true)) {
                $byMethod[$bucket] += $cents;
            } elseif ($bucket && $row->type === 'estorno') {
                $byMethod[$bucket] -= $cents;
            }
        }

        $receivedCents = array_sum($byMethod);

        return [
            'period' => $period,
            'label' => StorePeriod::label($period),
            'from' => $range['from'],
            'to' => $range['to'],
            'sales_total' => $this->money($salesCents),
            'sales_count' => $salesCount,
            'ticket' => $this->money($salesCount > 0 ? (int) round($salesCents / $salesCount) : 0),
            'received' => $this->money($receivedCents),
            'expenses' => $this->money($expensesCents),
            'result' => $this->money($receivedCents - $expensesCents),
            'by_method' => array_map(fn (int $cents) => $this->money($cents), $byMethod),
            'by_origin' => $origins,
        ];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
