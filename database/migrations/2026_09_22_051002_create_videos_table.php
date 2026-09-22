<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained();
            $table->foreignId('origin_id')->constrained('video_origins');
            // No ->constrained(): the `video_generations` table is created later in this same phase.
            $table->foreignId('generation_id')->nullable();
            $table->foreignId('status_id')->constrained('video_statuses');
            $table->string('mux_asset_id')->nullable();
            $table->string('mux_playback_id')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
