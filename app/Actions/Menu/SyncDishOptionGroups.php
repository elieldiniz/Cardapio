<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links complement groups to a dish, keeping the order the dono chose (RF-03).
 * A group of another restaurant is rejected (RNF-01). Runs inside the caller's
 * transaction when there is one, so a product and its groups save together.
 */
class SyncDishOptionGroups
{
    /**
     * @param  array<int, int|string>  $groupIds  in display order
     *
     * @throws ValidationException
     */
    public function handle(Dish $dish, array $groupIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $groupIds)));

        $owned = OptionGroup::query()
            ->where('restaurant_id', $dish->restaurant_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($owned) !== count($ids)) {
            throw ValidationException::withMessages([
                'option_group_ids' => 'Um dos grupos de complementos não pertence ao seu restaurante.',
            ]);
        }

        DB::transaction(function () use ($dish, $ids) {
            $dish->optionGroups()->sync(
                collect($ids)->mapWithKeys(fn (int $id, int $index) => [$id => ['display_order' => $index + 1]])->all(),
            );
        });
    }
}
