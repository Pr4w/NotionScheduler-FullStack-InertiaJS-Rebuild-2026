<?php

namespace App\Console\Commands;

use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionPosts;
use App\Models\NotionSocialAccounts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateDormantNotionRecords extends Command
{
    protected $signature = 'notion:deactivate-dormant
                            {--months=12 : Months of inactivity before deactivation}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Deactivate Notion social accounts, databases, and access tokens for users who have not scheduled a post within the given window';

    /**
     * Models to clean up. Each must have a userid column,
     * an is_active column, and an is_valid column.
     */
    protected array $targets = [
        'social accounts' => NotionSocialAccounts::class,
        'databases'       => NotionDatabases::class,
        'access tokens'   => NotionAccessTokens::class,
    ];

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths($months);

        $this->info("Cutoff: {$cutoff->toDateTimeString()} ({$months} months ago)");

        $activeUserIds = NotionPosts::where('created_at', '>=', $cutoff)
            ->distinct()
            ->pluck('userid');

        $this->info("Users with recent posts: {$activeUserIds->count()}");
        $this->newLine();

        $totals = [];

        foreach ($this->targets as $label => $modelClass) {
            $query = $modelClass::query()
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->whereNotIn('userid', $activeUserIds);

            $count = $query->count();
            $userCount = (clone $query)->distinct('userid')->count('userid');

            $this->line("<comment>{$label}</comment>: {$count} rows across {$userCount} users");

            if ($count === 0 || $dryRun) {
                $totals[$label] = ['rows' => $count, 'users' => $userCount, 'updated' => 0];
                continue;
            }

            $updated = $query->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

            $this->info("  → deactivated {$updated}");
            $totals[$label] = ['rows' => $count, 'users' => $userCount, 'updated' => $updated];
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn('Dry run — no changes made.');
        } else {
            $this->info('Done.');
            Log::info('Dormant Notion records deactivated', [
                'months_threshold' => $months,
                'cutoff' => $cutoff->toDateTimeString(),
                'totals' => $totals,
            ]);
        }

        return self::SUCCESS;
    }
}