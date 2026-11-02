<?php

namespace App\Actions\Couriers;

use App\Actions\Cash\RecordMovement;
use App\Models\CashMovement;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pays a courier for the deliveries finalized and not yet settled (RF-22): one expense in the
 * caixa (category "Motoboys") of deliveries × fee. The orders are claimed first, in the same
 * transaction, so settling twice writes a single expense (RNF-04); with the caixa closed
 * nothing is claimed.
 */
class SettleCourierRun
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    /** How many deliveries are waiting to be paid, and how much that is. */
    public function pending(Courier $courier): array
    {
        $count = $this->pendingQuery($courier)->count();

        return ['count' => $count, 'amount' => number_format($count * (float) $courier->fee_per_delivery, 2, '.', '')];
    }

    /**
     * @return CashMovement|null null when there was nothing to settle
     *
     * @throws ValidationException
     */
    public function handle(Courier $courier, ?User $by = null, string $method = 'dinheiro'): ?CashMovement
    {
        return DB::transaction(function () use ($courier, $by, $method) {
            $ids = $this->pendingQuery($courier)->lockForUpdate()->pluck('id');

            if ($ids->isEmpty()) {
                return null;
            }

            $amount = number_format($ids->count() * (float) $courier->fee_per_delivery, 2, '.', '');

            if ((float) $amount <= 0) {
                throw ValidationException::withMessages(['courier' => 'Defina o valor por entrega do motoboy antes de acertar.']);
            }

            $claimed = Order::query()->whereIn('id', $ids)->whereNull('courier_settled_at')->update(['courier_settled_at' => now()]);

            if ($claimed !== $ids->count()) {
                throw ValidationException::withMessages(['courier' => 'O acerto mudou. Tente de novo.']);
            }

            return $this->recordMovement->handle($courier->restaurant, 'despesa', $amount, $method, [
                'category' => 'Motoboys',
                'description' => "Acerto do motoboy {$courier->name}: {$ids->count()} ".($ids->count() === 1 ? 'entrega' : 'entregas'),
                'reference' => "courier_settle:{$courier->id}:{$ids->max()}:{$ids->count()}",
                'user' => $by,
            ]);
        });
    }

    private function pendingQuery(Courier $courier)
    {
        return Order::query()
            ->where('courier_id', $courier->id)
            ->whereNull('courier_settled_at')
            ->whereHas('status', fn ($status) => $status->where('slug', 'finalizado'));
    }
}
