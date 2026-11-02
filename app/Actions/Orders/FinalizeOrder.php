<?php

namespace App\Actions\Orders;

use App\Actions\Loyalty\CreditLoyaltyStamp;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderPaymentRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finishes an order that is on its way, recording how it was paid (RF-09): Pix, cash (with
 * change), card, "fiado" or a split between them. The dono can also finish without
 * registering a payment. Everything — the status, the payments, the cash movements and the
 * receivable — happens in one transaction on the locked order, so finalizing twice never
 * duplicates money (RNF-04). A delivery order may be finalized with the caixa closed: the
 * payment is kept on the order but no cash movement is written, and the caller is told
 * (`cash_recorded` false) so the screen can warn the dono (SPEC Q-04).
 */
class FinalizeOrder
{
    public function __construct(private readonly AdvanceOrder $advance, private readonly OrderPaymentRegistrar $registrar, private readonly CreditLoyaltyStamp $creditStamp) {}

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array{order: Order, cash_recorded: bool}
     *
     * @throws ValidationException
     */
    public function handle(Order $order, array $payments = [], ?User $by = null, bool $withoutPayment = false): array
    {
        return DB::transaction(function () use ($order, $payments, $by, $withoutPayment) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->with(['restaurant', 'status'])->firstOrFail();

            if ($locked->status->slug === 'finalizado') {
                return ['order' => $locked, 'cash_recorded' => $locked->cash_session_id !== null];
            }

            if ($locked->status->slug !== 'em_rota') {
                throw ValidationException::withMessages(['status' => 'Só é possível finalizar um pedido que já saiu para entrega ou está pronto.']);
            }

            $recorded = false;

            // A counter sale is paid when it is made (RF-13): finishing it later only moves it on.
            $alreadyPaid = $locked->payments()->exists();

            if (! $withoutPayment && ! $alreadyPaid) {
                $recorded = $this->registrar->register($locked, $payments, $by);
            }

            $finished = $this->advance->handle($locked, 'finalizado', $by);
            $this->creditStamp->handle($finished);

            return ['order' => $finished, 'cash_recorded' => $recorded || ($alreadyPaid && $finished->cash_session_id !== null)];
        });
    }
}
