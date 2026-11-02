<?php

namespace App\Actions\Couriers;

use App\Models\Courier;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a courier (RF-22).
 */
class SaveCourier
{
    /**
     * @param  array{name: string, phone?: ?string, fee_per_delivery?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, ?Courier $courier = null): Courier
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));
        $fee = filled($data['fee_per_delivery'] ?? null) ? Money::parse((string) $data['fee_per_delivery']) : '0.00';

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Informe o nome do motoboy.';
        }

        if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 13)) {
            $errors['phone'] = 'Informe o WhatsApp com DDD.';
        }

        if ($fee === null) {
            $errors['fee_per_delivery'] = 'Valor por entrega inválido.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $attributes = [
            'name' => $name,
            'phone' => $phone === '' ? null : (strlen($phone) <= 11 ? '55'.$phone : $phone),
            'fee_per_delivery' => $fee,
        ];

        if ($courier === null) {
            return $restaurant->couriers()->create($attributes);
        }

        $courier->update($attributes);

        return $courier;
    }
}
