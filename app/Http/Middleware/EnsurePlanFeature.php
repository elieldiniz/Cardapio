<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the owner to the "assine para usar" page when the restaurant cannot use a feature
 * (RF-02d): its plan has it switched off and the 14-day trial is over. Used as `plan.feature:<key>`.
 *
 * The caixa screens stay open while there is still a caixa to close or a fiado to collect, so
 * the money of a store that lost the feature is never stuck.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $restaurant = $request->user()?->restaurant;

        if ($restaurant === null || ! $restaurant->hasFeature($feature)) {
            if ($feature === 'cash' && $restaurant !== null && $restaurant->hasCashToWrapUp()) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                abort(403, 'Este recurso não está disponível no seu plano.');
            }

            return redirect()->route('panel.locked', $feature);
        }

        return $next($request);
    }
}
