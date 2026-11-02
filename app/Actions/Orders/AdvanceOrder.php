<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Orders\OrderTransitions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an order to another status (RF-08). The row is locked while it changes, so two
 * people tapping at once cannot both apply a move (RNF-04); asking for the status the
 * order already has is a no-op, so a double tap never writes two history rows. Each real
 * move writes exactly one history row, which feeds the customer's timeline (RF-07).
 */
class AdvanceOrder
{
    /**
     * @param  array<string, mixed>  $attributes  extra columns saved in the same transaction (fee, reason…)
     *
     * @throws ValidationException
     */
    public function handle(Order $order, string $to, ?User $by = null, array $attributes = []): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $attributes) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status->slug;

            if ($from === $to) {
                return $locked;
            }

            if (! OrderTransitions::can($from, $to)) {
                throw ValidationException::withMessages([
                    'status' => "Não é possível mudar o pedido de “{$locked->status->name}” para essa etapa.",
                ]);
            }

            $now = now();

            $updates = $attributes + ['status_id' => OrderStatus::idFor($to)];

            match ($to) {
                'em_preparo' => $updates += ['accepted_at' => $locked->accepted_at ?? $now],
                'em_rota' => $updates += ['dispatched_at' => $locked->dispatched_at ?? $now],
                'finalizado' => $updates += ['finished_at' => $now],
                'recusado' => $updates += ['cancelled_at' => $now],
                default => null,
            };

            $locked->update($updates);
            $locked->history()->create(['status_id' => $locked->status_id, 'user_id' => $by?->id, 'changed_at' => $now]);

            return $locked->refresh();
        });
    }
}
