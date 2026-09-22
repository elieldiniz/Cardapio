<?php

use App\Actions\Ai\DebitGenerationBalance;
use App\Actions\Ai\RequestVideoGeneration;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use Tests\Fakes\FakeVideoGenerationProvider;

beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
});

test('a successful generation debits monthly balance before addon balance', function () {
    [, $dish] = ownerWithDish(photos: 1, monthly: 2, addon: 5);

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 3);

    $balance = GenerationBalance::where('restaurant_id', $dish->restaurant_id)->first();
    $ledger = GenerationLedger::with('type')->where('restaurant_id', $dish->restaurant_id)->get();

    expect($balance->monthly_balance)->toBe(0)
        ->and($balance->addon_balance)->toBe(4)
        ->and($ledger)->toHaveCount(3)
        ->and($ledger->pluck('type.slug')->unique()->all())->toBe(['uso'])
        ->and($ledger->sum('quantity'))->toBe(-3)
        ->and($ledger->pluck('reference')->unique()->all())->toBe([DebitGenerationBalance::reference($generation)]);
});

test('a failed generation leaves the ledger untouched', function () {
    FakeVideoGenerationProvider::$shouldFail = true;
    [, $dish] = ownerWithDish(photos: 1, monthly: 2, addon: 5);

    app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2);

    expect(GenerationLedger::count())->toBe(0)
        ->and(GenerationBalance::where('restaurant_id', $dish->restaurant_id)->value('monthly_balance'))->toBe(2);
});

it('never debits the same generation twice', function () {
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2);

    app(DebitGenerationBalance::class)->handle($generation, 2);

    expect(GenerationLedger::count())->toBe(2)
        ->and(GenerationBalance::where('restaurant_id', $dish->restaurant_id)->value('addon_balance'))->toBe(3);
});
