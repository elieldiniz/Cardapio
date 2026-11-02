<?php

namespace App\Actions\Cash;

use App\Models\CashMovement;
use App\Models\CashMovementType;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes one line of the cash register (RF-10). The caixa must be open. The session row is
 * locked while writing, so a movement can never land in a session that is closing, and a
 * `reference` makes the call idempotent: recording the same event again returns the line
 * already written instead of a second one (RNF-04). Withdrawals and top-ups are always cash.
 *
 * meta: category, description, order (Order), reference, user (User), session (CashSession).
 */
class RecordMovement
{
    public const EXPENSE_CATEGORIES = ['Mercadoria / fornecedor', 'Gás', 'Gelo', 'Embalagens', 'Funcionários', 'Motoboys', 'Aluguel / contas', 'Manutenção', 'Outros'];

    private const METHODS = [
        'venda' => ['dinheiro', 'pix', 'cartao', 'debito', 'credito'],
        'recebimento_fiado' => ['dinheiro', 'pix', 'cartao', 'debito', 'credito'],
        'estorno' => ['dinheiro', 'pix', 'cartao', 'debito', 'credito'],
        'despesa' => ['dinheiro', 'pix', 'cartao'],
    ];

    /**
     * @param  array<string, mixed>  $meta
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, string $type, string|float|int $amount, ?string $method = null, array $meta = []): CashMovement
    {
        if (! array_key_exists($type, self::METHODS) && ! in_array($type, ['retirada', 'reforco'], true)) {
            throw ValidationException::withMessages(['type' => 'Tipo de movimento inválido.']);
        }

        $value = Money::parse(is_string($amount) ? $amount : number_format((float) $amount, 2, '.', ''));

        if ($value === null || (float) $value <= 0) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor maior que zero.']);
        }

        if (in_array($type, ['retirada', 'reforco'], true)) {
            $method = 'dinheiro';
        } elseif (! in_array($method, self::METHODS[$type], true)) {
            throw ValidationException::withMessages(['method' => 'Escolha a forma de pagamento.']);
        }

        $category = null;

        if ($type === 'despesa') {
            $category = filled($meta['category'] ?? null) ? $meta['category'] : 'Outros';

            if (! in_array($category, self::EXPENSE_CATEGORIES, true)) {
                throw ValidationException::withMessages(['category' => 'Categoria de despesa inválida.']);
            }
        }

        $reference = $meta['reference'] ?? null;

        return DB::transaction(function () use ($restaurant, $type, $value, $method, $meta, $category, $reference) {
            if ($reference !== null) {
                $existing = CashMovement::query()->where('restaurant_id', $restaurant->id)->where('reference', $reference)->first();

                if ($existing) {
                    return $existing;
                }
            }

            $session = $this->session($restaurant, $meta['session'] ?? null);

            /** @var Order|null $order */
            $order = $meta['order'] ?? null;
            /** @var User|null $user */
            $user = $meta['user'] ?? null;

            return CashMovement::query()->create([
                'restaurant_id' => $restaurant->id,
                'cash_session_id' => $session->id,
                'type_id' => CashMovementType::idFor($type),
                'payment_method_id' => PaymentMethod::idFor($method),
                'order_id' => $order?->id,
                'user_id' => $user?->id,
                'amount' => $value,
                'category' => $category,
                'description' => filled($meta['description'] ?? null) ? mb_substr(trim($meta['description']), 0, 255) : null,
                'reference' => $reference,
                'occurred_at' => now(),
            ]);
        });
    }

    private function session(Restaurant $restaurant, ?CashSession $given): CashSession
    {
        $session = CashSession::query()
            ->where('restaurant_id', $restaurant->id)
            ->when($given, fn ($query) => $query->whereKey($given->id), fn ($query) => $query->whereNull('closed_at'))
            ->lockForUpdate()
            ->first();

        if ($session === null || ! $session->isOpen()) {
            throw ValidationException::withMessages(['cash' => 'Abra o caixa para registrar este movimento.']);
        }

        return $session;
    }
}
