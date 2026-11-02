<?php

namespace App\Actions\Menu;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a category with its icon and optional own time window
 * (RF-02b, "cardápio do almoço / do jantar"). The window needs both ends; a
 * window whose end is before its start runs past midnight.
 */
class SaveCategory
{
    /**
     * @param  array{name: string, icon?: ?string, shows_from?: ?string, shows_until?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, ?Category $category = null): Category
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:16'],
            'shows_from' => ['nullable', 'date_format:H:i'],
            'shows_until' => ['nullable', 'date_format:H:i', 'different:shows_from'],
        ], [
            'name.required' => 'Dê um nome para a categoria.',
            'shows_from.date_format' => 'Horário inválido. Use HH:MM.',
            'shows_until.date_format' => 'Horário inválido. Use HH:MM.',
            'shows_until.different' => 'Início e fim do horário não podem ser iguais.',
        ])->after(function ($validator) use ($data) {
            if (filled($data['shows_from'] ?? null) xor filled($data['shows_until'] ?? null)) {
                $validator->errors()->add('shows_from', 'Preencha o início e o fim do horário, ou deixe os dois vazios.');
            }
        })->validate();

        $attributes = [
            'name' => trim($validated['name']),
            'icon' => filled($validated['icon'] ?? null) ? trim($validated['icon']) : null,
            'shows_from' => filled($validated['shows_from'] ?? null) ? $validated['shows_from'] : null,
            'shows_until' => filled($validated['shows_until'] ?? null) ? $validated['shows_until'] : null,
        ];

        return DB::transaction(function () use ($restaurant, $category, $attributes) {
            if ($category === null) {
                return $restaurant->categories()->create($attributes + [
                    'display_order' => (int) $restaurant->categories()->max('display_order') + 1,
                ]);
            }

            $category->update($attributes);

            return $category;
        });
    }
}
