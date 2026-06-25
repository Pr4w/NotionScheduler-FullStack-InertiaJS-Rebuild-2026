<?php

// app/Services/FrontEndStats.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FrontEndStats
{
    public function get(): array
    {
        return Cache::remember('frontend_stats', now()->addHour(), function () {
            try {
                $response = Http::timeout(5)->get('https://api.notionscheduler.app/admin/frontEndStats');

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'users' => (int) ($data['users'] + 1000 ?? 0),
                        'published_posts' => (int) ($data['published_posts'] + 4000 ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
            }

            // Fallback if the API is down/slow — cached zeros for a SHORT
            // time so we retry soon rather than showing 0 for an hour.
            Cache::put('frontend_stats', ['users' => 0, 'published_posts' => 0], now()->addMinutes(2));

            return ['users' => 0, 'published_posts' => 0];
        });
    }
}
