<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 14-day trial with every feature on, for restaurants on the free plan. The clock starts at signup;
     * restaurants that already exist get 14 days from today. Afterwards the free plan keeps only what
     * its `features` allow, and nothing is deleted.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('menu_style');
        });

        $freePlanIds = DB::table('plans')->where('price_cents', 0)->pluck('id');

        DB::table('restaurants')->whereIn('plan_id', $freePlanIds)->update(['trial_ends_at' => now()->addDays(14)]);

        // Plans still on the untouched defaults get the commercial matrix; edited plans are left alone.
        $defaults = config('plan_features.defaults');

        foreach (config('plan_features.by_plan') as $name => $features) {
            $plan = Plan::query()->where('name', $name)->first();
            $current = $plan?->features;

            if ($plan !== null && ($current === null || $current === $defaults)) {
                $plan->update(['features' => $features]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('trial_ends_at'));
    }
};
