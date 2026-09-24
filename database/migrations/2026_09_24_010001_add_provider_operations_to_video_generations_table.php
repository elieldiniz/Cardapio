<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Async providers (e.g. Gemini Veo) return one long-running operation per
     * variation; their handles are kept here while the poll job waits on them.
     */
    public function up(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->json('provider_operations')->nullable()->after('variations_requested');
        });
    }

    public function down(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropColumn('provider_operations');
        });
    }
};
