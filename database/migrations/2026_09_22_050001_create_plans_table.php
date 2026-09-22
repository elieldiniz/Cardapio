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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('stripe_price_id')->nullable()->unique();
            $table->unsignedInteger('monthly_generations')->default(0);
            $table->unsignedInteger('initial_generations')->default(0);
            $table->unsignedInteger('dish_limit')->nullable();
            $table->boolean('removes_branding')->default(false);
            $table->foreignId('metrics_level_id')->constrained('metrics_levels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
