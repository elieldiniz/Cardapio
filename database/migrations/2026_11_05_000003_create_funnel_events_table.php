<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordering funnel (RF-21): one row per (session, step, product, day), so repeating a step in
     * the same visit never inflates the numbers. `dish_id` is 0 for steps that are not about a product.
     * The last two steps of the funnel (WhatsApp opened, confirmed) come from the orders themselves.
     */
    public function up(): void
    {
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 64);
            $table->string('event', 12); // visit | add | checkout
            $table->unsignedBigInteger('dish_id')->default(0);
            $table->date('occurred_on');
            $table->unsignedTinyInteger('hour');
            $table->timestamp('created_at')->nullable();

            $table->unique(['restaurant_id', 'session_token', 'event', 'dish_id', 'occurred_on'], 'funnel_events_dedupe');
            $table->index(['restaurant_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};
