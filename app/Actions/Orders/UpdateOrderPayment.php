<?php

namespace App\Actions\Orders;

use App\Models\CashMovement;
use App\Models\CashMovementType;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Orders\OrderPaymentRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrects how a finished order was paid (RF-09, "Editar" on the card). It replaces the
 * payments, the cash movements and the fiado of the order. While the movements still
 * belong to an open caixa this is safe; once that caixa is closed the numbers are history,
 * so the edit is refused. A "fiado" that was already paid cannot be edited either.
 */
class UpdateOrderPayment
{
    public function __construct(private readonly OrderPaymentRegistrar $registrar) {}

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return bool whether the cash register got movements for the new payments
     *
     * @throws ValidationException
     */
    public function handle(Order $order, array $payments, ?User $by = null): bool
    {
        return DB::transaction(function () use ($order, $payments, $by) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->with(['restaurant', 'status'])->firstOrFail();

            if ($locked->status->slug !== 'finalizado') {
                throw ValidationException::withMessages(['status' => 'Só é possível editar o pagamento de um pedido finalizado.']);
            }

            $sales = CashMovement::query()
                ->where('order_id', $locked->id)
                ->where('type_id', CashMovementType::idFor('venda'))
                ->with('session')
                ->get();

            if ($sales->contains(fn (CashMovement $movement) => ! $movement->session->isOpen())) {
                throw ValidationException::withMessages(['payments' => 'O caixa deste pedido já foi fechado. Não é possível alterar o pagamento.']);
            }

            $receivable = Receivable::query()->where('order_id', $locked->id)->where('reference', "order_fiado:{$locked->id}")->first();

            if ($receivable?->paid_at !== null) {
                throw ValidationException::withMessages(['payments' => 'O fiado deste pedido já foi pago. Não é possível alterar o pagamento.']);
            }

            CashMovement::query()->whereIn('id', $sales->pluck('id'))->delete();
            $receivable?->delete();
            $locked->payments()->delete();
            $locked->update(['cash_session_id' => null]);

            return $this->registrar->register($locked, $payments, $by);
        });
    }
}
