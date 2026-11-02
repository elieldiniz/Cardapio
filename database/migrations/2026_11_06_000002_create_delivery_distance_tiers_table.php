<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Delivery fee by distance (RF-24a): ranges of km from the store, each with its fee.
     * The order remembers the distance it was priced with.
     */
    public function up(): void
    {
        Schema::create('delivery_distance_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_km', 6, 2);
            $table->decimal('to_km', 6, 2);
            $table->decimal('fee', 10, 2);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('distance_km', 6, 2)->nullable()->after('delivery_fee_pending');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('distance_km'));
        Schema::dropIfExists('delivery_distance_tiers');
    }
};
