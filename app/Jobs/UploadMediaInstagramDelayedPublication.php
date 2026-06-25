<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionPosts;

use App\Enums\SocialNetworks;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

use App\Jobs\UpdateNotionPostInDatabaseAfterUpload;

use VStelmakh\UrlHighlight\UrlHighlight;

use Carbon\Carbon;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Throwable;


/**
 * 
 * NOTE -
 * 
 * 
 * 
 * NOTE - This isn't the only job that runs checks against the post to validate that everything is correct, the FindNotionPostsInDB also runs those checks, so any new check in one should be made in the other
 * 
 * Example - If I enable posting videos to linkedin I'll need to enable it in both scripts, if not then posts won't get picked up...
 * 
 * 
 * 
 * NOTE - 
 */

class UploadMediaInstagramDelayedPublication implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 40;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionPosts $post,
        public $social_account,
        public $container_id
    )
    {


        

    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;
 
    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->post->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // Mark the post as processing
        $this->post->status = 'slow_processing';
        $this->post->in_flight = 0;
        $this->post->save();

        // Refresh our models
        $this->social_account->refresh();

        // Log
        Log::info("UploadMediaInstagramDelayedPublication - Attempt #" . $this->attempts() . " for post with ID " . $this->post->id);

        // Perform the post
        $response = Http::facebook()->post($this->social_account->account_id . '/media_publish', [
            'access_token' => $this->social_account->access_token->access_token,
            'creation_id' => $this->container_id
        ]);

        // Make pretty
        $rep = $response->json();

        // Check the response
        if (!$response->successful()) {
            if (isset($rep['error']['error_subcode'])) {
                if ($rep['error']['error_subcode'] == 2207027) {
                    $this->release(1800); // Try again in 30 minutes
                    return;
                }
            } else {

                Log::info("UploadMediaInstagramDelayedPublication - Unhandled error");
                Log::info($response);
                Log::info($rep);
                $this->release(60);
                return;

            }
        } else {

            $foreign_id = $rep['id'];
            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                true, // Success level
                "", // Message we want to share?
                $this->post, // The post object,
                $foreign_id
            );
            return;

        }
        
    }
    


    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {

        $this->post->status = 'error';
        $this->post->save();
        
    }
}