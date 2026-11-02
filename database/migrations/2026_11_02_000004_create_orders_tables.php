<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders, their items (a snapshot of what was bought), payments, status history and
     * customers (RF-06, RF-02a, RF-07a). Everything carries `restaurant_id` (RNF-01) and
     * money is decimal(10,2) (RNF-02). Columns that will point to tables created in later
     * phases (promo code, courier, cash session) are plain unsigned ids for now.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'whatsapp']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('tracking_token', 40)->unique();
            $table->foreignId('status_id')->constrained('order_statuses');
            $table->string('fulfillment', 10); // entrega | retirada | balcao
            $table->string('channel', 10)->default('feed'); // feed | pdv
            $table->string('origin', 20)->default('direto');
            $table->string('campaign', 60)->nullable();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();

            // Delivery address: personal data, purged after 30 days (RNF-08).
            $table->string('address_street', 150)->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement', 80)->nullable();
            $table->string('neighborhood', 60)->nullable();
            $table->string('address_reference', 150)->nullable();
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->nullable(); // null = "a combinar"
            $table->boolean('delivery_fee_pending')->default(false);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedBigInteger('promo_code_id')->nullable();

            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods');
            $table->decimal('change_for', 10, 2)->nullable();

            $table->boolean('placed_off_hours')->default(false);
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->unsignedBigInteger('cash_session_id')->nullable();

            $table->timestamp('whatsapp_opened_at')->nullable();
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('personal_data_purged_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'code']);
            $table->index(['restaurant_id', 'status_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160); // snapshot: later edits to the product never change it
            $table->string('sale_type', 8)->default('unit'); // unit | kg
            $table->decimal('quantity', 8, 3)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('notes', 160)->nullable();
            $table->json('selections')->nullable(); // size, flavors, complements chosen, as bought
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->decimal('amount', 10, 2);
            $table->decimal('received', 10, 2)->nullable();
            $table->decimal('change', 10, 2)->nullable();
            $table->string('reference', 80)->nullable()->unique(); // idempotency key (RNF-04)
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('order_statuses');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
