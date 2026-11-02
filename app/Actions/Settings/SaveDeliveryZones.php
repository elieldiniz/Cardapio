<?php

namespace App\Actions\Settings;

use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves the delivery setup of a restaurant (RF-06b): how the fee is calculated, the
 * neighborhoods with their fees, the free-delivery threshold, the minimum order for
 * delivery and whether a neighborhood outside the list may still order. Zones keep
 * their ids when edited; the ones left out of the list are deleted. Everything saves
 * in one transaction.
 */
class SaveDeliveryZones
{
    public const MAX_ZONES = 100;

    /**
     * @param  array<int, array{id?: ?int, name: string, fee: string, is_active?: bool}>  $zones
     * @param  array<string, mixed>  $settings  delivery_mode, free_delivery_over, min_order_delivery, accepts_other_neighborhood
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $zones, array $settings): void
    {
        $validator = Validator::make(['zones' => $zones], [
            'zones' => ['array', 'max:'.self::MAX_ZONES],
            'zones.*.name' => ['required', 'string', 'max:60'],
            'zones.*.fee' => ['required', 'string'],
        ], [
            'zones.*.name.required' => 'Dê um nome para o bairro.',
            'zones.*.fee.required' => 'Informe a taxa do bairro (pode ser 0,00).',
        ])->after(function ($validator) use ($zones) {
            $seen = [];

            foreach (array_values($zones) as $index => $zone) {
                if (filled($zone['fee'] ?? null) && Money::parse((string) $zone['fee']) === null) {
                    $validator->errors()->add("zones.{$index}.fee", 'Taxa inválida. Use o formato 5,00.');
                }

                $key = mb_strtolower(trim((string) ($zone['name'] ?? '')));

                if ($key !== '' && in_array($key, $seen, true)) {
                    $validator->errors()->add("zones.{$index}.name", 'Este bairro já está na lista.');
                }

                $seen[] = $key;
            }
        });

        $validator->validate();

        DB::transaction(function () use ($restaurant, $zones, $settings) {
            app(SaveOrderSettings::class)->handle($restaurant, $settings);

            $keep = [];

            foreach (array_values($zones) as $index => $zone) {
                $row = [
                    'name' => trim($zone['name']),
                    'fee' => Money::parse((string) $zone['fee']),
                    'is_active' => (bool) ($zone['is_active'] ?? true),
                    'display_order' => $index + 1,
                ];

                $existing = isset($zone['id']) ? $restaurant->deliveryZones()->whereKey($zone['id'])->first() : null;

                if ($existing) {
                    $existing->update($row);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $restaurant->deliveryZones()->create($row)->id;
                }
            }

            $restaurant->deliveryZones()->whereNotIn('id', $keep)->delete();
        });
    }
}
