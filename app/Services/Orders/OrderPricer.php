<?php

namespace App\Services\Orders;

use App\Actions\Orders\ApplyPromoCode;
use App\Exceptions\OrderRejectedException;
use App\Models\Dish;
use App\Models\DishVariant;
use App\Models\OptionGroup;
use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Support\BusinessHours;
use App\Support\DistanceFee;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Prices a cart from the database, never from what the browser sent (RF-05, RNF-02):
 * product or variation price, complements, quantities, delivery fee, free delivery and
 * the delivery minimum. Money is computed in integer cents. Anything that cannot be
 * accepted is collected and thrown together as an OrderRejectedException (RF-06e).
 *
 * Cart item shape:
 *   dish_id, quantity, notes?, variant_id?, options?: [{id, quantity}]
 */
class OrderPricer
{
    public const MAX_UNITS = 99;

    public const MAX_KG = 50.0;

    public const NOTES_LIMIT = 140;

    /** @var array<int, array<string, mixed>> */
    private array $issues = [];

    private ?float $distanceKm = null;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $delivery  fulfillment: entrega|retirada|balcao, zone_id?, neighborhood?
     *
     * @throws OrderRejectedException
     */
    public function price(Restaurant $restaurant, array $items, array $delivery = []): PricedOrder
    {
        $this->issues = [];
        $this->distanceKm = null;

        $dishes = $this->loadDishes($restaurant, $items);
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $line = $this->priceLine($restaurant, $dishes, $item, $index);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        if ($lines === [] && $this->issues === []) {
            $this->issues[] = ['type' => 'empty_cart', 'message' => 'O pedido está vazio.'];
        }

        $subtotalCents = array_sum(array_map(fn (array $line) => $this->cents($line['total']), $lines));
        $requested = $delivery['fulfillment'] ?? 'retirada';
        $fulfillment = in_array($requested, ['entrega', 'retirada', 'balcao'], true) ? $requested : 'retirada';

        [$feeCents, $pending, $neighborhood, $free, $missingCents] = $this->priceDelivery($restaurant, $fulfillment, $subtotalCents, $delivery);

        // A discount code (RF-17) is checked against the subtotal before delivery; it never reduces the
        // delivery minimum or the free-delivery threshold, which look at the subtotal as sold.
        $discountCents = 0;
        $promo = null;

        if (filled($delivery['promo_code'] ?? null) && $lines !== []) {
            try {
                ['promo' => $promo, 'discount' => $discountCents] = app(ApplyPromoCode::class)->check($restaurant, (string) $delivery['promo_code'], $subtotalCents);
            } catch (OrderRejectedException $exception) {
                $this->issues = [...$this->issues, ...$exception->issues];
            }
        }

        if ($this->issues !== []) {
            throw new OrderRejectedException($this->issues);
        }

        $totalCents = $subtotalCents + ($feeCents ?? 0) - $discountCents;

        return new PricedOrder(
            lines: $lines,
            subtotal: $this->money($subtotalCents),
            deliveryFee: $fulfillment === 'entrega' ? ($feeCents === null ? null : $this->money($feeCents)) : null,
            deliveryFeePending: $pending,
            discount: $this->money($discountCents),
            total: $this->money($totalCents),
            fulfillment: $fulfillment,
            neighborhood: $neighborhood,
            freeDelivery: $free,
            missingForFreeDelivery: $missingCents !== null ? $this->money($missingCents) : null,
            distanceKm: $this->distanceKm,
            promoCodeId: $promo?->id,
            promoCode: $promo?->code,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Dish>
     */
    private function loadDishes(Restaurant $restaurant, array $items)
    {
        $ids = collect($items)->pluck('dish_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();

        return Dish::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', $ids)
            ->with(['status', 'category', 'variants', 'optionGroups.options', 'pizzaSizes', 'pizzaFlavors.prices'])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Dish>  $dishes
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function priceLine(Restaurant $restaurant, $dishes, array $item, int $index): ?array
    {
        $dishId = isset($item['dish_id']) ? (int) $item['dish_id'] : 0;
        /** @var Dish|null $dish */
        $dish = $dishes->get($dishId);

        if ($dish === null) {
            $this->issues[] = ['type' => 'item_unavailable', 'dish_id' => $dishId ?: null, 'name' => null, 'message' => 'Um item do pedido não está mais no cardápio.'];

            return null;
        }

        if (! $this->isOrderable($restaurant, $dish)) {
            $this->issues[] = ['type' => 'item_unavailable', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$dish->name} não está disponível no momento."];

            return null;
        }

        $quantity = $this->quantity($dish, $item['quantity'] ?? 1);

        if ($quantity === null) {
            $this->issues[] = ['type' => 'invalid_quantity', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Quantidade inválida para {$dish->name}."];

            return null;
        }

        $notes = isset($item['notes']) ? trim((string) $item['notes']) : '';

        if (mb_strlen($notes) > self::NOTES_LIMIT) {
            $this->issues[] = ['type' => 'notes_too_long', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => 'A observação de '.$dish->name.' passa de '.self::NOTES_LIMIT.' caracteres.'];

            return null;
        }

        $before = count($this->issues);
        $pizza = null;
        $variant = null;

        if ($dish->isPizza()) {
            [$pizza, $unitCents] = $this->pricePizza($restaurant, $dish, $item);
        } else {
            $variant = $this->resolveVariant($dish, $item);
            $unitCents = $variant !== null ? $this->cents($variant->price) : $this->cents($dish->currentPrice());
        }

        [$complements, $extraCents] = $this->priceComplements($dish, $item['options'] ?? []);

        if (count($this->issues) > $before) {
            return null;
        }

        $unitCents += $extraCents;
        $totalCents = $this->multiply($unitCents, $quantity['milli']);

        return [
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'sale_type' => $quantity['sale_type'],
            'quantity' => number_format($quantity['milli'] / 1000, 3, '.', ''),
            'unit_price' => $this->money($unitCents),
            'total' => $this->money($totalCents),
            'notes' => $notes !== '' ? $notes : null,
            'selections' => [
                'icon' => $dish->category?->icon,
                'variant' => $variant ? ['id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price] : null,
                'pizza' => $pizza,
                'complements' => $complements,
            ],
            'display_order' => $index + 1,
        ];
    }

    /**
     * A product is orderable while it is active (not "esgotado"/hidden), its category is
     * visible and inside its own time window (RF-02b, RF-06e).
     */
    private function isOrderable(Restaurant $restaurant, Dish $dish): bool
    {
        if ($dish->status?->slug !== 'ativo') {
            return false;
        }

        $category = $dish->category;

        return $category !== null
            && $category->is_visible
            && $category->isShownAt(BusinessHours::localNow($restaurant));
    }

    /**
     * @return array{milli: int, sale_type: string}|null
     */
    private function quantity(Dish $dish, mixed $raw): ?array
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        if (($dish->sale_type ?? 'unit') === 'kg') {
            $milli = (int) round($value * 1000);

            return ($value >= 0.5 && $value <= self::MAX_KG && $milli % 500 === 0) ? ['milli' => $milli, 'sale_type' => 'kg'] : null;
        }

        return ($value >= 1 && $value <= self::MAX_UNITS && floor($value) === $value) ? ['milli' => (int) $value * 1000, 'sale_type' => 'unit'] : null;
    }

    /**
     * A pizza costs what its size and flavors say (RF-14, RF-14a): the size must be on sale and
     * accept that many flavors; every flavor must be on sale and exist in that size. With more
     * than one flavor the price follows the store's rule — the most expensive flavor
     * ("maior", the pizzeria standard) or the average ("media", rounded half up).
     *
     * @param  array<string, mixed>  $item
     * @return array{0: ?array<string, mixed>, 1: int}
     */
    private function pricePizza(Restaurant $restaurant, Dish $dish, array $item): array
    {
        $size = $dish->pizzaSizes->firstWhere('id', (int) ($item['size_id'] ?? 0));

        if ($size === null || ! $size->is_available) {
            $this->issues[] = ['type' => 'missing_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Escolha o tamanho de {$dish->name}."];

            return [null, 0];
        }

        $ids = array_values(array_unique(array_map('intval', (array) ($item['flavor_ids'] ?? []))));

        if ($ids === []) {
            $this->issues[] = ['type' => 'missing_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Escolha o sabor de {$dish->name}."];

            return [null, 0];
        }

        if (count($ids) > $size->max_flavors) {
            $this->issues[] = ['type' => 'invalid_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$dish->name} tamanho {$size->name}: escolha no máximo {$size->max_flavors} ".($size->max_flavors === 1 ? 'sabor' : 'sabores').'.'];

            return [null, 0];
        }

        $flavors = [];
        $prices = [];

        foreach ($ids as $id) {
            $flavor = $dish->pizzaFlavors->firstWhere('id', $id);
            $price = $flavor?->prices->firstWhere('pizza_size_id', $size->id);

            if ($flavor === null || ! $flavor->is_available || $price === null) {
                $name = $flavor?->name ?? 'Um sabor';
                $this->issues[] = ['type' => 'item_unavailable', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$name} não está disponível em {$dish->name} tamanho {$size->name}."];

                return [null, 0];
            }

            $flavors[] = ['id' => $flavor->id, 'name' => $flavor->name, 'price' => $price->price];
            $prices[] = $this->cents($price->price);
        }

        $rule = $restaurant->orderSettings()->pizza_pricing_rule === 'media' ? 'media' : 'maior';
        $unit = $rule === 'media' ? intdiv(array_sum($prices) * 2 + count($prices), count($prices) * 2) : max($prices);

        return [
            ['size' => ['id' => $size->id, 'name' => $size->name, 'slices' => $size->slices], 'flavors' => $flavors, 'rule' => $rule],
            $unit,
        ];
    }

    /**
     * Variations are alternative prices (P/M/G): when the product has them, one must
     * be chosen; without variations the product's own price applies.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveVariant(Dish $dish, array $item): ?DishVariant
    {
        if ($dish->variants->isEmpty()) {
            return null;
        }

        $variant = isset($item['variant_id']) ? $dish->variants->firstWhere('id', (int) $item['variant_id']) : null;

        if ($variant === null) {
            $this->issues[] = ['type' => 'missing_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Escolha uma opção de {$dish->name}."];
        }

        return $variant;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selected  [{id, quantity}]
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function priceComplements(Dish $dish, array $selected): array
    {
        $chosen = [];

        foreach ($selected as $entry) {
            $id = (int) ($entry['id'] ?? 0);
            $qty = (int) ($entry['quantity'] ?? 1);
            $chosen[$id] = ($chosen[$id] ?? 0) + max($qty, 0);
        }

        $complements = [];
        $extraCents = 0;
        $seen = [];

        foreach ($dish->optionGroups->where('is_active', true) as $group) {
            /** @var OptionGroup $group */
            $total = 0;

            foreach ($group->options as $option) {
                $qty = $chosen[$option->id] ?? 0;

                if ($qty === 0) {
                    continue;
                }

                $seen[] = $option->id;

                if (! $option->is_available) {
                    $this->issues[] = ['type' => 'item_unavailable', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$option->name} não está disponível em {$dish->name}."];

                    continue;
                }

                if ($qty > 1 && ! $group->allow_repeat) {
                    $this->issues[] = ['type' => 'invalid_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$group->name}: {$option->name} só pode ser escolhido uma vez."];

                    continue;
                }

                $total += $qty;
                $extraCents += $this->cents($option->price) * $qty;
                $complements[] = [
                    'group_id' => $group->id,
                    'group' => $group->name,
                    'option_id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price,
                    'quantity' => $qty,
                ];
            }

            if ($group->is_required && $total === 0) {
                $this->issues[] = ['type' => 'missing_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Escolha {$group->name} de {$dish->name}."];
            }

            $limit = $group->mode === 'one' ? 1 : $group->max_choices;

            if ($total > $limit) {
                $this->issues[] = ['type' => 'invalid_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "{$group->name}: escolha no máximo {$limit}."];
            }
        }

        foreach (array_keys($chosen) as $optionId) {
            if (($chosen[$optionId] ?? 0) > 0 && ! in_array($optionId, $seen, true)) {
                $this->issues[] = ['type' => 'invalid_choice', 'dish_id' => $dish->id, 'name' => $dish->name, 'message' => "Uma opção escolhida não pertence a {$dish->name}."];
            }
        }

        return [$complements, $extraCents];
    }

    /**
     * Delivery fee, free-delivery threshold and the delivery minimum (RF-06b).
     * The minimum counts the subtotal before any discount (SPEC Q-06b).
     *
     * @param  array<string, mixed>  $delivery
     * @return array{0: ?int, 1: bool, 2: ?string, 3: bool, 4: ?int} fee cents (null = a combinar), pending, neighborhood, free, missing cents
     */
    private function priceDelivery(Restaurant $restaurant, string $fulfillment, int $subtotalCents, array $delivery): array
    {
        if ($fulfillment !== 'entrega') {
            return [0, false, null, false, null];
        }

        $settings = $restaurant->orderSettings();

        $minimum = $settings->min_order_delivery !== null ? $this->cents($settings->min_order_delivery) : 0;

        if ($minimum > 0 && $subtotalCents < $minimum) {
            $this->issues[] = [
                'type' => 'min_order_delivery',
                'message' => 'O pedido mínimo para entrega é '.Money::brl($this->money($minimum)).'. Abaixo disso só é possível retirar na loja.',
                'amount' => $this->money($minimum),
            ];
        }

        $neighborhood = null;
        $feeCents = null;
        $pending = true;

        if ($settings->delivery_mode === 'bairro') {
            $zones = $restaurant->deliveryZones()->where('is_active', true)->get();
            $zone = isset($delivery['zone_id']) ? $zones->firstWhere('id', (int) $delivery['zone_id']) : null;
            $zone ??= isset($delivery['neighborhood']) ? $zones->first(fn ($candidate) => mb_strtolower($candidate->name) === mb_strtolower(trim((string) $delivery['neighborhood']))) : null;

            if ($zone !== null) {
                $feeCents = $this->cents($zone->fee);
                $pending = false;
                $neighborhood = $zone->name;
            } elseif ($zones->isEmpty() || $settings->accepts_other_neighborhood) {
                $neighborhood = isset($delivery['neighborhood']) && trim((string) $delivery['neighborhood']) !== '' ? trim((string) $delivery['neighborhood']) : null;
            } else {
                $this->issues[] = ['type' => 'zone_not_served', 'message' => 'Não entregamos nesse bairro. Escolha um bairro da lista ou retire na loja.'];
            }
        } else {
            $neighborhood = isset($delivery['neighborhood']) && trim((string) $delivery['neighborhood']) !== '' ? trim((string) $delivery['neighborhood']) : null;

            if ($settings->delivery_mode === 'distancia') {
                $this->distanceKm = $this->distance($settings, $delivery);
                $fee = DistanceFee::fee($restaurant->distanceTiers()->get()->toArray(), $this->distanceKm);

                // Outside every range, or without a location: the fee is agreed on WhatsApp.
                if ($fee !== null) {
                    $feeCents = $this->cents($fee);
                    $pending = false;
                }
            }
        }

        $threshold = $settings->free_delivery_over !== null ? $this->cents($settings->free_delivery_over) : 0;
        $free = false;
        $missing = null;

        if ($threshold > 0) {
            if ($subtotalCents >= $threshold) {
                $feeCents = 0;
                $pending = false;
                $free = true;
            } else {
                $missing = $threshold - $subtotalCents;
            }
        }

        return [$feeCents, $pending, $neighborhood, $free, $missing];
    }

    /** Distance in km from the store to the customer's location, or null when either is unknown. */
    private function distance(RestaurantOrderSetting $settings, array $delivery): ?float
    {
        if ($settings->lat === null || $settings->lng === null || ! isset($delivery['lat'], $delivery['lng'])
            || ! is_numeric($delivery['lat']) || ! is_numeric($delivery['lng'])
            || abs((float) $delivery['lat']) > 90 || abs((float) $delivery['lng']) > 180) {
            return null;
        }

        return DistanceFee::km((float) $settings->lat, (float) $settings->lng, (float) $delivery['lat'], (float) $delivery['lng']);
    }

    private function cents(string|float|int|null $amount): int
    {
        return $amount === null ? 0 : (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * unit cents × quantity in thousandths, rounded half up, without floats.
     */
    private function multiply(int $unitCents, int $milli): int
    {
        return intdiv($unitCents * $milli + 500, 1000);
    }
}
