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
            // At least R$ 3,90 per generation: below that an add-on pack loses money (see Docs/custos-e-precificacao.md).
            ['name' => '10 gerações', 'generations_count' => 10, 'price_cents' => 3900],
            ['name' => '25 gerações', 'generations_count' => 25, 'price_cents' => 8900],
        ];

        foreach ($packages as $package) {
            VideoAddonPackage::query()->updateOrCreate(
                ['name' => $package['name']],
                $package + ['is_active' => true],
            );
        }
    }
}
