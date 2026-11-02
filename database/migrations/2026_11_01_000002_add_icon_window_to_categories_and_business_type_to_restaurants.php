<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A category can carry an icon and its own time window ("cardápio do almoço",
     * RF-02b); the restaurant records its business type chosen at signup (RF-02c).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 16)->nullable()->after('name');
            $table->time('shows_from')->nullable()->after('is_visible');
            $table->time('shows_until')->nullable()->after('shows_from');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'shows_from', 'shows_until']);
        });
    }
};
