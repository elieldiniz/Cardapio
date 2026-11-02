<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order-taking settings (RF-01, RF-02) and weekly business hours, one set per
     * restaurant (RNF-01). The store keeps its own timezone while the app stays on
     * UTC (RNF-09); money columns are decimal(10,2) (RNF-02).
     */
    public function up(): void
    {
        Schema::create('restaurant_order_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('pix_key')->nullable();
            $table->string('pix_holder')->nullable();
            $table->string('pix_city')->nullable();
            $table->boolean('accepts_orders')->default(true);
            $table->boolean('hours_enabled')->default(false);
            $table->boolean('accepts_when_closed')->default(false);
            $table->string('timezone', 64)->default('America/Porto_Velho');
            $table->string('delivery_mode', 20)->default('a_combinar');
            $table->decimal('free_delivery_over', 10, 2)->nullable();
            $table->decimal('min_order_delivery', 10, 2)->nullable();
            $table->string('pizza_pricing_rule', 10)->default('maior');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = domingo … 6 = sábado
            $table->unsignedTinyInteger('shift')->default(1);
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['restaurant_id', 'weekday', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('restaurant_order_settings');
    }
};
