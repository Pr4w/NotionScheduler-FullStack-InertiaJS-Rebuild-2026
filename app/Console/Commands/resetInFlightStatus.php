<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\NotionPosts;
use App\Jobs\UpdateNotionPostInDatabaseAfterUpload;
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
        // (1) Posts already flagged as 'error' but still stuck in_flight after a
        //     short while — just clear the flag (original behaviour).
        $stale_errors = NotionPosts::where('status', 'error')
            ->where('in_flight', 1)
            ->where('in_flight_start', '<', Carbon::now()->subMinutes(20))
            ->get();

        if ($stale_errors->count() > 0) {
            NotionPosts::whereIn('id', $stale_errors->pluck('id')->all())
                ->update(['in_flight' => 0]);
        }

        // (2) Posts stuck mid-upload (e.g. TikTok stalling indefinitely). An
        //     in-progress status that's been in_flight for over an hour is dead,
        //     so fail it — locally AND back in Notion with a message — which
        //     unsticks it and lets the user reschedule it.
        $timeout_mins = 180;
        $stuck = NotionPosts::where('in_flight', 1)
            ->whereIn('status', ['processing', 'slow_processing', 'processing_part2'])
            ->where('in_flight_start', '<', Carbon::now()->subMinutes($timeout_mins))
            ->get();

        foreach ($stuck as $post) {
            $message = "This post's upload stalled and was automatically cancelled after {$timeout_mins} minutes. Please reschedule it to try again.";

            // Fix local state immediately so it's unstuck + visible as failed
            // even if the queue is backed up.
            $post->status = 'error';
            $post->in_flight = 0;
            $post->in_flight_start = null;
            $post->save();

            // Write the Error status + message back to the Notion row
            // (is_success = false → the job marks it as an error there).
            UpdateNotionPostInDatabaseAfterUpload::dispatch(false, $message, $post);

            Log::info("resetInFlightStatus - timed out stuck post #{$post->id} ({$post->platform}) after {$timeout_mins} min");
        }

        // Log::info('resetInFlightStatus - done', [
        //     'stale_errors_cleared' => $stale_errors->count(),
        //     'stuck_timed_out' => $stuck->count(),
        // ]);
    }
}
