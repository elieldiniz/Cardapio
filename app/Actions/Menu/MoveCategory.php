<?php

namespace App\Actions\Menu;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Drag-to-reorder (US-2.1): moves a category to a new zero-based position and
 * renumbers the restaurant's categories so display_order stays contiguous.
 */
class MoveCategory
{
    public function handle(Category $category, int $position): void
    {
        DB::transaction(function () use ($category, $position) {
            $ids = Category::query()
                ->where('restaurant_id', $category->restaurant_id)
                ->orderBy('display_order')
                ->orderBy('id')
                ->pluck('id')
                ->reject(fn (int $id) => $id === $category->id)
                ->values()
                ->all();

            $position = max(0, min($position, count($ids)));
            array_splice($ids, $position, 0, [$category->id]);

            foreach ($ids as $order => $id) {
                Category::query()->whereKey($id)->update(['display_order' => $order]);
            }
        });
    }
}
