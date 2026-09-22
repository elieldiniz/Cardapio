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
        Schema::create('video_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained();
            $table->foreignId('preset_id')->constrained('ai_presets');
            $table->foreignId('provider_id')->constrained('ai_providers');
            $table->foreignId('status_id')->constrained('generation_statuses');
            $table->unsignedSmallInteger('variations_requested')->default(2);
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_generations');
    }
};
