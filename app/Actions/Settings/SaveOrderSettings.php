<?php

namespace App\Actions\Settings;

use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves the order-taking settings of one restaurant (RF-01, RF-02, RF-02e, RF-06b).
 * Only the keys present in `$data` change, so each settings section can save on its own.
 * Money goes through `Money::parse` (RNF-02); the number is normalised to digits with the
 * Brazilian country code, ready for wa.me links.
 */
class SaveOrderSettings
{
    public const DELIVERY_MODES = ['a_combinar', 'bairro', 'distancia'];

    public const PIZZA_RULES = ['maior', 'media'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data): RestaurantOrderSetting
    {
        $validated = Validator::make($data, [
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'pix_key' => ['nullable', 'string', 'max:140'],
            'pix_holder' => ['nullable', 'string', 'max:60'],
            'pix_city' => ['nullable', 'string', 'max:40'],
            'accepts_orders' => ['nullable', 'boolean'],
            'hours_enabled' => ['nullable', 'boolean'],
            'accepts_when_closed' => ['nullable', 'boolean'],
            'accepts_other_neighborhood' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
            'delivery_mode' => ['nullable', 'string', 'in:'.implode(',', self::DELIVERY_MODES)],
            'free_delivery_over' => ['nullable', 'string'],
            'min_order_delivery' => ['nullable', 'string'],
            'pizza_pricing_rule' => ['nullable', 'string', 'in:'.implode(',', self::PIZZA_RULES)],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'timezone.in' => 'Fuso horário inválido.',
            'delivery_mode.in' => 'Escolha como a taxa de entrega é calculada.',
        ])->after(function ($validator) use ($data) {
            if (filled($data['whatsapp_number'] ?? null) && self::normalizeWhatsApp((string) $data['whatsapp_number']) === null) {
                $validator->errors()->add('whatsapp_number', 'WhatsApp inválido. Use DDD + número, ex.: (69) 99604-1447.');
            }

            foreach (['free_delivery_over', 'min_order_delivery'] as $field) {
                if (filled($data[$field] ?? null) && Money::parse((string) $data[$field]) === null) {
                    $validator->errors()->add($field, 'Valor inválido. Use o formato 80,00.');
                }
            }
        })->validate();

        $attributes = [];

        foreach (['address', 'pix_key', 'pix_holder', 'pix_city', 'timezone', 'delivery_mode', 'pizza_pricing_rule', 'lat', 'lng'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = filled($validated[$field]) ? $validated[$field] : null;
            }
        }

        foreach (['accepts_orders', 'hours_enabled', 'accepts_when_closed', 'accepts_other_neighborhood'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $attributes[$field] = (bool) $validated[$field];
            }
        }

        if (array_key_exists('whatsapp_number', $validated)) {
            $attributes['whatsapp_number'] = filled($validated['whatsapp_number'])
                ? self::normalizeWhatsApp((string) $validated['whatsapp_number'])
                : null;
        }

        foreach (['free_delivery_over', 'min_order_delivery'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = filled($validated[$field]) ? Money::parse((string) $validated[$field]) : null;
            }
        }

        return DB::transaction(fn () => RestaurantOrderSetting::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id],
            $attributes,
        ));
    }

    /**
     * Digits only, with the Brazilian country code: 10–11 digits get "55" in front,
     * 12–13 digits must already start with it. Anything else is invalid (null).
     */
    public static function normalizeWhatsApp(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            $digits = '55'.$digits;
        }

        return preg_match('/^55\d{10,11}$/', $digits) ? $digits : null;
    }
}
