<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The dono can adjust the chosen style or write their own description of
     * the video. It is kept on the generation (null = the preset's own prompt).
     */
    public function up(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->text('custom_prompt')->nullable()->after('preset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropColumn('custom_prompt');
        });
    }
};
