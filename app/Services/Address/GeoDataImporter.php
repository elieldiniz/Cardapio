<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads database/data/cities.csv and neighborhoods.csv (IBGE: municipalities and the
 * neighborhoods the city halls delimited for the 2022 census) into the cities and
 * neighborhoods tables. Safe to run again: it only adds what is missing.
 * The files are rebuilt with database/data/build_geo_data.py.
 */
class GeoDataImporter
{
    public function import(?string $dataPath = null): void
    {
        $dataPath ??= database_path('data');

        DB::transaction(function () use ($dataPath) {
            $this->importCities($dataPath.'/cities.csv');
            $this->importNeighborhoods($dataPath.'/neighborhoods.csv');
        });
    }

    private function importCities(string $file): void
    {
        $rows = [];

        foreach ($this->rows($file) as [$code, $state, $name]) {
            $rows[] = ['ibge_code' => (int) $code, 'state' => $state, 'name' => $name, 'search_name' => self::normalize($name)];

            if (count($rows) === 500) {
                DB::table('cities')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('cities')->insertOrIgnore($rows);
        }
    }

    private function importNeighborhoods(string $file): void
    {
        $cityIds = DB::table('cities')->pluck('id', 'ibge_code');
        $rows = [];

        foreach ($this->rows($file) as [$code, $name]) {
            if (! isset($cityIds[(int) $code])) {
                continue;
            }

            $rows[] = ['city_id' => $cityIds[(int) $code], 'name' => $name, 'source' => 'ibge'];

            if (count($rows) === 500) {
                DB::table('neighborhoods')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('neighborhoods')->insertOrIgnore($rows);
        }
    }

    /** @return \Generator<int, array<int, string>> */
    private function rows(string $file): \Generator
    {
        $handle = fopen($file, 'r');

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            if ($row !== [null]) {
                yield $row;
            }
        }

        fclose($handle);
    }

    /** Lowercase without accents, for accent-insensitive search ("sao paulo" finds "São Paulo"). */
    public static function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }
}
