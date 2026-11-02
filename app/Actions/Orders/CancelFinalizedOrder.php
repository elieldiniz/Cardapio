<?php

namespace App\Actions\Orders;

use App\Actions\Cash\CancelReceivable;
use App\Actions\Cash\RecordMovement;
use App\Actions\Loyalty\RevertLoyaltyStamp;
use App\Models\CashMovement;
use App\Models\CashMovementType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an order that was already finalized (RF-08c): needs a reason, writes a refund
 * ("estorno") movement for every payment taken in the cash register — nothing is deleted,
 * the sale stays in its day and the refund lands in the open caixa — and cancels the
 * customer's "fiado" if it was not paid yet. A fiado already paid is kept, and its payment
 * is refunded like any other. Cancelling twice changes nothing (RNF-04).
 */
class CancelFinalizedOrder
{
    public function __construct(
        private readonly RecordMovement $recordMovement,
        private readonly CancelReceivable $cancelReceivable,
        private readonly ReleasePromoCodeUse $releasePromoCode,
        private readonly RevertLoyaltyStamp $revertStamp,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?string $reason, ?User $by = null): Order
    {
        $reason = trim((string) $reason);

        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo do cancelamento.']);
        }

        return DB::transaction(function () use ($order, $reason, $by) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->with(['restaurant', 'status'])->firstOrFail();

            if ($locked->status->slug === 'cancelado') {
                return $locked;
            }

            if ($locked->status->slug !== 'finalizado') {
                throw ValidationException::withMessages(['status' => 'Só um pedido finalizado pode ser cancelado com estorno. Pedidos em andamento são recusados.']);
            }

            $taken = CashMovement::query()
                ->where('order_id', $locked->id)
                ->whereIn('type_id', [CashMovementType::idFor('venda'), CashMovementType::idFor('recebimento_fiado')])
                ->get();

            $session = $locked->restaurant->openCashSession();

            if ($taken->isNotEmpty() && $session === null) {
                throw ValidationException::withMessages(['cash' => 'Abra o caixa para registrar o estorno deste pedido.']);
            }

            foreach ($taken as $movement) {
                $this->recordMovement->handle($locked->restaurant, 'estorno', $movement->amount, $movement->paymentMethod->slug, [
                    'order' => $locked,
                    'user' => $by,
                    'session' => $session,
                    'reference' => "order_refund:{$movement->id}",
                    'description' => "Estorno do pedido {$locked->code}: {$reason}",
                ]);
            }

            Receivable::query()
                ->where('order_id', $locked->id)
                ->whereNull('paid_at')
                ->whereNull('cancelled_at')
                ->get()
                ->each(fn (Receivable $receivable) => $this->cancelReceivable->handle($receivable));

            $now = now();

            $locked->update([
                'status_id' => OrderStatus::idFor('cancelado'),
                'cancelled_at' => $now,
                'cancel_reason' => mb_substr($reason, 0, 255),
            ]);
            $locked->history()->create(['status_id' => $locked->status_id, 'user_id' => $by?->id, 'changed_at' => $now]);

            $this->releasePromoCode->handle($locked);
            $this->revertStamp->handle($locked);

            return $locked->refresh();
        });
    }
}
