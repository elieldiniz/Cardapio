<?php

namespace App\Actions\Settings;

use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the weekly business hours of a restaurant atomically (RF-02, RF-02e).
 * Each weekday (0 = domingo … 6 = sábado) holds up to three shifts; a shift whose
 * closing time is earlier than its opening runs past midnight.
 */
class SaveBusinessHours
{
    public const MAX_SHIFTS_PER_DAY = 3;

    /**
     * @param  array<int, array<int, array{opens: string, closes: string}>>  $days
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $days): void
    {
        $rows = [];

        foreach ($days as $weekday => $shifts) {
            $validator = Validator::make(
                ['weekday' => $weekday, 'shifts' => array_values($shifts)],
                [
                    'weekday' => ['integer', 'between:0,6'],
                    'shifts' => ['array', 'max:'.self::MAX_SHIFTS_PER_DAY],
                    'shifts.*.opens' => ['required', 'date_format:H:i'],
                    'shifts.*.closes' => ['required', 'date_format:H:i', 'different:shifts.*.opens'],
                ],
                [
                    'shifts.max' => 'No máximo '.self::MAX_SHIFTS_PER_DAY.' turnos por dia.',
                    'shifts.*.opens.date_format' => 'Horário inválido. Use HH:MM.',
                    'shifts.*.closes.date_format' => 'Horário inválido. Use HH:MM.',
                    'shifts.*.closes.different' => 'Abertura e fechamento não podem ser iguais.',
                ],
            );

            $validated = $validator->validate();

            foreach ($validated['shifts'] as $index => $shift) {
                $rows[] = [
                    'restaurant_id' => $restaurant->id,
                    'weekday' => (int) $validated['weekday'],
                    'shift' => $index + 1,
                    'opens_at' => $shift['opens'],
                    'closes_at' => $shift['closes'],
                ];
            }
        }

        DB::transaction(function () use ($restaurant, $rows) {
            $restaurant->businessHours()->delete();

            foreach ($rows as $row) {
                $restaurant->businessHours()->create($row);
            }
        });
    }
}
