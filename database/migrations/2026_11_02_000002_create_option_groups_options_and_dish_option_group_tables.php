<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable groups of complements (sizes, add-ons, edges, sauces…) that the
     * customer chooses before adding a product (RF-03). A group belongs to one
     * restaurant (RNF-01); an option's extra price is decimal(10,2), null = free
     * (RNF-02). `dish_variants` stays untouched: a variant is an alternative price
     * of the product, a complement only adds to it.
     */
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('mode', 10)->default('one'); // one | many
            $table->unsignedSmallInteger('max_choices')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_repeat')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'display_order']);
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dish_option_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);

            $table->unique(['dish_id', 'option_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dish_option_group');
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
    }
};
