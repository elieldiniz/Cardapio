<?php

use App\Models\BusinessHour;
use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Support\BusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * RF-02, RF-02e, RNF-09, UI-04 — open/closed in the store's own timezone.
 * 2026-11-02 is a Monday; America/Porto_Velho is UTC-4 (no DST).
 */
function storeWithHours(array $settings = [], array $hours = []): Restaurant
{
    $restaurant = Restaurant::factory()->create();
    RestaurantOrderSetting::factory()->create(array_merge(['restaurant_id' => $restaurant->id, 'hours_enabled' => true], $settings));

    foreach ($hours as $hour) {
        BusinessHour::factory()->create(array_merge(['restaurant_id' => $restaurant->id], $hour));
    }

    return $restaurant->fresh();
}

it('is open inside the Monday 18-23 shift, converting UTC to the store timezone', function () {
    $store = storeWithHours([], [['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']]);

    $state = BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 22:30', 'UTC')); // 18:30 local

    expect($state['open'])->toBeTrue()->and($state['label'])->toBe('Aberto agora');
});

it('is closed after the shift ends and shows the next opening', function () {
    $store = storeWithHours([], [['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00'], ['weekday' => 2, 'opens_at' => '18:00', 'closes_at' => '23:00']]);

    $late = BusinessHours::state($store, CarbonImmutable::parse('2026-11-03 03:30', 'UTC')); // Monday 23:30 local

    expect($late['open'])->toBeFalse()->and($late['label'])->toBe('Fechado · abre ter às 18:00');

    $noon = BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 16:00', 'UTC')); // Monday 12:00 local

    expect($noon['open'])->toBeFalse()->and($noon['label'])->toBe('Fechado · abre às 18:00');
});

it('lets the manual switch close the store even inside its hours', function () {
    $store = storeWithHours(['accepts_orders' => false], [['weekday' => 1, 'opens_at' => '00:00', 'closes_at' => '23:59']]);

    $state = BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 16:00', 'UTC'));

    expect($state['open'])->toBeFalse()->and($state['reason'])->toBe('manual');
});

it('is always open without automatic hours', function () {
    $store = storeWithHours(['hours_enabled' => false]);

    expect(BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 08:00', 'UTC'))['open'])->toBeTrue();
});

it('supports several shifts in the same day', function () {
    $store = storeWithHours([], [
        ['weekday' => 1, 'shift' => 1, 'opens_at' => '11:00', 'closes_at' => '14:00'],
        ['weekday' => 1, 'shift' => 2, 'opens_at' => '18:00', 'closes_at' => '23:00'],
    ]);

    expect(BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 16:00', 'UTC'))['open'])->toBeTrue()   // 12:00
        ->and(BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 19:00', 'UTC'))['open'])->toBeFalse() // 15:00
        ->and(BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 22:30', 'UTC'))['open'])->toBeTrue(); // 18:30
});

it('handles a shift that runs past midnight', function () {
    $store = storeWithHours([], [['weekday' => 5, 'opens_at' => '22:00', 'closes_at' => '02:00']]);

    // Friday 2026-11-06 23:30 local and Saturday 01:00 local are both inside; Saturday 03:00 is not.
    expect(BusinessHours::state($store, CarbonImmutable::parse('2026-11-07 03:30', 'UTC'))['open'])->toBeTrue()
        ->and(BusinessHours::state($store, CarbonImmutable::parse('2026-11-07 05:00', 'UTC'))['open'])->toBeTrue()
        ->and(BusinessHours::state($store, CarbonImmutable::parse('2026-11-07 07:00', 'UTC'))['open'])->toBeFalse();
});

it('honours the store timezone instead of a fixed one', function () {
    $store = storeWithHours(['timezone' => 'America/Sao_Paulo'], [['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']]);

    // 21:30 UTC = 18:30 in São Paulo (UTC-3) but 17:30 in Porto Velho.
    expect(BusinessHours::state($store, CarbonImmutable::parse('2026-11-02 21:30', 'UTC'))['open'])->toBeTrue();
});

it('rejects an invalid timezone', function () {
    $store = storeWithHours(['timezone' => 'Mars/Olympus']);

    BusinessHours::state($store, CarbonImmutable::now());
})->throws(InvalidArgumentException::class);

it('builds the week grid starting on Monday', function () {
    $store = storeWithHours([], [['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '23:00']]);

    $grid = BusinessHours::weekGrid($store);

    expect($grid)->toHaveCount(7)
        ->and($grid[0]['label'])->toBe('Segunda')
        ->and($grid[0]['shifts'])->toBe([['opens' => '18:00', 'closes' => '23:00']])
        ->and($grid[6]['label'])->toBe('Domingo');
});
