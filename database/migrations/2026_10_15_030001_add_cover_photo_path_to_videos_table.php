<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The cover the dono chose for a video: the photo a generated video was made from, or the
     * optional cover sent with an own video. `cover_path` keeps the Mux frame set when the
     * video is ready and stays as the fallback.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable()->after('cover_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('cover_photo_path');
        });
    }
};
