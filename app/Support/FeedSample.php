<?php

namespace App\Support;

use App\Models\Restaurant;

/**
 * Sample cardápio from the "Cardápio Fumaça - Feed" mockup. Used by the dev
 * feed preview and by the appearance preview while a restaurant has no
 * published dish yet.
 */
class FeedSample
{
    /**
     * @return array<string, array{name: string, dishes: array<int, array<string, mixed>>}>
     */
    private static function menu(): array
    {
        return [
            'burgers' => ['name' => 'Burgers', 'dishes' => [
                ['name' => 'Burger Clássico', 'price' => 'R$ 32,90', 'short_description' => 'Pão brioche, blend 180g, queijo cheddar, picles e maionese da casa.', 'description' => 'Blend de 180g grelhado no ponto, queijo cheddar derretido, picles artesanais e maionese da casa, servido no pão brioche tostado na manteiga. Acompanha batata rústica.', 'badges' => ['Mais pedido']],
                ['name' => 'Burger Fumaça', 'price' => 'R$ 39,90', 'short_description' => 'Blend 180g, queijo gouda fumado, cebola caramelizada e bacon crocante.', 'description' => 'Blend 180g grelhado na brasa, queijo gouda defumado na casa, cebola caramelizada lentamente e bacon crocante, com toque de fumaça em cada camada.', 'badges' => ['Novo']],
            ]],
            'fumados' => ['name' => 'Fumados', 'dishes' => [
                ['name' => 'Costela 12h', 'price' => 'R$ 68,90', 'short_description' => 'Costela bovina defumada por 12 horas, finalizada na brasa, molho barbecue.', 'description' => 'Costela bovina defumada lentamente por 12 horas em fumeiro próprio, finalizada na brasa e servida com molho barbecue da casa e farofa crocante.', 'badges' => ['Mais pedido']],
                ['name' => 'Peito Fumado', 'price' => 'R$ 54,90', 'short_description' => 'Peito bovino defumado lentamente, fatiado fino, servido com farofa.', 'description' => 'Peito bovino defumado por horas até ficar macio e suculento, fatiado fino e servido com farofa crocante e vinagrete da casa.', 'badges' => [], 'sold_out' => true],
            ]],
            'bebidas' => ['name' => 'Bebidas', 'dishes' => [
                ['name' => 'Limonada Suíça', 'price' => 'R$ 10,90', 'short_description' => 'Limonada batida com leite condensado e hortelã.', 'description' => 'Limonada batida na hora com leite condensado e folhas de hortelã fresca, bem gelada.', 'badges' => [], 'variants' => [['name' => 'P', 'price' => 'R$ 10,90'], ['name' => 'G', 'price' => 'R$ 14,90']]],
            ]],
        ];
    }

    /**
     * View data for feed/show.blade.php.
     *
     * @return array<string, mixed>
     */
    public static function page(): array
    {
        $menu = static::menu();
        $first = array_key_first($menu);

        return [
            'restaurant' => [
                'name' => 'Fumaça',
                'description' => 'Churrascaria & fumeiro contemporâneo. Carnes defumadas 12h na casa.',
                'slug' => 'fumaca',
                'logo_url' => null,
                'cover_url' => null,
                'accent' => Restaurant::DEFAULT_ACCENT_COLOR,
                'font' => null,
                'show_branding' => true,
            ],
            'categories' => collect($menu)->map(fn (array $category, string $id) => [
                'id' => $id,
                'name' => $category['name'],
                'url' => route('dev.feed.category', $id),
            ])->values()->all(),
            'activeCategoryId' => $first,
            'dishes' => static::dishes($first),
            'grid' => collect(array_keys($menu))
                ->flatMap(fn (string $categoryId) => static::dishes($categoryId))
                ->map(fn (array $dish) => collect($dish)->only(['id', 'category_id', 'name', 'sold_out', 'thumb_url'])->all())
                ->all(),
            'trackUrl' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function dishes(string $categoryId): array
    {
        return collect(static::menu()[$categoryId]['dishes'] ?? [])
            ->map(fn (array $dish, int $index) => [
                'id' => "{$categoryId}-{$index}",
                'category_id' => $categoryId,
                'name' => $dish['name'],
                'price' => $dish['price'],
                'short_description' => $dish['short_description'],
                'description' => $dish['description'],
                'badges' => $dish['badges'],
                'sold_out' => $dish['sold_out'] ?? false,
                'variants' => $dish['variants'] ?? [],
                'cover_url' => null,
                'thumb_url' => null,
                'video_url' => null,
                'video_url_hd' => null,
            ])->all();
    }
}
