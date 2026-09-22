<?php

use App\Models\GenerationBalance;
use App\Models\Restaurant;

it('reports enough balance only when monthly plus addon covers the request', function () {
    $restaurant = Restaurant::factory()->create();

    $balance = GenerationBalance::create([
        'restaurant_id' => $restaurant->id,
        'monthly_balance' => 2,
        'addon_balance' => 1,
    ]);

    expect($balance->hasEnoughBalance(3))->toBeTrue()
        ->and($balance->hasEnoughBalance(4))->toBeFalse();
});
