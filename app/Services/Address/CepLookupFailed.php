<?php

namespace App\Services\Address;

use RuntimeException;
use Throwable;

class CepLookupFailed extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Não foi possível consultar o CEP agora.', 0, $previous);
    }
}
