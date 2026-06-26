<?php

namespace App\Console\Commands;

use App\Jobs\QueryPostMetrics;
use App\Models\NotionPosts;
use Illuminate\Console\Command;

class ScrapeMetrics extends Command
{
    protected $signature = 'metrics:scrape';

    protected $description = 'Dispatch jobs to scrape social metrics for recent posts';

    public function handle(): int
    {
        $count = 0;

        $tiktokSince = '2026-06-18 00:00:00'; // prod deploy datetime for video.list
        $linkedinSince = '2026-06-09 00:00:00'; // prod deploy datetime for the LinkedIn scopes

        NotionPosts::query()
            ->whereNotNull('posted_foreign_id')
            ->where('posted_foreign_id', '!=', '')
            ->where('platform_is_story', 0)
            ->where('status', 'posted')
            ->where('is_valid', 1)
            ->where('platform', '!=', 'facebook') // FIXME - Disable Facebook for now while we fix permissions
            ->where('posted_date', '>=', now()->subDays(30))   // tracking window
            ->whereHas('account', function ($q) use ($tiktokSince, $linkedinSince) {
                $q->where('is_active', 1)
                    ->where('is_valid', 1)
                    ->where(function ($q) use ($tiktokSince, $linkedinSince) {
                        $q->whereNotIn('platform', ['tiktok', 'linkedin']) // other platforms unaffected
                            ->orWhere(function ($q) use ($tiktokSince) {
                                $q->where('platform', 'tiktok')
                                    ->whereHas('access_token', fn ($t) => $t->where('created_at', '>=', $tiktokSince));
                            })
                            ->orWhere(function ($q) use ($linkedinSince) {
                                $q->where('platform', 'linkedin')
                                    ->whereHas('access_token', fn ($t) => $t->where('created_at', '>=', $linkedinSince));
                            });
                    });
            })
            ->where(function ($q) {                              // cadence: never scraped or >1 day old
                $q->whereNull('metrics_last_scraped_at')
                    ->orWhere('metrics_last_scraped_at', '<', now()->subDay());
            })
            ->orderByRaw('metrics_last_scraped_at IS NOT NULL, metrics_last_scraped_at ASC') // nulls first, then oldest
            ->chunkById(100, function ($posts) use (&$count) {
                foreach ($posts as $post) {
                    QueryPostMetrics::dispatch($post);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} metric jobs.");

        return self::SUCCESS;
    }
}
