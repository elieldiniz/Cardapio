<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account deletion by the dono goes to a trash first: the super admin
     * later restores it or purges it for good from /admin → Restaurantes.
     * (Deliberate exception to the "no soft delete" schema convention.)
     */
    public function up(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('users', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
