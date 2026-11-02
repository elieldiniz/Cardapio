<?php

namespace App\Actions\Settings;

use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the store's distance ranges (RF-24a). Ranges must not overlap; the ones left out are deleted.
 */
class SaveDistanceTiers
{
    public const MAX_TIERS = 12;

    /**
     * @param  array<int, array{from_km: string, to_km: string, fee: string}>  $tiers
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $tiers): void
    {
        $rows = [];
        $errors = [];

        if (count($tiers) > self::MAX_TIERS) {
            throw ValidationException::withMessages(['tiers' => 'No máximo '.self::MAX_TIERS.' faixas.']);
        }

        foreach (array_values($tiers) as $index => $tier) {
            $from = $this->number($tier['from_km'] ?? null);
            $to = $this->number($tier['to_km'] ?? null);
            $fee = Money::parse((string) ($tier['fee'] ?? ''));

            if ($from === null || $to === null || $from < 0 || $to <= $from || $to > 200) {
                $errors["tiers.{$index}.to_km"] = 'Confira os km: o final precisa ser maior que o início.';
            }

            if ($fee === null) {
                $errors["tiers.{$index}.fee"] = 'Informe a taxa da faixa (pode ser 0,00).';
            }

            if ($errors === [] || ! array_key_exists("tiers.{$index}.to_km", $errors)) {
                $rows[] = ['from_km' => $from, 'to_km' => $to, 'fee' => $fee];
            }
        }

        usort($rows, fn ($a, $b) => $a['from_km'] <=> $b['from_km']);

        foreach ($rows as $i => $row) {
            if ($i > 0 && $row['from_km'] < $rows[$i - 1]['to_km']) {
                $errors['tiers'] = 'As faixas de distância não podem se sobrepor.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($restaurant, $rows) {
            $restaurant->distanceTiers()->delete();

            foreach ($rows as $row) {
                $restaurant->distanceTiers()->create($row);
            }
        });
    }

    private function number(mixed $value): ?float
    {
        $clean = str_replace(',', '.', trim((string) $value));

        return $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }
}
