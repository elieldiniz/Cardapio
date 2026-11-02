<?php

use App\Models\BusinessHour;
use App\Models\Restaurant;
use App\Models\RestaurantOrderSetting;
use Illuminate\Database\QueryException;

/**
 * RF-01, RF-02, RF-02e, RNF-01, RNF-02, RNF-09 — order settings and business hours schema.
 */
it('persists the settings of a restaurant with the default timezone', function () {
    $restaurant = Restaurant::factory()->create();

    $settings = RestaurantOrderSetting::factory()->create([
        'restaurant_id' => $restaurant->id,
        'free_delivery_over' => '80.00',
    ]);

    expect($settings->fresh()->timezone)->toBe('America/Porto_Velho')
        ->and($settings->fresh()->free_delivery_over)->toBe('80.00')
        ->and($restaurant->fresh()->orderSetting->id)->toBe($settings->id);
});

it('allows only one settings row per restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $restaurant->id]);

    expect(fn () => RestaurantOrderSetting::factory()->create(['restaurant_id' => $restaurant->id]))
        ->toThrow(QueryException::class);
});

it('falls back to default settings for a store that never opened them', function () {
    $restaurant = Restaurant::factory()->create();

    $settings = $restaurant->orderSettings();

    expect($settings->exists)->toBeFalse()
        ->and($settings->timezone)->toBe('America/Porto_Velho')
        ->and($settings->accepts_orders)->toBeTrue()
        ->and($settings->hours_enabled)->toBeFalse();
});

it('keeps the application timezone on UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

it('stores several shifts per weekday but never the same shift twice', function () {
    $restaurant = Restaurant::factory()->create();

    BusinessHour::factory()->create(['restaurant_id' => $restaurant->id, 'weekday' => 1, 'shift' => 1]);
    BusinessHour::factory()->create(['restaurant_id' => $restaurant->id, 'weekday' => 1, 'shift' => 2, 'opens_at' => '11:00', 'closes_at' => '14:00']);

    expect($restaurant->businessHours()->count())->toBe(2);

    expect(fn () => BusinessHour::factory()->create(['restaurant_id' => $restaurant->id, 'weekday' => 1, 'shift' => 1]))
        ->toThrow(QueryException::class);
});
