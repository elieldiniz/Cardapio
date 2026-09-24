<?php

namespace App\Contracts;

/**
 * A vendor whose generations run as long-running operations (minutes), which
 * would outlive a single queued job. The work is split in two: start() submits
 * one operation per variation, then poll() is called until they finish.
 */
interface AsyncVideoGenerationProvider
{
    /**
     * Submit the generation. Throws on any provider error (nothing is charged).
     *
     * @param  array<int, string>  $photos  Publicly reachable URLs of the source photos, ordered by angle.
     * @param  array<string, mixed>  $preset  Same shape as VideoGenerationProvider::generate().
     * @return array<int, string> One operation handle per requested variation.
     */
    public function start(array $photos, array $preset): array;

    /**
     * Check the operations. Returns null while any is still running; once all
     * are done, returns every clip that succeeded as a local MP4 file. Throws
     * only when none of them produced a video.
     *
     * @param  array<int, string>  $operations
     * @return array<int, array{video_file: string, cost_usd?: float}>|null
     */
    public function poll(array $operations): ?array;
}
