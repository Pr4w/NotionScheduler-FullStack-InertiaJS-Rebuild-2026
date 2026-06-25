<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;
use App\Models\NotionDatabases;
use Carbon\Carbon;

class queryDBAndFindReadyPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:query-d-b-and-find-ready-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // INIT
        $interval_minutes = 20;

        // Get all the DBs we haven't scanned in a while
        $to_scan = NotionDatabases::with('token')
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->where('last_check_for_new_posts', '<', Carbon::now()->subMinutes($interval_minutes)) // FIXME - Comment this if you want to constantly check
            ->orderBy('last_check_for_new_posts', 'asc')
            ->get();

        // Log::info("QueryDnAbdFindReadyPosts IDs");
        // Log::info($to_scan->pluck('id'));

        if ($to_scan->count() < 1) {
            return "There are not Notion Databases that need their posts checked";
        }

        foreach ($to_scan as $db) {

            echo "Dispatching JOB - Databse with ID " . $db->id;
            \App\Jobs\FindNotionPostsInDB::dispatch($db);

        }

        return;
    }
}
