<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown before a generation is created when monthly + addon balance cannot
 * cover the requested variations (US-3.4).
 */
class InsufficientGenerationBalanceException extends RuntimeException
{
    public function __construct(public readonly int $needed, public readonly int $available)
    {
        parent::__construct("A geração precisa de {$needed} gerações, mas o saldo disponível é {$available}.");
    }
}
