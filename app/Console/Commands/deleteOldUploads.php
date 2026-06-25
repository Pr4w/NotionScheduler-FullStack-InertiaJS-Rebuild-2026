<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Log;

class deleteOldUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-old-uploads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unuused old files';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $folder = '/public/uploadable_media';
        $files = Storage::files($folder);

        if (!count($files)) {
            return "Nothing to do here";
        }

        // Make
        $now = Carbon::now();

        // Loop
        foreach ($files as $file) {

            $root = Str::of($file);
            $filename = $root->remove('public/uploadable_media/')->explode('-')->last();
            $time_posted = Str::of($filename)->explode('.')->first();
        
            // Check time difference
            if (Carbon::createFromTimestamp($time_posted)->diffInHours(Carbon::now()) > 24) {
                Storage::delete($file);
                echo "Deleting $filename...";
            }

        }

        return;
    }
}