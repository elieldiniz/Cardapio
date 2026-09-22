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
        Schema::create('video_generation_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_generation_id')->constrained();
            $table->foreignId('dish_photo_id')->constrained();
            $table->unsignedSmallInteger('angle_order')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_generation_photos');
    }
};
