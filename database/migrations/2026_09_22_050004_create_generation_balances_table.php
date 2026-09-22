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
        Schema::create('generation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained();
            $table->unsignedInteger('monthly_balance')->default(0);
            $table->unsignedInteger('addon_balance')->default(0);
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_balances');
    }
};
