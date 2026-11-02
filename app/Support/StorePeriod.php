<?php

namespace App\Support;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Calendar periods ("hoje", "ontem", "7 dias", "este mês"…) as the dono thinks of them: in
 * the store's own timezone (RNF-09). The bounds come back in UTC, ready for a database
 * query: `from` inclusive, `to` exclusive.
 */
class StorePeriod
{
    public const KEYS = ['hoje', 'ontem', 'ontem_hoje', '7dias', 'mes', 'mes_passado'];

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function range(Restaurant $restaurant, string $key, ?CarbonInterface $now = null): array
    {
        $local = BusinessHours::localNow($restaurant, $now);
        $today = $local->startOfDay();

        [$from, $to] = match ($key) {
            'hoje' => [$today, $today->addDay()],
            'ontem' => [$today->subDay(), $today],
            'ontem_hoje' => [$today->subDay(), $today->addDay()],
            '7dias' => [$today->subDays(6), $today->addDay()],
            'mes' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            'mes_passado' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()],
            default => throw new InvalidArgumentException("Período desconhecido: {$key}."),
        };

        return ['from' => $from->utc(), 'to' => $to->utc()];
    }

    /**
     * Human label of a period key, as shown on the tabs.
     */
    public static function label(string $key): string
    {
        return [
            'hoje' => 'Hoje',
            'ontem' => 'Ontem',
            'ontem_hoje' => 'Ontem e hoje',
            '7dias' => '7 dias',
            'mes' => 'Este mês',
            'mes_passado' => 'Mês passado',
        ][$key] ?? $key;
    }
}
