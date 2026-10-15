<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatic video cleanup (videos:prune): when a video was approved, and
     * whether the dono was already warned that an unreviewed round expires.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('duration_seconds');
        });

        Schema::table('video_generations', function (Blueprint $table) {
            $table->timestamp('expiry_warned_at')->nullable()->after('cost_usd');
        });

        // Videos that are or were on the cardápio: approved (active) or replaced with a playback id.
        $statusIds = DB::table('video_statuses')->whereIn('slug', ['aprovado', 'rejeitado'])->pluck('id');

        DB::table('videos')
            ->whereIn('status_id', $statusIds)
            ->whereNotNull('mux_playback_id')
            ->update(['approved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });

        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropColumn('expiry_warned_at');
        });
    }
};
