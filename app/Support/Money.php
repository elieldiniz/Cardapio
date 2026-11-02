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

    /**
     * Parse a price typed by the dono ("32,90", "R$ 1.234,50", "32.90") into a
     * decimal string ("32.90"), or null when it is not a valid amount.
     */
    public static function parse(?string $input): ?string
    {
        $value = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $input));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            // Brazilian format: dots are thousands separators, comma is the decimal mark.
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Like parse(), for a price pasted or typed into a single quick field (RF-06f): a whole
     * number of 4 or more digits with no separator is read as cents, so "3290" means
     * R$ 32,90 and never R$ 3.290,00. Anything with a comma, a dot or fewer digits goes
     * through parse() unchanged ("32,90", "R$ 32,90", "32", "1.234,50").
     */
    public static function parsePrice(?string $input): ?string
    {
        $clean = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $input));

        if (preg_match('/^\d{4,}$/', $clean)) {
            return number_format(((int) $clean) / 100, 2, '.', '');
        }

        return static::parse($input);
    }

    /**
     * Format a stored decimal for an editable input, e.g. "32.90" → "32,90".
     */
    public static function input(float|int|string|null $amount): string
    {
        return $amount === null ? '' : number_format((float) $amount, 2, ',', '.');
    }
}
