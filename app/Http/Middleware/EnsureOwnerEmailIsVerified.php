<?php

namespace App\Http\Middleware;

use App\Actions\Admin\Impersonation;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dono panel requires a confirmed e-mail — except while a super admin is
 * impersonating the account for support.
 */
class EnsureOwnerEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() && ! Impersonation::active()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
