<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No active AI preset is configured by the super admin, so nothing can be generated.
 */
class VideoGenerationUnavailableException extends RuntimeException
{
    public static function noActivePreset(): self
    {
        return new self('A geração de vídeo por IA está indisponível no momento.');
    }
}
