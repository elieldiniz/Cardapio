<?php

namespace App\Actions\Marketing;

use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates one of the restaurant's discount codes (RF-17). The code is stored in
 * upper case and is unique within the store; the screen only collects the fields.
 */
class SavePromoCode
{
    /**
     * @param  array{code: string, type: string, value: string, min_order?: ?string, max_uses?: int|string|null, starts_on?: ?string, ends_on?: ?string, description?: ?string, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, ?PromoCode $promo = null): PromoCode
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($data['code'] ?? '')));
        $type = $data['type'] ?? 'percentual';
        $value = Money::parse((string) ($data['value'] ?? ''));
        $minOrder = filled($data['min_order'] ?? null) ? Money::parse((string) $data['min_order']) : null;
        $maxUses = filled($data['max_uses'] ?? null) ? (int) $data['max_uses'] : null;
        $startsOn = filled($data['starts_on'] ?? null) ? $data['starts_on'] : null;
        $endsOn = filled($data['ends_on'] ?? null) ? $data['ends_on'] : null;

        $errors = [];

        if (strlen($code) < 3 || strlen($code) > 30) {
            $errors['code'] = 'O código precisa ter de 3 a 30 letras ou números.';
        } elseif ($restaurant->promoCodes()->where('code', $code)->when($promo, fn ($query) => $query->whereKeyNot($promo->id))->exists()) {
            $errors['code'] = 'Você já tem um cupom com esse código.';
        }

        if (! in_array($type, ['percentual', 'valor'], true)) {
            $errors['type'] = 'Escolha o tipo do desconto.';
        }

        if ($value === null || (float) $value <= 0) {
            $errors['value'] = 'Informe o valor do desconto.';
        } elseif ($type === 'percentual' && (float) $value > 100) {
            $errors['value'] = 'A porcentagem não pode passar de 100.';
        }

        if (filled($data['min_order'] ?? null) && $minOrder === null) {
            $errors['min_order'] = 'Pedido mínimo inválido.';
        }

        if ($maxUses !== null && $maxUses < 1) {
            $errors['max_uses'] = 'O limite de usos precisa ser pelo menos 1.';
        }

        if ($startsOn && $endsOn && $endsOn < $startsOn) {
            $errors['ends_on'] = 'O fim da validade não pode ser antes do início.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $attributes = [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'min_order' => $minOrder,
            'max_uses' => $maxUses,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'description' => filled($data['description'] ?? null) ? mb_substr(trim($data['description']), 0, 120) : null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($promo === null) {
            return $restaurant->promoCodes()->create($attributes);
        }

        $promo->update($attributes);

        return $promo;
    }
}
