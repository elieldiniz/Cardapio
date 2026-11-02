<?php

namespace App\Actions\Cash;

use App\Models\Receivable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a "fiado" nobody paid yet (RF-08c): the debt stops existing. One that was already
 * paid is history and cannot be cancelled. Cancelling twice changes nothing.
 */
class CancelReceivable
{
    /**
     * @throws ValidationException
     */
    public function handle(Receivable $receivable): Receivable
    {
        return DB::transaction(function () use ($receivable) {
            $locked = Receivable::query()->whereKey($receivable->id)->lockForUpdate()->firstOrFail();

            if ($locked->cancelled_at !== null) {
                return $locked;
            }

            if ($locked->paid_at !== null) {
                throw ValidationException::withMessages(['receivable' => 'Este fiado já foi pago e não pode ser cancelado.']);
            }

            $locked->update(['cancelled_at' => now()]);

            return $locked->refresh();
        });
    }
}
