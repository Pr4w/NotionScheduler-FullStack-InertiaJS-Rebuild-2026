<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\NotionSocialAccounts;
use Carbon\Carbon;

use Illuminate\Support\Facades\Log;

class checkSocialTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-social-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all Social Media Tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // INIT
        $interval_hours = 6;

        // Get all the DBs we haven't scanned in a while
        \DB::statement("SET SQL_MODE=''"); // NOTE - Temporarily disables safe mode
        $to_scan = NotionSocialAccounts::with('access_token')
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->where('last_token_check_scan', '<', Carbon::now()->subHours($interval_hours))
            // ->orderByDesc('last_token_check_scan')
            ->orderBy('last_token_check_scan', 'asc')
            ->groupBy("token_id")
            ->get();
            

        if ($to_scan->count() < 1) {
            return "There are not Social Accounts that need checking";
        }

        foreach ($to_scan as $social_account) {
            \App\Jobs\CheckSocialTokens::dispatch($social_account);
        }

        

        return;
    }
}
