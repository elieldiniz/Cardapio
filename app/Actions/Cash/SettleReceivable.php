<?php

namespace App\Actions\Cash;

use App\Models\Receivable;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receives a "fiado" (RF-11): marks it paid and writes the money into the open caixa as
 * "recebimento de fiado", with the method the customer used. The receivable row is locked
 * and the movement has a reference, so receiving twice never enters the money twice
 * (RNF-04). A cancelled fiado cannot be received.
 */
class SettleReceivable
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    /**
     * @throws ValidationException
     */
    public function handle(Receivable $receivable, string $method, ?User $by = null): Receivable
    {
        return DB::transaction(function () use ($receivable, $method, $by) {
            $locked = Receivable::query()->whereKey($receivable->id)->lockForUpdate()->with('order.restaurant')->firstOrFail();

            if ($locked->cancelled_at !== null) {
                throw ValidationException::withMessages(['receivable' => 'Este fiado foi cancelado.']);
            }

            if ($locked->paid_at !== null) {
                return $locked;
            }

            $restaurant = $locked->order?->restaurant ?? Restaurant::query()->findOrFail($locked->restaurant_id);
            $session = $restaurant->openCashSession();

            if ($session === null) {
                throw ValidationException::withMessages(['cash' => 'Abra o caixa para registrar o recebimento.']);
            }

            $this->recordMovement->handle($restaurant, 'recebimento_fiado', $locked->amount, $method, [
                'order' => $locked->order,
                'user' => $by,
                'session' => $session,
                'reference' => "receivable_paid:{$locked->id}",
                'description' => "Fiado de {$locked->customer_name}",
            ]);

            $locked->update(['paid_at' => now()]);

            return $locked->refresh();
        });
    }
}
