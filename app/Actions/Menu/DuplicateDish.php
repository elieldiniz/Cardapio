<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Copies a product with its category, price, texts, variations, badges and complement
 * groups (RF-06f). The video and the photos are not copied: the copy has none until the
 * dono creates them. Blocked at the plan's product limit (RF-25).
 */
class DuplicateDish
{
    /**
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, Dish $dish): Dish
    {
        abort_unless($dish->restaurant_id === $restaurant->id, 404);

        if (! $restaurant->canAddDish()) {
            throw ValidationException::withMessages(['dish' => 'Limite de produtos do plano atingido.']);
        }

        return DB::transaction(function () use ($restaurant, $dish) {
            $copy = $restaurant->dishes()->create([
                'category_id' => $dish->category_id,
                'status_id' => DishStatus::idFor('ativo'),
                'name' => Str::limit($dish->name, 106, '').' (cópia)',
                'price' => $dish->price,
                'short_description' => $dish->short_description,
                'description' => $dish->description,
                'display_order' => (int) $restaurant->dishes()->max('display_order') + 1,
            ]);

            $copy->badges()->sync($dish->badges()->pluck('badges.id')->all());

            foreach ($dish->variants as $variant) {
                $copy->variants()->create(['name' => $variant->name, 'price' => $variant->price]);
            }

            $copy->optionGroups()->sync(
                $dish->optionGroups->mapWithKeys(fn ($group) => [$group->id => ['display_order' => $group->pivot->display_order]])->all(),
            );

            return $copy;
        });
    }
}
