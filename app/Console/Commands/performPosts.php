<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNotionPost;
use App\Models\NotionPosts;
use Carbon\Carbon;
use Illuminate\Console\Command;

class performPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:perform-posts';

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
        // Preprod safety guard — don't even dispatch publish jobs when disabled.
        if (! config('posting.enabled')) {
            $this->warn('Posting disabled (POSTING_ENABLED=false) — no posts dispatched.');

            return self::SUCCESS;
        }

        // Get all scheduled posts
        $scheduled_posts = NotionPosts::with('account')->with('user')
            ->where('scheduled_date', '<=', Carbon::now())
            ->where('status', 'scheduled')
            ->where('in_flight', 0)
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->get();

        // Check if we have any posts to work on
        if (! $scheduled_posts->count()) {
            return 'No posts to handle';
        }

        foreach ($scheduled_posts as $post) {
            ProcessNotionPost::dispatch($post);
        }

    }
}
