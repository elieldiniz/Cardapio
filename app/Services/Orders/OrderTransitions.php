<?php

namespace App\Services\Orders;

/**
 * The statuses an order can move to from each one (RF-08). Forward is the normal flow
 * (novo → em preparo → pronto/em rota → finalizado); one step back is allowed before the
 * order ends, to fix a mistaken tap. Refusing is possible while the order is still being
 * handled; cancelling a finished order is a separate, reasoned action (RF-08c). A refused,
 * cancelled or finished order never moves again here.
 */
final class OrderTransitions
{
    public const MAP = [
        'novo' => ['em_preparo', 'recusado'],
        'em_preparo' => ['em_rota', 'novo', 'recusado'],
        'em_rota' => ['finalizado', 'em_preparo'],
        'finalizado' => [],
        'recusado' => [],
        'cancelado' => [],
    ];

    /**
     * @return array<int, string>
     */
    public static function allowed(string $from): array
    {
        return self::MAP[$from] ?? [];
    }

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::allowed($from), true);
    }
}
