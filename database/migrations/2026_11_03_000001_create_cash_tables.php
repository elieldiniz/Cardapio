<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash register, its movements and the "fiado" receivables (RF-09, RF-10, RF-11).
     * A store can have only one open session: a generated column holds the restaurant id
     * while the session is open (and NULL once closed) and carries the unique index, so two
     * simultaneous "abrir caixa" can never both win (RNF-04). A movement's `reference` is
     * unique, which makes recording the same sale twice harmless. Money is decimal(10,2) and
     * every row carries `restaurant_id` (RNF-01, RNF-02).
     */
    public function up(): void
    {
        Schema::create('cash_movement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_float', 10, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('open_restaurant_id')->nullable()->virtualAs('IF(closed_at IS NULL, restaurant_id, NULL)');
            $table->timestamps();

            $table->unique('open_restaurant_id');
            $table->index(['restaurant_id', 'opened_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('cash_movement_types');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('category', 60)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('reference', 80)->nullable()->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'occurred_at']);
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20)->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('reference', 80)->nullable()->unique();
            $table->timestamps();

            $table->index(['restaurant_id', 'paid_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cash_session_id']);
        });

        Schema::dropIfExists('receivables');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_movement_types');
    }
};
