<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `restaurants` is created before `plans` (Phase 3 vs Phase 5), so the
     * plan_id constraint is wired here once the `plans` table exists.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
    }
};
