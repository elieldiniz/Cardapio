<?php

namespace App\Actions;

use App\Models\GenerationBalance;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
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
     * @param  array{name: string, restaurant_name: string, email: string, password: string, business_type?: ?string}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $plan = Plan::query()->where('name', self::FREE_PLAN_NAME)->firstOrFail();

            $restaurant = Restaurant::create([
                'plan_id' => $plan->id,
                'status_id' => RestaurantStatus::query()->where('slug', 'ativo')->value('id'),
                'name' => $data['restaurant_name'],
                'slug' => $this->uniqueSlug($data['restaurant_name']),
                'business_type' => $this->businessType($data['business_type'] ?? null),
                'trial_ends_at' => now()->addDays((int) config('plan_features.trial_days', 14)),
            ]);

            $this->seedCategories($restaurant);

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

        // Sends the "confirme seu e-mail" link (the panel stays locked until it is used).
        event(new Registered($user));

        return $user;
    }

    /**
     * Only a configured business type is stored; anything else means "none chosen" (RF-02c).
     */
    private function businessType(?string $type): ?string
    {
        return $type !== null && array_key_exists($type, config('business_types', [])) ? $type : null;
    }

    /**
     * A chosen business type gives the new restaurant its starter categories, inside the
     * signup transaction (RF-02c). Without a type no category is created.
     */
    private function seedCategories(Restaurant $restaurant): void
    {
        $categories = config("business_types.{$restaurant->business_type}.categories", []);

        foreach ($categories as $index => $category) {
            $restaurant->categories()->create([
                'name' => $category['name'],
                'icon' => $category['icon'] ?? null,
                'display_order' => $index + 1,
            ]);
        }
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
