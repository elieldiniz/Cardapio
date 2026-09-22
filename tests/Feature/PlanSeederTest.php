<?php

use App\Models\Plan;
use Database\Seeders\MetricsLevelSeeder;
use Database\Seeders\PlanSeeder;

it('seeds a free plan with five one time initial generations and no monthly renewal', function () {
    $this->seed(MetricsLevelSeeder::class);
    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(3);

    $free = Plan::where('name', 'Grátis')->firstOrFail();

    expect($free->initial_generations)->toBe(5)
        ->and($free->monthly_generations)->toBe(0)
        ->and($free->dish_limit)->toBe(15)
        ->and($free->removes_branding)->toBeFalse();

    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(3);
});
