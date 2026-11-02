<?php

namespace App\Services\Orders;

use App\Support\Money;

/**
 * Turns the choices stored on an order item (variation, complements) into the short
 * lines shown on the WhatsApp message, the tracking page, the manager card and the
 * kitchen ticket, so every place reads the same.
 */
class SelectionDescriber
{
    /**
     * @param  array<string, mixed>|null  $selections
     * @return array<int, string>
     */
    public static function lines(?array $selections): array
    {
        $selections ??= [];
        $lines = [];

        if (! empty($selections['pizza'])) {
            $pizza = $selections['pizza'];
            $slices = ! empty($pizza['size']['slices']) ? " ({$pizza['size']['slices']} fatias)" : '';
            $lines[] = 'Tamanho: '.$pizza['size']['name'].$slices;

            $count = count($pizza['flavors']);
            $fraction = [1 => '', 2 => '½ ', 3 => '⅓ ', 4 => '¼ '][$count] ?? '';

            $lines[] = ($count === 1 ? 'Sabor: ' : 'Sabores: ').collect($pizza['flavors'])->map(fn (array $flavor) => $fraction.$flavor['name'])->implode(', ');
        }

        if (! empty($selections['variant']['name'])) {
            $lines[] = 'Opção: '.$selections['variant']['name'];
        }

        $byGroup = [];

        foreach ($selections['complements'] ?? [] as $complement) {
            $byGroup[$complement['group']][] = $complement;
        }

        foreach ($byGroup as $group => $complements) {
            $lines[] = $group.': '.collect($complements)->map(function (array $complement) {
                $price = $complement['price'] !== null && (float) $complement['price'] > 0
                    ? ' (+'.Money::brl((float) $complement['price'] * $complement['quantity']).')'
                    : '';

                return ($complement['quantity'] > 1 ? $complement['quantity'].'× ' : '').$complement['name'].$price;
            })->implode(', ');
        }

        return $lines;
    }
}
