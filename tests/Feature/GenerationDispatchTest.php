<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\VideoGeneration;
use Tests\Fakes\FakeVideoGenerationProvider;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
});

test('a successful generation creates one video row per variation', function () {
    [, $dish] = ownerWithDish(photos: 1, addon: 5);

    // Queue is sync in tests: the job runs right after the request commits.
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 3)->fresh(['status', 'videos.status', 'videos.origin']);

    expect($generation->status->slug)->toBe('pronto')
        ->and((float) $generation->cost_usd)->toBe(0.75)
        ->and($generation->videos)->toHaveCount(3)
        ->and($generation->videos->pluck('status.slug')->unique()->all())->toBe(['processando'])
        ->and($generation->videos->pluck('origin.slug')->unique()->all())->toBe(['ia'])
        ->and($generation->videos->pluck('mux_asset_id')->all())->toBe(['asset-1', 'asset-2', 'asset-3'])
        ->and(collect($this->mux->assets)->pluck('input')->all())->toBe([
            'https://provider.test/clips/1.mp4',
            'https://provider.test/clips/2.mp4',
            'https://provider.test/clips/3.mp4',
        ]);
});

test('a provider error marks the generation as erro and does not debit the balance', function () {
    FakeVideoGenerationProvider::$shouldFail = true;
    [, $dish] = ownerWithDish(photos: 1, monthly: 2, addon: 3);

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2)->fresh(['status', 'videos']);

    $balance = GenerationBalance::where('restaurant_id', $dish->restaurant_id)->first();

    expect($generation->status->slug)->toBe('erro')
        ->and($generation->videos)->toBeEmpty()
        ->and($balance->monthly_balance)->toBe(2)
        ->and($balance->addon_balance)->toBe(3)
        ->and(GenerationLedger::count())->toBe(0)
        ->and($this->mux->assets)->toBeEmpty();
});

it('never asks for a full turn with a single photo and allows a wide turn with 3 or more angles', function () {
    [, $single] = ownerWithDish(photos: 1, addon: 10);
    [, $multi] = ownerWithDish(photos: 3, addon: 10);

    app(RequestVideoGeneration::class)->handle($single, $single->photos->pluck('id')->all(), 2);
    app(RequestVideoGeneration::class)->handle($multi, $multi->photos->pluck('id')->all(), 2);

    [$singleCall, $multiCall] = FakeVideoGenerationProvider::$calls;

    expect($singleCall['preset']['turn'])->toBe(VideoGeneration::TURN_LIMITED)
        ->and($singleCall['photos'])->toHaveCount(1)
        ->and($multiCall['preset']['turn'])->toBe(VideoGeneration::TURN_WIDE)
        ->and($multiCall['photos'])->toHaveCount(3)
        ->and($multiCall['preset']['aspect_ratio'])->toBe('9:16');
});
