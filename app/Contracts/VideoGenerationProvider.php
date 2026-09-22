<?php

namespace App\Contracts;

interface VideoGenerationProvider
{
    /**
     * Generate video variations from a set of dish photos using a fixed preset.
     *
     * The preset array carries: prompt, camera_movement, duration_seconds,
     * aspect_ratio ("9:16"), variations (how many clips to return) and turn —
     * "limited" (single photo: never a full 360°) or "wide" (3–4 angles).
     *
     * Implementations throw on any provider error; an error never consumes balance.
     *
     * @param  array<int, string>  $photos  Publicly reachable URLs of the source photos, ordered by angle.
     * @param  array<string, mixed>  $preset  The fixed AI preset configuration.
     * @return array<int, array{video_url: string, cost_usd?: float}> One entry per generated clip (an MP4 URL Mux can ingest).
     */
    public function generate(array $photos, array $preset): array;
}
