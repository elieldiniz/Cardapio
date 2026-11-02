<?php

namespace App\Support;

/**
 * Delivery fee by distance (RF-24a): great-circle distance between the store and the customer
 * (haversine) and the range it falls in. Pure functions, so the server alone decides the fee.
 */
class DistanceFee
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public static function km(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) ** 2;

        return round(self::EARTH_RADIUS_KM * 2 * asin(min(1, sqrt($a))), 2);
    }

    /**
     * The fee of the range holding `$km`, or null when the distance is outside every range
     * (the order then becomes "a combinar"). Ranges are `from < km <= to`; the first one also takes 0.
     *
     * @param  iterable<array{from_km: float|int|string, to_km: float|int|string, fee: float|int|string}>  $tiers
     */
    public static function fee(iterable $tiers, ?float $km): ?string
    {
        if ($km === null || $km < 0) {
            return null;
        }

        $sorted = collect($tiers)->sortBy(fn ($tier) => (float) $tier['from_km'])->values();

        foreach ($sorted as $index => $tier) {
            $from = (float) $tier['from_km'];
            $to = (float) $tier['to_km'];

            if (($km > $from || ($index === 0 && $km >= $from)) && $km <= $to) {
                return number_format((float) $tier['fee'], 2, '.', '');
            }
        }

        return null;
    }
}
