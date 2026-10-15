<?php

use App\Models\Plan;
use Database\Seeders\MetricsLevelSeeder;
use Database\Seeders\PlanSeeder;

it('seeds the new plan model: a free trial, paid plans without monthly generations and a first-subscription bonus', function () {
    $this->seed(MetricsLevelSeeder::class);
    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(3);

    $free = Plan::where('name', 'Grátis')->firstOrFail();

    $basic = Plan::where('name', 'Básico')->firstOrFail();
    $pro = Plan::where('name', 'Pro')->firstOrFail();

    expect($basic->price_cents)->toBe(3990)
        ->and($basic->monthly_generations)->toBe(0)
        ->and($basic->first_subscription_bonus)->toBe(6)
        ->and($pro->price_cents)->toBe(7990)
        ->and($pro->monthly_generations)->toBe(0)
        ->and($pro->first_subscription_bonus)->toBe(12)
        ->and($pro->dish_limit)->toBeNull();

    expect($free->initial_generations)->toBe(3)
        ->and($free->first_subscription_bonus)->toBe(0)
        ->and($free->monthly_generations)->toBe(0)
        ->and($free->dish_limit)->toBe(15)
        ->and($free->removes_branding)->toBeFalse();

    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(3);
});
