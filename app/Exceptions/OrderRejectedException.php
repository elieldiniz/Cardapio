<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an order cannot be accepted as sent (RF-05, RF-06e): an item became
 * unavailable, a required choice is missing, the store does not deliver there or the
 * delivery minimum was not reached. Carries every problem found, so the customer can
 * fix the cart in one go instead of one error at a time.
 */
class OrderRejectedException extends RuntimeException
{
    /**
     * @param  array<int, array{type: string, message: string, dish_id?: ?int, name?: ?string, amount?: ?string}>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct($issues[0]['message'] ?? 'Não foi possível aceitar o pedido.');
    }

    /**
     * Unavailable items, so the cart can drop or highlight them.
     *
     * @return array<int, array{dish_id: ?int, name: ?string, message: string}>
     */
    public function items(): array
    {
        return array_values(array_map(
            fn (array $issue) => ['dish_id' => $issue['dish_id'] ?? null, 'name' => $issue['name'] ?? null, 'message' => $issue['message']],
            array_filter($this->issues, fn (array $issue) => isset($issue['dish_id'])),
        ));
    }

    public function hasType(string $type): bool
    {
        return collect($this->issues)->contains(fn (array $issue) => $issue['type'] === $type);
    }
}
