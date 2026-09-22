<?php

namespace App\Actions;

use App\Models\GenerationBalance;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-service signup (US-8.1, US-5.1): every new account is a `dono` owning a
 * fresh restaurant on the Grátis plan, with the plan's one-time generations.
 */
class RegisterRestaurantOwner
{
    public const FREE_PLAN_NAME = 'Grátis';

    /**
     * @param  array{name: string, restaurant_name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::query()->where('name', self::FREE_PLAN_NAME)->firstOrFail();

            $restaurant = Restaurant::create([
                'plan_id' => $plan->id,
                'status_id' => RestaurantStatus::query()->where('slug', 'ativo')->value('id'),
                'name' => $data['restaurant_name'],
                'slug' => $this->uniqueSlug($data['restaurant_name']),
            ]);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->restaurant_id = $restaurant->id;
            $user->role_id = Role::query()->where('slug', 'dono')->value('id');
            $user->save();

            GenerationBalance::create([
                'restaurant_id' => $restaurant->id,
                'monthly_balance' => 0,
                'addon_balance' => $plan->initial_generations,
            ]);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'restaurante';
        $slug = $base;
        $suffix = 2;

        while (Restaurant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
