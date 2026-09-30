<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const ADMIN_ACTIONS = [
        'chamado_respondido' => 'Chamado Respondido',
        'chamado_fechado' => 'Chamado Fechado',
        'chamado_reaberto' => 'Chamado Reaberto',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // The dono's ticket list: their restaurant, most recent first.
            $table->index(['restaurant_id', 'last_message_at']);
        });

        // The audit lookup rows ship with the code so replying never depends on a manual seed.
        foreach (self::ADMIN_ACTIONS as $slug => $name) {
            DB::table('admin_actions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_actions')->whereIn('slug', array_keys(self::ADMIN_ACTIONS))->delete();

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'last_message_at']);
        });
    }
};
