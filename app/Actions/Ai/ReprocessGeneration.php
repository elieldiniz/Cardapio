<?php

namespace App\Actions\Ai;

use App\Jobs\GenerateDishVideo;
use App\Models\GenerationStatus;
use App\Models\VideoGeneration;
use InvalidArgumentException;

/**
 * Super admin "reprocessar" (US-7.4): sends an errored generation back
 * through the same job. The debit is idempotent per generation, so a
 * reprocessed generation is never charged twice.
 */
class ReprocessGeneration
{
    public function handle(VideoGeneration $generation): void
    {
        if ($generation->status?->slug !== 'erro') {
            throw new InvalidArgumentException('Only an errored generation can be reprocessed.');
        }

        $generation->update(['status_id' => GenerationStatus::idFor('fila')]);

        GenerateDishVideo::dispatch($generation->id);
    }
}
