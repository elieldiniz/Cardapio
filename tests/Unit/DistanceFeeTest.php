<?php

use App\Support\DistanceFee;

/**
 * RF-24a — fee by distance range.
 */
$tiers = [
    ['from_km' => 0, 'to_km' => 3, 'fee' => '5.00'],
    ['from_km' => 3, 'to_km' => 6, 'fee' => '8.00'],
];

it('picks the range that holds the distance', function (float $km, ?string $fee) use ($tiers) {
    expect(DistanceFee::fee($tiers, $km))->toBe($fee);
})->with([
    'no ponto de partida' => [0.0, '5.00'],
    'dentro da primeira' => [1.5, '5.00'],
    'na divisa vai para a faixa de baixo' => [3.0, '5.00'],
    'logo depois da divisa' => [3.01, '8.00'],
    '4 km' => [4.0, '8.00'],
    'no limite da última' => [6.0, '8.00'],
    '7 km fora das faixas' => [7.0, null],
]);

it('has no fee without a distance or without ranges', function () use ($tiers) {
    expect(DistanceFee::fee($tiers, null))->toBeNull()->and(DistanceFee::fee([], 2.0))->toBeNull();
});

it('does not depend on the order of the ranges', function () use ($tiers) {
    expect(DistanceFee::fee(array_reverse($tiers), 4.0))->toBe('8.00');
});

it('computes the distance between two points with the haversine formula', function () {
    // 0.01° of latitude ≈ 1.11 km.
    expect(DistanceFee::km(-8.76, -63.9, -8.77, -63.9))->toEqualWithDelta(1.11, 0.02)
        ->and(DistanceFee::km(-8.76, -63.9, -8.76, -63.9))->toBe(0.0)
        ->and(DistanceFee::km(-23.55, -46.63, -22.9, -43.17))->toEqualWithDelta(358, 5);
});
