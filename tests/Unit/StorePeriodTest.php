<?php

use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use App\Support\StorePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * RNF-09, RF-08b, RF-12 — periods are calendar days of the store, returned in UTC.
 * America/Porto_Velho is UTC-4.
 */
beforeEach(function () {
    $this->store = Restaurant::factory()->create();
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id]);
    // Tuesday 2026-11-03 02:00 UTC = Monday 2026-11-02 22:00 in Porto Velho.
    $this->now = CarbonImmutable::parse('2026-11-03 02:00:00', 'UTC');
});

it('puts an order at 02:00 UTC of Tuesday inside "hoje" of Monday', function () {
    $range = StorePeriod::range($this->store, 'hoje', $this->now);

    expect($range['from']->toDateTimeString())->toBe('2026-11-02 04:00:00')
        ->and($range['to']->toDateTimeString())->toBe('2026-11-03 04:00:00')
        ->and($this->now->betweenIncluded($range['from'], $range['to']->subSecond()))->toBeTrue();
});

it('computes yesterday, yesterday and today and the last seven days', function () {
    expect(StorePeriod::range($this->store, 'ontem', $this->now)['from']->toDateTimeString())->toBe('2026-11-01 04:00:00')
        ->and(StorePeriod::range($this->store, 'ontem', $this->now)['to']->toDateTimeString())->toBe('2026-11-02 04:00:00')
        ->and(StorePeriod::range($this->store, 'ontem_hoje', $this->now)['from']->toDateTimeString())->toBe('2026-11-01 04:00:00')
        ->and(StorePeriod::range($this->store, 'ontem_hoje', $this->now)['to']->toDateTimeString())->toBe('2026-11-03 04:00:00')
        ->and(StorePeriod::range($this->store, '7dias', $this->now)['from']->toDateTimeString())->toBe('2026-10-27 04:00:00');
});

it('computes this month and last month in the store timezone', function () {
    $month = StorePeriod::range($this->store, 'mes', $this->now);
    $last = StorePeriod::range($this->store, 'mes_passado', $this->now);

    expect($month['from']->toDateTimeString())->toBe('2026-11-01 04:00:00')
        ->and($month['to']->toDateTimeString())->toBe('2026-12-01 04:00:00')
        ->and($last['from']->toDateTimeString())->toBe('2026-10-01 04:00:00')
        ->and($last['to']->toDateTimeString())->toBe('2026-11-01 04:00:00');
});

it('follows the store timezone instead of a fixed one', function () {
    $this->store->orderSetting->update(['timezone' => 'America/Sao_Paulo']);

    // 02:00 UTC of Tuesday is still Monday 23:00 in São Paulo (UTC-3).
    expect(StorePeriod::range($this->store->fresh(), 'hoje', $this->now)['from']->toDateTimeString())->toBe('2026-11-02 03:00:00');
});

it('rejects an unknown period and labels the known ones', function () {
    expect(fn () => StorePeriod::range($this->store, 'ano', $this->now))->toThrow(InvalidArgumentException::class);

    expect(StorePeriod::label('ontem_hoje'))->toBe('Ontem e hoje')->and(StorePeriod::KEYS)->toContain('mes_passado');
});
