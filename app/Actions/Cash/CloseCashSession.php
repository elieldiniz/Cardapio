<?php

namespace App\Actions\Cash;

use App\Models\CashSession;
use App\Models\User;
use App\Services\Cash\CashSummary;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes the cash register comparing the cash the dono counted with the cash expected in
 * the drawer (RF-10). The expected value and the difference are saved on the session, so
 * the closing stays true even if the rules change later. Closing twice is refused.
 */
class CloseCashSession
{
    public function __construct(private readonly CashSummary $summary) {}

    /**
     * @throws ValidationException
     */
    public function handle(CashSession $session, ?string $countedCash, ?string $notes = null, ?User $by = null): CashSession
    {
        $counted = filled($countedCash) ? Money::parse($countedCash) : null;

        if ($counted === null) {
            throw ValidationException::withMessages(['counted_cash' => 'Conte o dinheiro da gaveta e informe o valor (pode ser 0,00).']);
        }

        return DB::transaction(function () use ($session, $counted, $notes, $by) {
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['cash' => 'Este caixa já foi fechado.']);
            }

            $expected = $this->summary->for($locked)['expected_cash'];

            $locked->update([
                'closed_at' => now(),
                'closed_by' => $by?->id,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => number_format((float) $counted - (float) $expected, 2, '.', ''),
                'notes' => filled($notes) ? mb_substr(trim($notes), 0, 255) : null,
            ]);

            return $locked->refresh();
        });
    }
}
