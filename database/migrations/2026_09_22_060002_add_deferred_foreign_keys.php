<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys whose referenced table is created by a later migration.
     * MySQL (unlike SQLite) refuses a constraint to a table that doesn't
     * exist yet, so they are wired here, once every table exists.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->foreign('generation_id')->references('id')->on('video_generations');
        });
    }

    public function down(): void
    {
        Schema::table('videos', fn (Blueprint $table) => $table->dropForeign(['generation_id']));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropForeign(['restaurant_id']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropForeign(['role_id']);
        });
    }
};
