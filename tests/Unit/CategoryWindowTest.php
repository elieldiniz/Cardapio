<?php

use App\Models\Category;
use Carbon\CarbonImmutable;

/**
 * RF-02b — a category's own time window ("cardápio do almoço / do jantar").
 */
function categoryWindow(?string $from, ?string $until): Category
{
    return new Category(['shows_from' => $from, 'shows_until' => $until]);
}

it('always shows a category without a window', function () {
    expect(categoryWindow(null, null)->isShownAt(CarbonImmutable::parse('2026-11-02 03:00')))->toBeTrue();
});

it('hides a lunch category after its window and shows it inside', function () {
    $lunch = categoryWindow('11:00:00', '15:00:00');

    expect($lunch->isShownAt(CarbonImmutable::parse('2026-11-02 12:00')))->toBeTrue()
        ->and($lunch->isShownAt(CarbonImmutable::parse('2026-11-02 16:00')))->toBeFalse()
        ->and($lunch->isShownAt(CarbonImmutable::parse('2026-11-02 10:59')))->toBeFalse()
        ->and($lunch->isShownAt(CarbonImmutable::parse('2026-11-02 15:00')))->toBeFalse();
});

it('handles a window that runs past midnight', function () {
    $late = categoryWindow('22:00', '02:00');

    expect($late->isShownAt(CarbonImmutable::parse('2026-11-02 23:30')))->toBeTrue()
        ->and($late->isShownAt(CarbonImmutable::parse('2026-11-02 01:00')))->toBeTrue()
        ->and($late->isShownAt(CarbonImmutable::parse('2026-11-02 12:00')))->toBeFalse();
});
