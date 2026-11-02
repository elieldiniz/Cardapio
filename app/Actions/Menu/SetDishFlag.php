<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use Illuminate\Validation\ValidationException;

/**
 * The two switches of the product list (RF-06f), expressed on the existing status:
 *  - "available": ativo ⇄ esgotado ("Indisponível no momento"); a hidden product stays hidden;
 *  - "visible": oculto ⇄ ativo ("Mostrar no catálogo"); showing again respects the plan limit (US-5.5).
 */
class SetDishFlag
{
    /**
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, Dish $dish, string $flag, bool $value): Dish
    {
        abort_unless($dish->restaurant_id === $restaurant->id, 404);

        $current = $dish->status->slug;

        if ($flag === 'available') {
            if ($current === 'oculto') {
                return $dish;
            }

            $dish->update(['status_id' => DishStatus::idFor($value ? 'ativo' : 'esgotado')]);

            return $dish->refresh();
        }

        if ($flag !== 'visible') {
            throw ValidationException::withMessages(['flag' => 'Interruptor inválido.']);
        }

        if ($value && $current === 'oculto' && ! $restaurant->canShowAnotherDish()) {
            throw ValidationException::withMessages([
                'flag' => 'O plano '.$restaurant->plan->name.' permite '.$restaurant->plan->dish_limit.' produtos visíveis. Faça upgrade ou oculte outro produto.',
            ]);
        }

        if (! $value) {
            $dish->update(['status_id' => DishStatus::idFor('oculto')]);
        } elseif ($current === 'oculto') {
            $dish->update(['status_id' => DishStatus::idFor('ativo')]);
        }

        return $dish->refresh();
    }
}
