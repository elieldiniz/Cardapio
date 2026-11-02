<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Changes a product's price straight from the list (RF-06f). Accepts "R$ 32,90", "32,90"
 * and "3290" (cents) through Money::parsePrice; a past order keeps its own snapshot.
 */
class UpdateDishPrice
{
    /**
     * @throws ValidationException
     */
    public function handle(Dish $dish, ?string $input): string
    {
        $price = Money::parsePrice($input);

        if ($price === null || (float) $price <= 0) {
            throw ValidationException::withMessages(['price' => 'Preço inválido. Use o formato 32,90.']);
        }

        $dish->update(['price' => $price]);

        return $price;
    }
}
