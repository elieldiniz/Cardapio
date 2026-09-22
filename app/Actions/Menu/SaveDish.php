<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a dish with its badges and variants (US-2.2). Editing
 * never touches the dish's approved video (US-2.3).
 */
class SaveDish
{
    /**
     * @param  array{category_id: int, name: string, price: string, short_description: ?string, description: ?string, badge_ids: array<int, int>, variants: array<int, array{name: string, price: string}>}  $data
     */
    public function handle(Restaurant $restaurant, array $data, ?Dish $dish = null): Dish
    {
        return DB::transaction(function () use ($restaurant, $data, $dish) {
            $attributes = [
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'price' => $data['price'],
                'short_description' => $data['short_description'] ?: null,
                'description' => $data['description'] ?: null,
            ];

            if ($dish === null) {
                $dish = $restaurant->dishes()->create($attributes + [
                    'status_id' => DishStatus::idFor('ativo'),
                    'display_order' => (int) $restaurant->dishes()->max('display_order') + 1,
                ]);
            } else {
                $dish->update($attributes);
            }

            $dish->badges()->sync($data['badge_ids']);

            $dish->variants()->delete();
            foreach ($data['variants'] as $variant) {
                $dish->variants()->create(['name' => $variant['name'], 'price' => $variant['price']]);
            }

            return $dish;
        });
    }
}
