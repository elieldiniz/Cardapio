<?php

namespace App\Services\Ai;

use App\Contracts\VideoGenerationProvider;

/**
 * A no-op provider used in tests and local environments where no real
 * AI video-generation vendor is configured.
 */
class NullVideoGenerationProvider implements VideoGenerationProvider
{
    public function generate(array $photos, array $preset): array
    {
        return [];
    }
}
