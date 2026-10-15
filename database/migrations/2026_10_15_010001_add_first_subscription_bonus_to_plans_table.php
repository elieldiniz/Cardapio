<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A plan can give a one-time welcome bonus of generations when a restaurant
     * pays its first subscription (once per restaurant, never again). The
     * ledger gets a "bonus" type to record it; the row ships with the code so
     * crediting never depends on a manual seed.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('first_subscription_bonus')->default(0)->after('initial_generations');
        });

        DB::table('generation_ledger_types')->updateOrInsert(
            ['slug' => 'bonus'],
            ['name' => 'Brinde', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('first_subscription_bonus');
        });

        DB::table('generation_ledger_types')->where('slug', 'bonus')->delete();
    }
};
