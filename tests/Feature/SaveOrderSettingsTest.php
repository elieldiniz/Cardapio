<?php

use App\Actions\Settings\SaveBusinessHours;
use App\Actions\Settings\SaveOrderSettings;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RF-01, RF-02, RF-02e, RNF-01 — settings Actions and per-store isolation.
 */
beforeEach(function () {
    seedReferenceData();
});

it('saves and normalises the settings', function () {
    $restaurant = User::factory()->create()->restaurant;

    $settings = app(SaveOrderSettings::class)->handle($restaurant, [
        'whatsapp_number' => '(69) 99604-1447',
        'address' => 'Rua das Flores, 120',
        'pix_key' => 'teste@docin.com.br',
        'timezone' => 'America/Manaus',
        'delivery_mode' => 'bairro',
        'free_delivery_over' => 'R$ 80,00',
        'min_order_delivery' => '25,00',
        'hours_enabled' => true,
    ]);

    expect($settings->fresh())
        ->whatsapp_number->toBe('5569996041447')
        ->timezone->toBe('America/Manaus')
        ->free_delivery_over->toBe('80.00')
        ->min_order_delivery->toBe('25.00')
        ->hours_enabled->toBeTrue()
        ->delivery_mode->toBe('bairro');
});

it('changes only the sent keys', function () {
    $restaurant = User::factory()->create()->restaurant;
    $action = app(SaveOrderSettings::class);

    $action->handle($restaurant, ['address' => 'Rua A', 'pix_key' => 'chave']);
    $action->handle($restaurant, ['address' => 'Rua B']);

    expect($restaurant->orderSetting->fresh())->address->toBe('Rua B')->pix_key->toBe('chave');
});

it('rejects an invalid whatsapp number, timezone, mode or amount', function (array $data, string $field) {
    $restaurant = User::factory()->create()->restaurant;

    expect(fn () => app(SaveOrderSettings::class)->handle($restaurant, $data))->toThrow(ValidationException::class);

    try {
        app(SaveOrderSettings::class)->handle($restaurant, $data);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'whatsapp curto' => [['whatsapp_number' => '1234'], 'whatsapp_number'],
    'fuso inválido' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'modo inválido' => [['delivery_mode' => 'drone'], 'delivery_mode'],
    'valor inválido' => [['free_delivery_over' => 'abc'], 'free_delivery_over'],
]);

it('replaces the business hours atomically and validates them', function () {
    $restaurant = User::factory()->create()->restaurant;
    $action = app(SaveBusinessHours::class);

    $action->handle($restaurant, [
        1 => [['opens' => '11:00', 'closes' => '14:00'], ['opens' => '18:00', 'closes' => '23:00']],
        2 => [['opens' => '18:00', 'closes' => '23:00']],
    ]);

    expect($restaurant->businessHours()->count())->toBe(3);

    $action->handle($restaurant, [3 => [['opens' => '09:00', 'closes' => '12:00']]]);

    expect($restaurant->businessHours()->pluck('weekday')->all())->toBe([3]);

    expect(fn () => $action->handle($restaurant, [1 => [['opens' => '25:00', 'closes' => '23:00']]]))
        ->toThrow(ValidationException::class);

    expect($restaurant->businessHours()->count())->toBe(1);
});

it('does not let a dono change another store settings', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $setting = RestaurantOrderSetting::factory()->create(['restaurant_id' => $owner->restaurant_id]);

    expect(Gate::forUser($owner)->allows('update', $setting))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $setting))->toBeFalse();
});
