<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loyalty card (RF-18): one program per store; each customer has one open card at a time
     * (a card is closed when its reward is redeemed). The order remembers the card it stamped
     * and whether the stamp was already given back, so finalizing or cancelling twice is harmless.
     */
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('stamps_required')->default(10);
            $table->decimal('min_order', 10, 2)->nullable();
            $table->string('reward_type', 10)->default('brinde'); // percentual | valor | brinde
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->string('reward_label', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('stamps')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['loyalty_program_id', 'customer_id', 'redeemed_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('loyalty_card_id')->nullable()->after('promo_released_at')->constrained('loyalty_cards')->nullOnDelete();
            $table->timestamp('loyalty_reverted_at')->nullable()->after('loyalty_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_card_id');
            $table->dropColumn('loyalty_reverted_at');
        });

        Schema::dropIfExists('loyalty_cards');
        Schema::dropIfExists('loyalty_programs');
    }
};
