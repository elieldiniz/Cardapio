<?php

namespace App\Services\Address;

use App\Models\City;
use App\Models\Neighborhood;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The neighborhoods of a city for the delivery checklist. IBGE's list is already in the
 * database; for the cities IBGE does not cover (São Paulo, Goiânia, Anápolis...) they are
 * looked up once on OpenStreetMap — slow and sometimes down, so FetchCityNeighborhoods runs
 * it in the background — cleaned and kept, so the next owner of that city gets them instantly.
 * When OpenStreetMap fails nothing is saved and the lookup can be tried again.
 */
class CityNeighborhoods
{
    private const OVERPASS = 'https://overpass-api.de/api/interpreter';

    /**
     * @return Collection<int, string> names in alphabetical order
     */
    public function for(City $city): Collection
    {
        return $city->neighborhoods()->orderBy('name')->pluck('name')
            ->sortBy(fn (string $name) => GeoDataImporter::normalize($name))
            ->values();
    }

    /** Whether the city has no neighborhoods yet and OpenStreetMap was not asked about it. */
    public function needsLookup(City $city): bool
    {
        return $city->neighborhoods_fetched_at === null && ! $city->neighborhoods()->exists();
    }

    /**
     * Asks OpenStreetMap for the city's neighborhoods and keeps them.
     *
     * @return bool false when OpenStreetMap could not answer (nothing is saved)
     */
    public function fetchFromOpenStreetMap(City $city): bool
    {
        $query = sprintf(
            '[out:json][timeout:60];area["name"="%s"]["boundary"="administrative"]["admin_level"="8"]["IBGE:GEOCODIGO"="%d"]->.a;nwr["place"~"^(suburb|neighbourhood)$"][name](area.a);out tags;',
            str_replace(['\\', '"'], ['\\\\', '\\"'], $city->name),
            $city->ibge_code,
        );

        try {
            $response = Http::timeout(90)->withUserAgent(config('app.name', 'cardapio').'/1.0')->asForm()->post(self::OVERPASS, ['data' => $query]);
        } catch (Throwable) {
            return false;
        }

        if ($response->failed() || ! is_array($response->json('elements'))) {
            return false;
        }

        $names = collect($response->json('elements'))
            ->map(fn (array $element) => self::clean((string) ($element['tags']['name'] ?? '')))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->take(400);

        foreach ($names as $name) {
            Neighborhood::query()->firstOrCreate(['city_id' => $city->id, 'name' => $name], ['source' => 'osm']);
        }

        $city->forceFill(['neighborhoods_fetched_at' => now()])->save();

        return true;
    }

    /**
     * OpenStreetMap names are free text: drop the "Bairro " prefix and what is not a neighborhood
     * ("Anexo ...", shopping centers, condominiums, names too long to be a bairro).
     */
    public static function clean(string $name): string
    {
        $name = Str::of($name)->squish()->replaceMatches('/^bairro\s+/iu', '')->toString();

        if ($name === '' || mb_strlen($name) > 60 || preg_match('/^(anexo|condom[ií]nio|shopping|loteamento\s+fechado)\b/iu', $name)) {
            return '';
        }

        return $name;
    }
}
