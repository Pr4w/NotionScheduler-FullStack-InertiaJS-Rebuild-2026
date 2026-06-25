<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\QueueBacklogNotification;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Process;

class checkSupervisor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-supervisor';

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

        Log::withContext([
            'origin' => 'Check Supervisor Job',
        ]);

        $jobCount = DB::table('jobs')->count();
        $threshold = 1000;


        if ($jobCount > $threshold) {
            Log::error("Too many jobs in queue!!!");
            Mail::raw("Alert: There are currently $jobCount jobs in the queue, exceeding the threshold of $threshold.", function ($message) {
                $message->to('mark@markhadjhamou.com')
                        ->subject('Queue Alert: High Job Count');
            });

            
            $command = 'sudo supervisorctl restart notionscheduler-worker:\*';
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info('Supervisor restart OK', ['output' => $result->output()]);
            } else {
                Log::error('Supervisor restart FAILED', [
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);
            }


        }

        
    }
}