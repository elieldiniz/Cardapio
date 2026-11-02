<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lookup tables for the order status and the payment method (RNF-03).
     */
    public function up(): void
    {
        foreach (['order_statuses', 'payment_methods'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('order_statuses');
    }
};
