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
     * @param  array{category_id: int, name: string, price: string, short_description: ?string, description: ?string, badge_ids: array<int, int>, variants: array<int, array{name: string, price: string}>, option_group_ids?: array<int, int>, sale_type?: string, kind?: string, pizza?: array{sizes: array<int, array<string, mixed>>, flavors: array<int, array<string, mixed>>}, promo_price?: ?string, promo_ends_at?: ?string, is_featured?: bool}  $data
     */
    public function handle(Restaurant $restaurant, array $data, ?Dish $dish = null): Dish
    {
        return DB::transaction(function () use ($restaurant, $data, $dish) {
            $wasPizza = $dish?->isPizza() ?? false;

            $attributes = [
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'price' => $data['price'],
                'short_description' => $data['short_description'] ?: null,
                'description' => $data['description'] ?: null,
            ];

            // Advanced fields (RF-14–RF-16) are optional: callers that do not send them leave them as they are.
            foreach (['sale_type', 'kind', 'promo_price', 'promo_ends_at', 'is_featured'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

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

            // A pizza is saved with its sizes, flavors and prices in the same transaction (RF-14); a product that
            // stops being a pizza loses them.
            if (($data['kind'] ?? null) === 'pizza') {
                app(SavePizza::class)->handle($dish, $data['pizza']['sizes'] ?? [], $data['pizza']['flavors'] ?? []);
            } elseif (array_key_exists('kind', $data) && $wasPizza) {
                app(SavePizza::class)->clear($dish);
            }

            // Complements are optional: callers that do not send the key leave them as they are (RF-03).
            if (array_key_exists('option_group_ids', $data)) {
                app(SyncDishOptionGroups::class)->handle($dish, $data['option_group_ids']);
            }

            return $dish;
        });
    }
}
