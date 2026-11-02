<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\PizzaFlavorPrice;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a pizza: its sizes, its flavors and the price of each flavor in each size, all in one
 * transaction (RF-14). A pizza without a single flavor that has a price is refused — it
 * would reach the cardápio as "R$ 0,00", the very failure seen in other systems. Sizes and
 * flavors keep their ids when edited, so open carts stay valid; the ones left out of the
 * lists are deleted with their prices. The product's own price becomes the cheapest price
 * ("a partir de").
 *
 * sizes:   [{id?, name, slices?, max_flavors, is_available?}]
 * flavors: [{id?, name, section?, ingredients?, is_available?, prices: {sizeIndex: "30,00"}}]
 */
class SavePizza
{
    public const MAX_FLAVORS_PER_SIZE = 4;

    public const SECTIONS = ['Tradicionais', 'Especiais', 'Doces'];

    /**
     * @param  array<int, array<string, mixed>>  $sizes
     * @param  array<int, array<string, mixed>>  $flavors
     *
     * @throws ValidationException
     */
    public function handle(Dish $dish, array $sizes, array $flavors): void
    {
        $sizes = array_values($sizes);
        $flavors = array_values($flavors);

        $this->validate($sizes, $flavors);

        DB::transaction(function () use ($dish, $sizes, $flavors) {
            $keptSizes = [];
            $sizeIds = [];

            foreach ($sizes as $index => $size) {
                $row = [
                    'name' => trim($size['name']),
                    'slices' => filled($size['slices'] ?? null) ? (int) $size['slices'] : null,
                    'max_flavors' => (int) ($size['max_flavors'] ?? 1),
                    'is_available' => (bool) ($size['is_available'] ?? true),
                    'display_order' => $index + 1,
                ];

                $model = isset($size['id']) ? $dish->pizzaSizes()->whereKey($size['id'])->first() : null;

                if ($model) {
                    $model->update($row);
                } else {
                    $model = $dish->pizzaSizes()->create($row);
                }

                $keptSizes[] = $model->id;
                $sizeIds[$index] = $model->id;
            }

            $dish->pizzaSizes()->whereNotIn('id', $keptSizes)->delete();

            $keptFlavors = [];
            $cheapest = null;

            foreach ($flavors as $index => $flavor) {
                $row = [
                    'name' => trim($flavor['name']),
                    'section' => filled($flavor['section'] ?? null) ? trim($flavor['section']) : self::SECTIONS[0],
                    'ingredients' => filled($flavor['ingredients'] ?? null) ? mb_substr(trim($flavor['ingredients']), 0, 255) : null,
                    'is_available' => (bool) ($flavor['is_available'] ?? true),
                    'display_order' => $index + 1,
                ];

                $model = isset($flavor['id']) ? $dish->pizzaFlavors()->whereKey($flavor['id'])->first() : null;

                if ($model) {
                    $model->update($row);
                } else {
                    $model = $dish->pizzaFlavors()->create($row);
                }

                $keptFlavors[] = $model->id;
                $keptPrices = [];

                foreach ($sizeIds as $sizeIndex => $sizeId) {
                    $price = Money::parse((string) ($flavor['prices'][$sizeIndex] ?? ''));

                    if ($price === null) {
                        continue;
                    }

                    PizzaFlavorPrice::query()->updateOrCreate(['pizza_flavor_id' => $model->id, 'pizza_size_id' => $sizeId], ['price' => $price]);
                    $keptPrices[] = $sizeId;
                    $cheapest = $cheapest === null ? $price : min((float) $cheapest, (float) $price);
                }

                PizzaFlavorPrice::query()->where('pizza_flavor_id', $model->id)->whereNotIn('pizza_size_id', $keptPrices)->delete();
            }

            $dish->pizzaFlavors()->whereNotIn('id', $keptFlavors)->delete();

            $dish->update(['kind' => 'pizza', 'sale_type' => 'unit', 'price' => number_format((float) $cheapest, 2, '.', '')]);
        });
    }

    /**
     * A product that stops being a pizza loses its sizes, flavors and prices.
     */
    public function clear(Dish $dish): void
    {
        DB::transaction(function () use ($dish) {
            $dish->pizzaFlavors()->delete();
            $dish->pizzaSizes()->delete();
            $dish->update(['kind' => 'common']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $sizes
     * @param  array<int, array<string, mixed>>  $flavors
     *
     * @throws ValidationException
     */
    private function validate(array $sizes, array $flavors): void
    {
        $errors = [];

        if ($sizes === []) {
            $errors['sizes'] = 'Cadastre pelo menos um tamanho.';
        }

        foreach ($sizes as $index => $size) {
            if (blank(trim((string) ($size['name'] ?? '')))) {
                $errors["sizes.{$index}.name"] = 'Dê um nome ao tamanho.';
            }

            $max = (int) ($size['max_flavors'] ?? 0);

            if ($max < 1 || $max > self::MAX_FLAVORS_PER_SIZE) {
                $errors["sizes.{$index}.max_flavors"] = 'Escolha de 1 a '.self::MAX_FLAVORS_PER_SIZE.' sabores.';
            }

            if (filled($size['slices'] ?? null) && ((int) $size['slices'] < 1 || (int) $size['slices'] > 32)) {
                $errors["sizes.{$index}.slices"] = 'Fatias de 1 a 32.';
            }
        }

        $priced = 0;

        foreach ($flavors as $index => $flavor) {
            $name = trim((string) ($flavor['name'] ?? ''));

            if ($name === '') {
                $errors["flavors.{$index}.name"] = 'Dê um nome ao sabor.';
            }

            $hasPrice = false;

            foreach ($sizes as $sizeIndex => $size) {
                $raw = (string) ($flavor['prices'][$sizeIndex] ?? '');

                if (blank($raw)) {
                    continue;
                }

                $price = Money::parse($raw);

                if ($price === null || (float) $price <= 0) {
                    $errors["flavors.{$index}.prices.{$sizeIndex}"] = 'Preço inválido. Use o formato 55,00.';
                } else {
                    $hasPrice = true;
                }
            }

            if ($name !== '' && ! $hasPrice && ! isset($errors["flavors.{$index}.prices"])) {
                $errors["flavors.{$index}.prices"] = "O sabor {$name} precisa de preço em pelo menos um tamanho.";
            }

            $priced += $hasPrice ? 1 : 0;
        }

        if ($priced === 0 && ! isset($errors['flavors'])) {
            $errors['flavors'] = 'Cadastre pelo menos um sabor com preço.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
