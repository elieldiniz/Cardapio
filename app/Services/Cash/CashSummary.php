<?php

namespace App\Services\Cash;

use App\Models\CashSession;
use App\Support\Money;

/**
 * The numbers of one cash session (RF-10), computed in integer cents from its movements.
 *
 * The cash expected in the drawer counts only cash (SPEC Q-04): the opening change, plus cash
 * sales and cash received on "fiado", plus top-ups, minus withdrawals, cash expenses and
 * cash refunds. Pix and card are reported on their own and never enter the drawer.
 */
class CashSummary
{
    private const CASH = 'dinheiro';

    private const CARD = ['cartao', 'debito', 'credito'];

    /**
     * @return array{opening_float: string, sales: array{dinheiro: string, pix: string, cartao: string}, sales_total: string, sales_count: int, fiado_sold: string, expenses_total: string, expenses_cash: string, withdrawals: string, top_ups: string, refunds_cash: string, expected_cash: string}
     */
    public function for(CashSession $session): array
    {
        $movements = $session->movements()->with(['type', 'paymentMethod'])->get();

        $sales = ['dinheiro' => 0, 'pix' => 0, 'cartao' => 0];
        $expenses = 0;
        $expensesCash = 0;
        $withdrawals = 0;
        $topUps = 0;
        $refundsCash = 0;
        $orders = [];

        foreach ($movements as $movement) {
            $cents = $this->cents($movement->amount);
            $method = $movement->paymentMethod?->slug;
            $bucket = $method === self::CASH ? 'dinheiro' : ($method === 'pix' ? 'pix' : (in_array($method, self::CARD, true) ? 'cartao' : null));

            switch ($movement->type->slug) {
                case 'venda':
                    if ($bucket) {
                        $sales[$bucket] += $cents;
                    }

                    if ($movement->order_id) {
                        $orders[$movement->order_id] = true;
                    }
                    break;
                case 'recebimento_fiado':
                    if ($bucket) {
                        $sales[$bucket] += $cents;
                    }
                    break;
                case 'estorno':
                    if ($bucket) {
                        $sales[$bucket] -= $cents;
                    }

                    if ($bucket === 'dinheiro') {
                        $refundsCash += $cents;
                    }
                    break;
                case 'despesa':
                    $expenses += $cents;
                    $expensesCash += $method === self::CASH ? $cents : 0;
                    break;
                case 'retirada':
                    $withdrawals += $cents;
                    break;
                case 'reforco':
                    $topUps += $cents;
                    break;
            }
        }

        $fiado = $session->receivables()->whereNull('cancelled_at')->get()->sum(fn ($receivable) => $this->cents($receivable->amount));

        // Cash sales already net of cash refunds, so refunds are not subtracted twice.
        $expected = $this->cents($session->opening_float) + $sales['dinheiro'] + $topUps - $withdrawals - $expensesCash;

        return [
            'opening_float' => $this->format($this->cents($session->opening_float)),
            'sales' => array_map(fn (int $cents) => $this->format($cents), $sales),
            'sales_total' => $this->format(array_sum($sales)),
            'sales_count' => count($orders),
            'fiado_sold' => $this->format($fiado),
            'expenses_total' => $this->format($expenses),
            'expenses_cash' => $this->format($expensesCash),
            'withdrawals' => $this->format($withdrawals),
            'top_ups' => $this->format($topUps),
            'refunds_cash' => $this->format($refundsCash),
            'expected_cash' => $this->format($expected),
        ];
    }

    private function cents(string|float|int|null $amount): int
    {
        return $amount === null ? 0 : (int) round(((float) $amount) * 100);
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function brl(string $amount): string
    {
        return Money::brl($amount);
    }
}
