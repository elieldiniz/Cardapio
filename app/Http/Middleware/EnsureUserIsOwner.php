<?php

namespace App\Http\Middleware;

use App\Actions\Admin\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the restaurant panel to authenticated `dono` users that own an active restaurant.
 */
class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isOwner() && $user->restaurant_id !== null, 403);

        // A suspended account is locked out (US-7.2) — except for a super admin giving support.
        if ($user->restaurant->isSuspended() && ! Impersonation::active()) {
            return response()->view('panel.suspended', ['restaurant' => $user->restaurant], 403);
        }

        return $next($request);
    }
}
