<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users who haven't finished onboarding on the setup wizard. Only the
 * Dashboard is gated — Pricing, Affiliates and Support stay reachable during
 * onboarding so the user can look around before committing. The wizard's own
 * action endpoints (connect, scan, finishedWizard, etc.) pass through untouched.
 */
class EnsureWizardCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->completed_wizard
            && $request->routeIs('dashboard')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
