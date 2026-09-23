<?php

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
    ],

];
