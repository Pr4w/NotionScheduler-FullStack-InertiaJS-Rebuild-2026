<?php

namespace App\Http\Controllers;

use App\Models\NotionAccessTokens;
use App\Models\NotionSocialAccounts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    /**
     * The guided onboarding wizard. Reports the user's current connection
     * state so the page can resume at the right step; the actual actions
     * (connect Notion, add database, connect socials) reuse the same endpoints
     * the dashboard uses.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('app/Setup', [
            'hasNotionToken' => NotionAccessTokens::where('userid', $userId)
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->exists(),
            'databasesCount' => $request->user()->getActiveDatabaseCount(),
            'socialsCount' => NotionSocialAccounts::where('userid', $userId)
                ->where('is_active', 1)
                ->count(),
            'completedWizard' => (bool) $request->user()->completed_wizard,
        ]);
    }
}
