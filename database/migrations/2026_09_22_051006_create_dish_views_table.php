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
        Schema::create('dish_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained();
            $table->foreignId('restaurant_id')->constrained();
            $table->string('session_token');
            $table->unsignedInteger('seconds_watched')->default(0);
            $table->date('viewed_on');
            // Append-only, aggregated per day: created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_views');
    }
};
