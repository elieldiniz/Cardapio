<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a generation failed, kept next to it so support can read it in the admin instead of digging in the log.
     */
    public function up(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->after('cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('video_generations', fn (Blueprint $table) => $table->dropColumn('failure_reason'));
    }
};
