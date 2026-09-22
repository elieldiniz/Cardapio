<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
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
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
