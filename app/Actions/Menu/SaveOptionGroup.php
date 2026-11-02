<?php

namespace App\Actions\Menu;

use App\Models\Dish;
use App\Models\OptionGroup;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a complement group with its options and the products that use
 * it, in one transaction (RF-03, RNF-01). Options keep their ids when edited, so a
 * customer's open cart still points at valid options; only the ones removed from
 * the list are deleted. Past orders are unaffected: they store a snapshot.
 */
class SaveOptionGroup
{
    public const MAX_OPTIONS = 40;

    public const MAX_CHOICES = 20;

    /**
     * @param  array{name: string, mode: string, max_choices?: int|string, is_required?: bool, allow_repeat?: bool, is_active?: bool, options: array<int, array{id?: ?int, name: string, price?: ?string, is_available?: bool}>, dish_ids?: array<int, int|string>}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, ?OptionGroup $group = null): OptionGroup
    {
        if ($group !== null && $group->restaurant_id !== $restaurant->id) {
            abort(404);
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:80'],
            'mode' => ['required', 'in:one,many'],
            'max_choices' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_CHOICES],
            'options' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
            'options.*.name' => ['required', 'string', 'max:80'],
            'options.*.price' => ['nullable', 'string'],
            'dish_ids' => ['nullable', 'array'],
        ], [
            'name.required' => 'Dê um nome para o grupo.',
            'options.required' => 'Adicione pelo menos uma opção.',
            'options.min' => 'Adicione pelo menos uma opção.',
            'options.*.name.required' => 'Toda opção precisa de um nome.',
        ])->after(function ($validator) use ($data) {
            foreach ($data['options'] ?? [] as $index => $option) {
                if (filled($option['price'] ?? null) && Money::parse((string) $option['price']) === null) {
                    $validator->errors()->add("options.{$index}.price", 'Preço inválido. Use o formato 4,00.');
                }
            }
        })->validate();

        $dishIds = array_values(array_unique(array_map('intval', $validated['dish_ids'] ?? [])));

        if ($dishIds !== [] && Dish::query()->where('restaurant_id', $restaurant->id)->whereIn('id', $dishIds)->count() !== count($dishIds)) {
            throw ValidationException::withMessages(['dish_ids' => 'Um dos produtos não pertence ao seu restaurante.']);
        }

        $single = $validated['mode'] === 'one';

        $attributes = [
            'name' => trim($validated['name']),
            'mode' => $validated['mode'],
            'max_choices' => $single ? 1 : (int) ($validated['max_choices'] ?? 1),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'allow_repeat' => $single ? false : (bool) ($data['allow_repeat'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return DB::transaction(function () use ($restaurant, $group, $attributes, $validated, $data, $dishIds) {
            if ($group === null) {
                $group = $restaurant->optionGroups()->create($attributes + [
                    'display_order' => (int) $restaurant->optionGroups()->max('display_order') + 1,
                ]);
            } else {
                $group->update($attributes);
            }

            $keep = [];

            foreach (array_values($validated['options']) as $index => $option) {
                $row = [
                    'name' => trim($option['name']),
                    'price' => filled($option['price'] ?? null) ? Money::parse((string) $option['price']) : null,
                    'is_available' => (bool) ($data['options'][$index]['is_available'] ?? true),
                    'display_order' => $index + 1,
                ];

                $existing = isset($data['options'][$index]['id'])
                    ? $group->options()->whereKey($data['options'][$index]['id'])->first()
                    : null;

                if ($existing) {
                    $existing->update($row);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $group->options()->create($row)->id;
                }
            }

            $group->options()->whereNotIn('id', $keep)->delete();

            if (array_key_exists('dish_ids', $data)) {
                $group->dishes()->sync($dishIds);
            }

            return $group->load('options');
        });
    }
}
