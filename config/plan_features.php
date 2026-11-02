<?php

/*
|--------------------------------------------------------------------------
| Plan feature switches (RF-02d, RF-21)
|--------------------------------------------------------------------------
|
| Orders are available on every plan. The free plan has every feature during its
| 14-day trial (`restaurants.trial_ends_at`), then only what `by_plan` allows. A plan can override any key through its
| `features` JSON column; a missing key falls back to the default below.
| Advanced statistics are gated by the plan's metrics level, not by a switch.
|
*/

return [
    /*
    | Commercial matrix: what each plan keeps after the 14-day trial (the free plan has everything
    | during the trial). Used by the PlanSeeder and the migration that introduced the trial.
    */
    'by_plan' => [
        'Grátis' => ['orders' => true, 'delivery' => true, 'cash' => false, 'pdv' => false, 'promo_codes' => false, 'loyalty' => false, 'couriers' => false],
        'Básico' => ['orders' => true, 'delivery' => true, 'cash' => true, 'pdv' => true, 'promo_codes' => true, 'loyalty' => false, 'couriers' => false],
        'Pro' => ['orders' => true, 'delivery' => true, 'cash' => true, 'pdv' => true, 'promo_codes' => true, 'loyalty' => true, 'couriers' => true],
    ],

    'trial_days' => 14,

    'defaults' => [
        'orders' => true,
        'cash' => true,
        'pdv' => true,
        'delivery' => true,
        'promo_codes' => true,
        'loyalty' => true,
        'couriers' => true,
    ],
];
