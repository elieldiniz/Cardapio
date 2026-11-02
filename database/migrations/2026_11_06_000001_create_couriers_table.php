<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motoboys (RF-22): a courier has a fee per delivery and a link (token) the dono can revoke.
     * The order remembers who took it, when it was delivered and whether the courier was already paid for it.
     */
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->decimal('fee_per_delivery', 10, 2)->default(0);
            $table->string('token', 40)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('courier_id')->references('id')->on('couriers')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('courier_id');
            $table->timestamp('courier_settled_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['courier_id']);
            $table->dropColumn(['delivered_at', 'courier_settled_at']);
        });

        Schema::dropIfExists('couriers');
    }
};
