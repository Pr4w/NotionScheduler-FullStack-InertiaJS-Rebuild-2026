<?php

namespace App\Console\Commands;

use App\Jobs\QueryAccountMetrics;
use App\Models\NotionSocialAccounts;
use Illuminate\Console\Command;

class ScrapeAccountMetrics extends Command
{
    protected $signature = 'metrics:scrape-accounts';

    protected $description = 'Dispatch jobs to refresh account follower metrics (once/day per account)';

    public function handle(): int
    {
        $count = 0;

        NotionSocialAccounts::query()
            ->where('is_active', 1)
            ->where('is_valid', 1)
            // Only platforms the package can scrape (Twitter/X has no driver).
            ->whereIn('platform', ['facebook', 'instagram', 'threads', 'linkedin', 'tiktok', 'youtube'])
            // Cadence: never scanned, or last scanned more than a day ago.
            ->where(function ($q) {
                $q->whereNull('metrics_last_scraped_at')
                    ->orWhere('metrics_last_scraped_at', '<', now()->subDay());
            })
            ->orderByRaw('metrics_last_scraped_at IS NOT NULL, metrics_last_scraped_at ASC') // nulls first, then oldest
            ->chunkById(100, function ($accounts) use (&$count) {
                foreach ($accounts as $account) {
                    QueryAccountMetrics::dispatch($account);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} account-metric jobs.");

        return self::SUCCESS;
    }
}
