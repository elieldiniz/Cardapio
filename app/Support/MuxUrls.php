<?php

namespace App\Support;

/**
 * Public Mux delivery URLs. The feed plays MP4 (faststart) static renditions
 * rather than HLS (Tech Stack — Vídeo no feed), starting at 480p and moving
 * up to 720p when the connection allows (US-1.2).
 */
class MuxUrls
{
    public const STREAM_ORIGIN = 'https://stream.mux.com';

    public const IMAGE_ORIGIN = 'https://image.mux.com';

    public static function mp4(string $playbackId, string $resolution = '480p'): string
    {
        return self::STREAM_ORIGIN."/{$playbackId}/{$resolution}.mp4";
    }

    public static function thumbnail(string $playbackId): string
    {
        return self::IMAGE_ORIGIN."/{$playbackId}/thumbnail.webp?time=0";
    }
}
