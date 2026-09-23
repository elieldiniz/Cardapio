<?php

namespace Database\Seeders;

use App\Models\VideoAddonPackage;
use Illuminate\Database\Seeder;

class VideoAddonPackageSeeder extends Seeder
{
    /**
     * Seed the default one-time generation packages idempotently.
     *
     * Packages only add AI video generations (addon_balance, never expires);
     * they don't change any plan limit. Editable later in the admin (US-7.3);
     * the Stripe price id is linked there once the product exists on Stripe.
     */
    public function run(): void
    {
        $packages = [
            ['name' => '20 gerações', 'generations_count' => 20, 'price_cents' => 1000],
            ['name' => '40 gerações', 'generations_count' => 40, 'price_cents' => 2000],
        ];

        foreach ($packages as $package) {
            VideoAddonPackage::query()->updateOrCreate(
                ['name' => $package['name']],
                $package + ['is_active' => true],
            );
        }
    }
}
