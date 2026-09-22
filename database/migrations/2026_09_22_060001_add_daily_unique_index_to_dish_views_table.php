<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * dish_views is aggregated per (dish, session, day): one row accumulates
     * watch time instead of one row per report (Phase 14.3).
     */
    public function up(): void
    {
        Schema::table('dish_views', function (Blueprint $table) {
            $table->unique(['dish_id', 'session_token', 'viewed_on']);
            $table->index(['restaurant_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::table('dish_views', function (Blueprint $table) {
            $table->dropUnique(['dish_id', 'session_token', 'viewed_on']);
            $table->dropIndex(['restaurant_id', 'viewed_on']);
        });
    }
};
