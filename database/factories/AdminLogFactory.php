<?php

namespace Database\Factories;

use App\Models\AdminAction;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminLog>
 */
class AdminLogFactory extends Factory
{
    protected $model = AdminLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->superAdmin(),
            'action_id' => AdminAction::query()->firstOrCreate(
                ['slug' => 'restaurante_suspenso'],
                ['name' => 'Restaurante Suspenso']
            )->id,
            'target' => null,
            'data' => null,
        ];
    }
}
