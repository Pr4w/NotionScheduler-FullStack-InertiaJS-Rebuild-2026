<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\NotionPosts;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class resetInFlightStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-in-flight-status';

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

        // Get all the posts that have in-flight issues
        echo "<h2>Getting posts with in-flight issues</h2>";
        $interval_mins = 20;

        // Get
        $get = NotionPosts::where('status', 'error')
            ->where('in_flight', 1)
            ->where('in_flight_start', '<', Carbon::now()->subMinutes($interval_mins))
            ->get();

        // Check to see if we have any
        if ($get->count() < 1) {
            return "We have no in_flight scheduled posts older than $interval_mins minutes";
        }

        // Perform task
        echo "We have " . $get->count() . " posts that been inflight more than $interval_mins minutes. Resetting their status now";
        $do = NotionPosts::whereIn('id', $get->pluck('id')->all())
            ->update(['in_flight' => 0]);

        return;

    }
}
