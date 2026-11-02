<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyProgram;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the store's single loyalty program (RF-18).
 */
class SaveLoyaltyProgram
{
    /**
     * @param  array{is_active?: bool, stamps_required: int|string, min_order?: ?string, reward_type: string, reward_value?: ?string, reward_label?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data): LoyaltyProgram
    {
        $stamps = (int) ($data['stamps_required'] ?? 0);
        $type = $data['reward_type'] ?? 'brinde';
        $value = filled($data['reward_value'] ?? null) ? Money::parse((string) $data['reward_value']) : null;
        $minOrder = filled($data['min_order'] ?? null) ? Money::parse((string) $data['min_order']) : null;
        $label = filled($data['reward_label'] ?? null) ? mb_substr(trim($data['reward_label']), 0, 80) : null;

        $errors = [];

        if ($stamps < 2 || $stamps > 50) {
            $errors['stamps_required'] = 'Escolha de 2 a 50 compras para completar o cartão.';
        }

        if (filled($data['min_order'] ?? null) && $minOrder === null) {
            $errors['min_order'] = 'Pedido mínimo inválido.';
        }

        if (! in_array($type, ['percentual', 'valor', 'brinde'], true)) {
            $errors['reward_type'] = 'Escolha o prêmio.';
        } elseif ($type !== 'brinde' && ($value === null || (float) $value <= 0)) {
            $errors['reward_value'] = 'Informe o valor do prêmio.';
        } elseif ($type === 'percentual' && (float) $value > 100) {
            $errors['reward_value'] = 'A porcentagem não pode passar de 100.';
        } elseif ($type === 'brinde' && $label === null) {
            $errors['reward_label'] = 'Diga qual é o brinde.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return LoyaltyProgram::updateOrCreate(['restaurant_id' => $restaurant->id], [
            'is_active' => $data['is_active'] ?? true,
            'stamps_required' => $stamps,
            'min_order' => $minOrder,
            'reward_type' => $type,
            'reward_value' => $type === 'brinde' ? null : $value,
            'reward_label' => $type === 'brinde' ? $label : null,
        ]);
    }
}
