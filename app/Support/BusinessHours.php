<?php

namespace App\Support;

use App\Models\BusinessHour;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Whether a store is open right now, in the store's own timezone (RF-02, RF-02e,
 * RNF-09, UI-04). The app runs in UTC; the instant is converted to the store's
 * zone here, never by the callers.
 *
 * Precedence: the manual switch (accepts_orders) always wins and closes the
 * store; without automatic hours the store is simply open; with them, the
 * weekly grid decides, including shifts that run past midnight.
 */
class BusinessHours
{
    private const DAY_NAMES = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    private const DAY_LABELS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    /**
     * @return array{open: bool, reason: string, next_opening: ?CarbonImmutable, label: string}
     */
    public static function state(Restaurant $restaurant, ?CarbonInterface $now = null): array
    {
        $settings = $restaurant->orderSettings();
        $local = self::localNow($restaurant, $now);

        if (! $settings->accepts_orders) {
            return ['open' => false, 'reason' => 'manual', 'next_opening' => null, 'label' => 'Fechado agora'];
        }

        if (! $settings->hours_enabled) {
            return ['open' => true, 'reason' => 'always', 'next_opening' => null, 'label' => 'Aberto agora'];
        }

        $hours = $restaurant->businessHours()->get();

        if (self::isInsideAnyShift($hours, $local)) {
            return ['open' => true, 'reason' => 'hours', 'next_opening' => null, 'label' => 'Aberto agora'];
        }

        $next = self::nextOpening($hours, $local);

        if ($next === null) {
            return ['open' => false, 'reason' => 'hours', 'next_opening' => null, 'label' => 'Fechado'];
        }

        $time = $next->format('H:i');
        $label = $next->isSameDay($local)
            ? "Fechado · abre às {$time}"
            : 'Fechado · abre '.self::DAY_NAMES[$next->dayOfWeek]." às {$time}";

        return ['open' => false, 'reason' => 'hours', 'next_opening' => $next, 'label' => $label];
    }

    /**
     * The instant converted to the store's timezone (RNF-09).
     */
    public static function localNow(Restaurant $restaurant, ?CarbonInterface $now = null): CarbonImmutable
    {
        $timezone = $restaurant->orderSettings()->timezone;

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException("Fuso horário inválido: {$timezone}.");
        }

        return CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone($timezone);
    }

    /**
     * The week as shown by "Ver horários": Monday first, each day with its shifts.
     *
     * @return array<int, array{weekday: int, label: string, shifts: array<int, array{opens: string, closes: string}>}>
     */
    public static function weekGrid(Restaurant $restaurant): array
    {
        $byDay = $restaurant->businessHours()->orderBy('shift')->get()->groupBy('weekday');
        $grid = [];

        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday) {
            $grid[] = [
                'weekday' => $weekday,
                'label' => self::DAY_LABELS[$weekday],
                'shifts' => ($byDay[$weekday] ?? collect())
                    ->map(fn (BusinessHour $hour) => [
                        'opens' => substr($hour->opens_at, 0, 5),
                        'closes' => substr($hour->closes_at, 0, 5),
                    ])->values()->all(),
            ];
        }

        return $grid;
    }

    /**
     * @param  iterable<int, BusinessHour>  $hours
     */
    private static function isInsideAnyShift(iterable $hours, CarbonImmutable $local): bool
    {
        $time = $local->format('H:i');

        foreach ($hours as $hour) {
            $opens = substr($hour->opens_at, 0, 5);
            $closes = substr($hour->closes_at, 0, 5);
            $overnight = $opens > $closes;

            if ($hour->weekday === $local->dayOfWeek) {
                if ($overnight ? $time >= $opens : ($time >= $opens && $time < $closes)) {
                    return true;
                }
            }

            // The tail of yesterday's shift that crossed midnight.
            if ($overnight && $hour->weekday === ($local->dayOfWeek + 6) % 7 && $time < $closes) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, BusinessHour>  $hours
     */
    private static function nextOpening(iterable $hours, CarbonImmutable $local): ?CarbonImmutable
    {
        $candidates = [];

        foreach ($hours as $hour) {
            [$h, $m] = array_map('intval', explode(':', substr($hour->opens_at, 0, 5)));

            for ($offset = 0; $offset <= 7; $offset++) {
                $day = $local->startOfDay()->addDays($offset);

                if ($day->dayOfWeek !== $hour->weekday) {
                    continue;
                }

                $opening = $day->setTime($h, $m);

                if ($opening->greaterThan($local)) {
                    $candidates[] = $opening;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        return $candidates[0];
    }
}
