<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The dono's QR code / table display choices live on the restaurant; whether the
     * full customization (style, logo, models, texts) is available is a plan feature.
     * Paid plans that already exist get it on, so nothing is taken away from them.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('qr_settings')->nullable()->after('font');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('qr_customization')->default(false)->after('removes_branding');
        });

        DB::table('plans')->where('price_cents', '>', 0)->update(['qr_customization' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('qr_customization');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('qr_settings');
        });
    }
};
