<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header of the cardápio's opening grid (mockup "Cardápio Fumaça - Feed"):
     * a cover photo and a short description, edited in Aparência.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->string('description', 200)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'description']);
        });
    }
};
