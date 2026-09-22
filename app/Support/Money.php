<?php

namespace App\Support;

class Money
{
    /**
     * Format a decimal amount in reais as shown to customers, e.g. "R$ 32,90".
     */
    public static function brl(float|int|string|null $amount): string
    {
        return 'R$ '.number_format((float) $amount, 2, ',', '.');
    }

    /**
     * Format an integer amount in cents (Stripe's native unit) as reais.
     */
    public static function brlFromCents(int $cents): string
    {
        return static::brl($cents / 100);
    }
}
