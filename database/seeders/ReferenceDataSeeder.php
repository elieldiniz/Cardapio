<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Every lookup table plus the baseline plans — the data the application
 * needs to run, independent of any demo content.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RestaurantStatusSeeder::class,
            DishStatusSeeder::class,
            VideoStatusSeeder::class,
            VideoOriginSeeder::class,
            GenerationStatusSeeder::class,
            GenerationLedgerTypeSeeder::class,
            MetricsLevelSeeder::class,
            DiscountTypeSeeder::class,
            BadgeSeeder::class,
            AdminActionSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
