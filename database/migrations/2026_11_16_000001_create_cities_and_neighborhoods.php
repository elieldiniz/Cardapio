<?php

use App\Services\Address\GeoDataImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brazilian municipalities and their neighborhoods, so the owner picks a city and ticks
     * the neighborhoods instead of typing them one by one (RF-06b). The IBGE lists load right
     * here, so a plain `migrate` on deploy already leaves the data in place.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ibge_code')->unique();
            $table->char('state', 2);
            $table->string('name', 120);
            $table->string('search_name', 120);
            // Set once the neighborhoods of a city IBGE does not cover were looked up elsewhere.
            $table->timestamp('neighborhoods_fetched_at')->nullable();
            $table->index(['state', 'search_name']);
        });

        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('source', 10)->default('ibge');
            $table->unique(['city_id', 'name']);
        });

        app(GeoDataImporter::class)->import();
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('cities');
    }
};
