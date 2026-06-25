<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;
use App\Models\User;

use Carbon\Carbon;

use Illuminate\Support\Facades\Mail;

class trialExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:trial-expired';

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
        // Log::info("Running trial expired command");

        // Get all users where the time since their account creation date is equal to 7
        $trial_time = 7;
        $users = User::where('is_active', 1)
            ->whereDate('created_at', Carbon::today()->subDays($trial_time))
            ->get();

        // Check
        if ($users->count() < 1) {
            return "No emails to send";
        }

        // There are people to send stuff to, so lets do it
        foreach ($users as $user) {

            // Log
            Log::info("Trial expired for user " . $user->id . " - Emailing him now");

            // Email the user 
            Mail::to($user->email)
            // Mail::to('eternal_ps@live.com')
                ->send(new \App\Mail\TrialExpired(
                    $user
                )
            );  

        }


    }
}
