<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Neighborhoods the store delivers to, each with its fee (RF-06b). A customer from a
     * neighborhood outside the list may still order with the fee "a combinar" when the
     * store allows it (`accepts_other_neighborhood`). Money is decimal(10,2) (RNF-02).
     */
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'display_order']);
        });

        Schema::table('restaurant_order_settings', function (Blueprint $table) {
            $table->boolean('accepts_other_neighborhood')->default(true)->after('min_order_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_settings', function (Blueprint $table) {
            $table->dropColumn('accepts_other_neighborhood');
        });

        Schema::dropIfExists('delivery_zones');
    }
};
