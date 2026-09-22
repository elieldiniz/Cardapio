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
        Schema::create('ai_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers');
            $table->string('name');
            $table->text('prompt');
            $table->string('camera_movement');
            $table->unsignedSmallInteger('duration_seconds');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_presets');
    }
};
