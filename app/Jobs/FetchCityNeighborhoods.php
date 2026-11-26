<?php

namespace App\Jobs;

use App\Models\City;
use App\Services\Address\CityNeighborhoods;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Looks the neighborhoods of a city IBGE does not cover up on OpenStreetMap (RF-06b). It is slow
 * and sometimes answers 502, so it runs in the background with a few retries while the
 * owner's screen waits. After the last failure a flag in the cache lets the screen say so.
 */
class FetchCityNeighborhoods implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $cityId) {}

    public function uniqueId(): string
    {
        return (string) $this->cityId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30];
    }

    public static function failedKey(int $cityId): string
    {
        return "city-neighborhoods-failed:{$cityId}";
    }

    public function handle(CityNeighborhoods $neighborhoods): void
    {
        $city = City::query()->find($this->cityId);

        if ($city === null || ! $neighborhoods->needsLookup($city)) {
            return;
        }

        Cache::forget(self::failedKey($city->id));

        if (! $neighborhoods->fetchFromOpenStreetMap($city)) {
            // Throwing makes the queue retry with the backoff above.
            throw new \RuntimeException("OpenStreetMap did not answer for city {$city->id}.");
        }
    }

    public function failed(): void
    {
        Cache::put(self::failedKey($this->cityId), true, now()->addMinutes(10));
    }
}
