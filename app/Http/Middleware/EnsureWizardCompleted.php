<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users who haven't finished onboarding on the setup wizard. Only the
 * main app *pages* are gated (by route name) — the wizard's own action
 * endpoints (connect, scan, finishedWizard, etc.) pass through untouched.
 */
class EnsureWizardCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->completed_wizard
            && $request->routeIs('dashboard', 'pricing', 'affiliates', 'support')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
