<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advanced products (RF-14, RF-15, RF-16): how a product is sold (unit or by weight), its
     * kind (common or pizza), a promotional price with an optional end, the "destaque" flag,
     * the pizza sizes / flavors / prices, and the store's promotional banner. Existing
     * products keep working: every new column has a safe default. Money is decimal(10,2).
     */
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->string('sale_type', 8)->default('unit')->after('price'); // unit | kg
            $table->string('kind', 10)->default('common')->after('sale_type'); // common | pizza
            $table->decimal('promo_price', 10, 2)->nullable()->after('kind');
            $table->timestamp('promo_ends_at')->nullable()->after('promo_price');
            $table->boolean('is_featured')->default(false)->after('promo_ends_at');
        });

        Schema::create('pizza_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedTinyInteger('slices')->nullable();
            $table->unsignedTinyInteger('max_flavors')->default(1);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pizza_flavors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('section', 40)->default('Tradicionais');
            $table->string('ingredients', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pizza_flavor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pizza_flavor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pizza_size_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);

            $table->unique(['pizza_flavor_id', 'pizza_size_id']);
        });

        Schema::create('promo_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title', 70);
            $table->string('text', 140)->nullable();
            $table->string('image_path')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('original_total', 10, 2)->nullable()->after('total');
            $table->timestamp('adjusted_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['original_total', 'adjusted_at']);
        });

        Schema::dropIfExists('promo_banners');
        Schema::dropIfExists('pizza_flavor_prices');
        Schema::dropIfExists('pizza_flavors');
        Schema::dropIfExists('pizza_sizes');

        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'kind', 'promo_price', 'promo_ends_at', 'is_featured']);
        });
    }
};
