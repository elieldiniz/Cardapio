<?php

namespace Tests\Fakes;

use App\Contracts\VideoGenerationProvider;
use RuntimeException;

/**
 * Test double for an AI video vendor: records every call and returns one clip
 * per requested variation, or throws when told to fail.
 */
class FakeVideoGenerationProvider implements VideoGenerationProvider
{
    /** @var array<int, array{photos: array<int, string>, preset: array<string, mixed>}> */
    public static array $calls = [];

    public static bool $shouldFail = false;

    public static function reset(): void
    {
        static::$calls = [];
        static::$shouldFail = false;
    }

    public function generate(array $photos, array $preset): array
    {
        static::$calls[] = ['photos' => $photos, 'preset' => $preset];

        if (static::$shouldFail) {
            throw new RuntimeException('Provider unavailable');
        }

        return array_map(fn (int $i) => [
            'video_url' => "https://provider.test/clips/{$i}.mp4",
            'cost_usd' => 0.25,
        ], range(1, (int) $preset['variations']));
    }
}
