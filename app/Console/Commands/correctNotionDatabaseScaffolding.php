<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\NotionDatabases;
use Carbon\Carbon;

use Illuminate\Support\Facades\Log;

class correctNotionDatabaseScaffolding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:correct-notion-database-scaffolding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a scan to correct all NotionScheduler databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // INIT
        $interval_hours = 2;

        // Get all the DBs we haven't scanned in a while
        $to_scan = NotionDatabases::with('token')
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->where('last_check_scaffolding_scan', '<', Carbon::now()->subHours($interval_hours))
            // ->orderByDesc('last_check_scaffolding_scan')
            ->orderBy('last_check_scaffolding_scan', 'asc')
            ->limit(10)
            ->get();

        if ($to_scan->count() < 1) {
            return "There are not Notion Databases that need their scaffoldings checked";
        }

        foreach ($to_scan as $db) {

            echo "Dispatching JOB - Databse with ID " . $db->id;
            \App\Jobs\CorrectNotionDatabaseScaffolding::dispatch($db);

        }

        return;
    }
}
