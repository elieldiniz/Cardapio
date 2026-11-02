<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * RF-02d — per-plan feature switches and the `plan.feature` middleware.
 */
beforeEach(function () {
    seedReferenceData();

    Route::middleware(['web', 'auth', 'plan.feature:cash'])->get('/_test/plan-feature', fn () => 'ok');
});

it('gives every seeded plan orders and follows the commercial matrix for the rest', function () {
    foreach (Plan::all() as $plan) {
        expect($plan->hasFeature('orders'))->toBeTrue();

        foreach (config("plan_features.by_plan.{$plan->name}") as $feature => $on) {
            expect($plan->hasFeature($feature))->toBe($on);
        }
    }

    expect(Plan::where('name', 'Grátis')->first()->hasFeature('cash'))->toBeFalse()
        ->and(Plan::where('name', 'Pro')->first()->hasFeature('couriers'))->toBeTrue();
});

it('falls back to the default for a plan without a features override', function () {
    $plan = Plan::query()->first();
    $plan->update(['features' => null]);

    expect($plan->hasFeature('pdv'))->toBeTrue()
        ->and($plan->hasFeature('unknown-feature'))->toBeFalse();
});

it('answers 200 when the plan has the feature and sends to the upgrade page when it is switched off', function () {
    $dono = User::factory()->create();
    $plan = $dono->restaurant->plan;

    $this->actingAs($dono)->get('/_test/plan-feature')->assertOk();

    $plan->update(['features' => ['cash' => false]]);

    // During the 14-day trial the free plan has everything, whatever its switches say.
    $this->actingAs($dono->fresh())->get('/_test/plan-feature')->assertOk();

    endTrial($dono->restaurant);

    $this->actingAs($dono->fresh())->get('/_test/plan-feature')->assertRedirect(route('panel.locked', 'cash'));
    $this->actingAs($dono->fresh())->getJson('/_test/plan-feature')->assertForbidden();
});
