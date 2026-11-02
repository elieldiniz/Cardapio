<?php

namespace App\Actions\Cash;

use App\Models\CashSession;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Records that a customer owes the store ("fiado", RF-11) for an order finalized on credit.
 * One receivable per order: asking again returns the one that exists (RNF-04).
 */
class CreateReceivable
{
    /**
     * @throws ValidationException
     */
    public function handle(Order $order, string $name, ?string $phone, string $amount, ?CashSession $session = null, ?User $by = null): Receivable
    {
        $value = Money::parse($amount);

        if (blank(trim($name))) {
            throw ValidationException::withMessages(['customer_name' => 'Informe quem ficou devendo.']);
        }

        if ($value === null || (float) $value <= 0) {
            throw ValidationException::withMessages(['amount' => 'Valor do fiado inválido.']);
        }

        $reference = "order_fiado:{$order->id}";

        return Receivable::query()->firstOrCreate(
            ['restaurant_id' => $order->restaurant_id, 'reference' => $reference],
            [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'cash_session_id' => $session?->id,
                'customer_name' => mb_substr(trim($name), 0, 120),
                'customer_phone' => filled($phone) ? preg_replace('/\D+/', '', $phone) : null,
                'amount' => $value,
            ],
        );
    }
}
