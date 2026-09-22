<?php

namespace App\Contracts;

interface VideoGenerationProvider
{
    /**
     * Generate video variations from a set of dish photos using a fixed preset.
     *
     * @param  array<int, string>  $photos  Absolute URLs or paths to the source photos.
     * @param  array<string, mixed>  $preset  The fixed AI preset configuration (prompt, camera movement, duration, ...).
     * @return array<int, array<string, mixed>>  The generated variations.
     */
    public function generate(array $photos, array $preset): array;
}
