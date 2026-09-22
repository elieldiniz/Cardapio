<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costs the super admin enters by hand for now (US-7.6): the month's Mux
     * bill and the USD→BRL rate used to compare USD costs with BRL revenue.
     */
    public function up(): void
    {
        Schema::create('monthly_costs', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->decimal('mux_cost_usd', 10, 2)->default(0);
            $table->decimal('usd_brl_rate', 8, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_costs');
    }
};
