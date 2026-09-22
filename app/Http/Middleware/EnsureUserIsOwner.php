<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the restaurant panel to authenticated `dono` users that own a restaurant.
 */
class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isOwner() && $user->restaurant_id !== null, 403);

        return $next($request);
    }
}
