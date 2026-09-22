<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * "Entrar como o dono" (US-7.2): the super admin continues as the restaurant's
 * dono without its password, clearly bannered, and can return to /admin.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function start(User $admin, Restaurant $restaurant): User
    {
        if (! $admin->isSuperAdmin()) {
            throw new InvalidArgumentException('Only a super admin can impersonate.');
        }

        $owner = $restaurant->users()
            ->whereHas('role', fn ($role) => $role->where('slug', 'dono'))
            ->orderBy('id')
            ->firstOrFail();

        AdminLog::record($admin, 'restaurante_impersonado', "restaurant:{$restaurant->id}", ['as_user_id' => $owner->id]);

        Auth::guard('web')->login($owner);
        session()->regenerate();
        session()->put(self::SESSION_KEY, $admin->id);

        return $owner;
    }

    public function stop(): ?User
    {
        $admin = User::find(session()->pull(self::SESSION_KEY));

        if ($admin === null || ! $admin->isSuperAdmin()) {
            Auth::guard('web')->logout();

            return null;
        }

        Auth::guard('web')->login($admin);
        session()->regenerate();

        return $admin;
    }
}
