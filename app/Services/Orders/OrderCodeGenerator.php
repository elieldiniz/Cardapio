<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The short code the dono and the customer read out loud ("D-X6ECG") and the long
 * random token that guards the public tracking page (RF-06, RF-07a). The code is
 * unique per store; characters that look alike (0/O, 1/I) are left out.
 */
class OrderCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 5;

    private const ATTEMPTS = 20;

    public function code(Restaurant $restaurant): string
    {
        $prefix = $this->prefix($restaurant);

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $code = $prefix.'-'.$this->random();

            if (! Order::query()->where('restaurant_id', $restaurant->id)->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Não foi possível gerar um código de pedido único.');
    }

    public function token(): string
    {
        do {
            $token = Str::random(32);
        } while (Order::query()->where('tracking_token', $token)->exists());

        return $token;
    }

    private function prefix(Restaurant $restaurant): string
    {
        $letter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($restaurant->name)) ?? '', 0, 1));

        return $letter !== '' ? $letter : 'P';
    }

    private function random(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
