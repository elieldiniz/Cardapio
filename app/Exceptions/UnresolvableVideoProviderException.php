<?php

namespace App\Exceptions;

use RuntimeException;

class UnresolvableVideoProviderException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("No AI video generation provider is bound for the configured slug [{$slug}]. Check the \"providers\" map in config/ai.php.");
    }
}
