<?php

// app/Services/FrontEndStats.php

namespace App\Services;

use App\Models\NotionPosts;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FrontEndStats
{
    /**
     * Headline numbers for the landing's social-proof card.
     *
     * The API and the landing now share one database, so this reads the counts
     * directly instead of calling the old external /admin/frontEndStats endpoint
     * over HTTP (no network hop, no down-API fallback needed).
     *
     * The +1000 / +4000 offsets are the long-standing vanity baseline carried
     * over from the legacy service so the displayed figures stay continuous —
     * tweak or drop them here if you want to show the true counts.
     *
     * Uses its own cache key: AdminController::returnStatsForFrontend() caches
     * the RAW counts under 'frontend_stats', so this padded variant must not
     * share that key.
     */
    public function get(): array
    {
        return Cache::remember('landing_social_proof_stats', now()->addHour(), function () {
            return [
                'users' => User::count() + 1000,
                'published_posts' => NotionPosts::where('status', 'posted')->count() + 4000,
            ];
        });
    }
}
