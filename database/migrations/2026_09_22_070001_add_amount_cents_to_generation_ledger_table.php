<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the dono paid for a `compra` entry, so addon revenue can be
     * compared against costs (US-7.6). Null for non-purchase entries.
     */
    public function up(): void
    {
        Schema::table('generation_ledger', function (Blueprint $table) {
            $table->integer('amount_cents')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('generation_ledger', function (Blueprint $table) {
            $table->dropColumn('amount_cents');
        });
    }
};
