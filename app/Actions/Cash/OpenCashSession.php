<?php

namespace App\Actions\Cash;

use App\Models\CashSession;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens the store's cash register with the change that is in the drawer (RF-10). A store
 * can have only one open session: the database enforces it, so two simultaneous taps on
 * "Abrir caixa" cannot both succeed (RNF-04).
 */
class OpenCashSession
{
    /**
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, ?string $openingFloat = null, ?User $by = null): CashSession
    {
        $float = filled($openingFloat) ? Money::parse($openingFloat) : '0.00';

        if ($float === null) {
            throw ValidationException::withMessages(['opening_float' => 'Valor do troco inválido. Use o formato 100,00.']);
        }

        if (! $restaurant->hasFeature('cash')) {
            throw ValidationException::withMessages(['cash' => 'O caixa não está incluído no seu plano.']);
        }

        $alreadyOpen = ValidationException::withMessages(['cash' => 'O caixa já está aberto.']);

        if ($restaurant->cashSessions()->open()->exists()) {
            throw $alreadyOpen;
        }

        try {
            return DB::transaction(fn () => $restaurant->cashSessions()->create([
                'opened_by' => $by?->id,
                'opening_float' => $float,
                'opened_at' => now(),
            ]));
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'open_restaurant_id')) {
                throw $alreadyOpen;
            }

            throw $exception;
        }
    }
}
