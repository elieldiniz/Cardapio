<?php

namespace App\Services\Orders;

use App\Actions\Cash\CreateReceivable;
use App\Actions\Cash\RecordMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Turns "how the customer paid" into payment rows, cash movements and a receivable (RF-09).
 * Used by FinalizeOrder and UpdateOrderPayment so both follow the same rules:
 *  - the payments must add up to the order total, to the cent;
 *  - cash can carry the amount received, and the change is worked out;
 *  - Pix, cash and card become cash-register movements only while the caixa is open;
 *  - "fiado" creates a receivable and never touches the cash.
 * Every row carries a reference, so registering the same order twice is refused by the
 * database instead of duplicating money (RNF-04).
 */
class OrderPaymentRegistrar
{
    public const METHODS = ['pix', 'dinheiro', 'cartao', 'fiado'];

    public function __construct(private readonly RecordMovement $recordMovement, private readonly CreateReceivable $createReceivable) {}

    /**
     * @param  array<int, array<string, mixed>>  $payments  method, amount, received?, customer_name?, customer_phone?
     * @return bool whether the cash register got a movement (false: closed caixa or only fiado)
     *
     * @throws ValidationException
     */
    public function register(Order $order, array $payments, ?User $by = null): bool
    {
        $rows = $this->validated($order, $payments);
        $restaurant = $order->restaurant;
        $session = $restaurant->openCashSession();
        $recorded = false;

        foreach ($rows as $index => $row) {
            $order->payments()->create([
                'payment_method_id' => PaymentMethod::idFor($row['method']),
                'amount' => $row['amount'],
                'received' => $row['received'],
                'change' => $row['change'],
                'reference' => "order_finalize:{$order->id}:{$index}",
            ]);

            if ($row['method'] === 'fiado') {
                $this->createReceivable->handle($order, $row['customer_name'], $row['customer_phone'], $row['amount'], $session, $by);

                continue;
            }

            if ($session !== null) {
                $this->recordMovement->handle($restaurant, 'venda', $row['amount'], $row['method'], [
                    'order' => $order,
                    'user' => $by,
                    'session' => $session,
                    'reference' => "order_sale:{$order->id}:{$index}",
                ]);

                $recorded = true;
            }
        }

        if ($recorded) {
            $order->update(['cash_session_id' => $session->id]);
        }

        return $recorded;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, array{method: string, amount: string, received: ?string, change: ?string, customer_name: ?string, customer_phone: ?string}>
     *
     * @throws ValidationException
     */
    private function validated(Order $order, array $payments): array
    {
        $payments = array_values($payments);

        if ($payments === []) {
            throw ValidationException::withMessages(['payments' => 'Informe como o cliente pagou.']);
        }

        $rows = [];
        $sum = 0;
        $fiados = 0;

        foreach ($payments as $payment) {
            $method = $payment['method'] ?? null;
            $amount = Money::parse((string) ($payment['amount'] ?? ''));

            if (! in_array($method, self::METHODS, true)) {
                throw ValidationException::withMessages(['payments' => 'Forma de pagamento inválida.']);
            }

            if ($amount === null || (float) $amount <= 0) {
                throw ValidationException::withMessages(['payments' => 'Informe um valor maior que zero em cada pagamento.']);
            }

            $received = null;
            $change = null;

            if ($method === 'dinheiro' && filled($payment['received'] ?? null)) {
                $received = Money::parse((string) $payment['received']);

                if ($received === null || (float) $received < (float) $amount) {
                    throw ValidationException::withMessages(['payments' => 'O valor recebido em dinheiro não pode ser menor que o valor pago.']);
                }

                $change = number_format((float) $received - (float) $amount, 2, '.', '');
            }

            if ($method === 'fiado') {
                $fiados++;
            }

            $sum += (int) round((float) $amount * 100);
            $rows[] = [
                'method' => $method,
                'amount' => $amount,
                'received' => $received,
                'change' => $change,
                'customer_name' => $method === 'fiado' ? trim((string) ($payment['customer_name'] ?? $order->customer_name ?? '')) : null,
                'customer_phone' => $method === 'fiado' ? (string) ($payment['customer_phone'] ?? $order->customer_phone ?? '') : null,
            ];
        }

        if ($fiados > 1) {
            throw ValidationException::withMessages(['payments' => 'Só é possível lançar um fiado por pedido.']);
        }

        if ($sum !== (int) round((float) $order->total * 100)) {
            throw ValidationException::withMessages([
                'payments' => 'A soma dos pagamentos ('.Money::brl($sum / 100).') precisa ser igual ao total do pedido ('.Money::brl($order->total).').',
            ]);
        }

        return $rows;
    }
}
