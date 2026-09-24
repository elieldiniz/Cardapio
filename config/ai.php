<?php

use App\Services\Ai\GeminiVeoVideoGenerationProvider;
use App\Services\Ai\NullVideoGenerationProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Video Generation Provider
    |--------------------------------------------------------------------------
    |
    | The slug of the provider used to turn dish photos into video clips.
    | Swapping vendors (e.g. Runway) is a config change here, not a code
    | change — add the implementation to "providers" and point this at it.
    |
    */

    'default' => env('AI_VIDEO_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Maps a provider slug to the class implementing
    | App\Contracts\VideoGenerationProvider.
    |
    */

    'providers' => [
        'null' => NullVideoGenerationProvider::class,
        'gemini' => GeminiVeoVideoGenerationProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini Veo pricing (USD per generated second)
    |--------------------------------------------------------------------------
    |
    | Used to record each generation's cost. Source: ai.google.dev pricing,
    | September 2026 (audio included, no free tier).
    |
    */

    'gemini_price_per_second' => [
        'veo-3.1-lite-generate-preview' => ['720p' => 0.05, '1080p' => 0.08],
        'veo-3.1-fast-generate-preview' => ['720p' => 0.10, '1080p' => 0.12, '4k' => 0.30],
        'veo-3.1-generate-preview' => ['720p' => 0.40, '1080p' => 0.40, '4k' => 0.60],
    ],

];
