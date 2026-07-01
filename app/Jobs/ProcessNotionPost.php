<?php

namespace App\Jobs;

use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionErrorManager;
use App\Models\NotionPosts;
use App\Models\NotionSocialAccounts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Notion\Notion;

class ProcessNotionPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionPosts $post
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Preprod safety guard: never publish to real platforms / write back to
        // Notion when the posting engine is disabled (see config/posting.php).
        if (! config('posting.enabled')) {
            Log::warning('Posting disabled (POSTING_ENABLED=false) — skipping publish', [
                'post_id' => $this->post->id,
                'platform' => $this->post->platform,
            ]);

            return;
        }

        try {

            Log::withContext([
                'origin' => 'ProcessNotionPost Job',
                'post_id' => $this->post->id,
                'post_platform' => $this->post->platform,
                'post_name' => $this->post->post_name,
            ]);

            // Atomically claim this post so a duplicate dispatch can't run in parallel.
            // performPosts selects on (status='scheduled', in_flight=0); in_flight used
            // to be set only at the END of this job, so while we did the slow Notion
            // fetches below, the next scheduler tick would re-select the same post and
            // dispatch a second copy — which is how one post ended up uploaded multiple
            // times. The conditional UPDATE is a single atomic statement, so exactly one
            // of the competing jobs flips in_flight and the losers bail here.
            //
            // Only claim on the first attempt: a released/retried job (see the catch's
            // release(120)) already owns the claim and must be allowed to proceed.
            if ($this->attempts() === 1) {
                $claimed = NotionPosts::where('id', $this->post->id)
                    ->where('in_flight', 0)
                    ->update(['in_flight' => 1, 'in_flight_start' => Carbon::now()]);

                if (! $claimed) {
                    Log::info('Post '.$this->post->id.' is already in flight — skipping duplicate dispatch.');

                    return;
                }

                // Keep the in-memory model in sync with the claim we just persisted.
                $this->post->in_flight = 1;
                $this->post->in_flight_start = Carbon::now();
            }

            // Check if the user posting is active or valid
            if (! $this->post->user->is_active) {
                Log::info('User '.$this->post->user->id.' is no longer active, lets not perform his post');
                $this->post->status = 'error';
                $this->post->in_flight = 0;
                $this->post->save();

                return;
            }

            // Get the corresponding DB
            $notion_database = NotionDatabases::where('id', $this->post->database_id)->first();

            // Check
            if (! $notion_database or is_null($notion_database)) {
                $this->fail('ERR39 - No Notion Database found');
                Log::debug(76);
                Log::debug($notion_database);
                Log::debug($this->post);
                Log::debug($this->post->user->id);
                $this->post->status = 'error';
                $this->post->in_flight = 0;
                $this->post->save();
                throw new \Exception('Could not find database with ID');
            }

            // Get the corresponding token
            $notion_token = NotionAccessTokens::where('id', $notion_database->token_id)->first();
            if (! $notion_token) {
                $this->fail('ERR46 - No Notion Database found');
            }

            // Create Notion object
            $notion = Notion::create($notion_token->token);

            // Get the page content
            $contents = $notion->blocks()->findChildrenRecursive($this->post->post_page_id);
            $content_final = NotionPosts::getAllContentFromChildren($contents);

            // Load the scaffolding
            $scaffolding = NotionDatabases::getDefaultScaffolding();

            // Load the page so we can get the media and whatnot
            $page = $notion->pages()->find($this->post->post_page_id);

            // Clean up
            // $props = $page->toArray();
            // $props = $props['properties'];

            // Get the media arrays
            // $media_base = $props[
            //     $scaffolding['properties']['files']['name']
            // ];
            // $thumbnail_base = $props[
            //     $scaffolding['properties']['files_thumbnail']['name']
            // ];

            // $media = NotionPosts::getAllMediaFromProps($media_base);
            // $thumbnail = NotionPosts::getThumbnailFromProps($thumbnail_base);

            $media_base = $page->properties()->getById($notion_database->column_media)->files;
            $media = NotionPosts::getAllMediaFromProps2($media_base);

            $thumbnail_base = $page->properties()->getById($notion_database->column_media_thumbnail)->files;
            $thumbnail = NotionPosts::getThumbnailFromProps2($thumbnail_base);

            $date = $page->properties()->getById($notion_database->column_post_date)->start();

            // Get the date
            // $date = $props[
            //     $scaffolding['properties']['schedule_post_date']['name']
            // ]['date']['start'];
            $date = Carbon::parse($date)->setTimezone(
                date_default_timezone_get()
            );

            // Log::info("Post name is " . $this->post->post_name);
            // Log::info("ProcessNotionPost - The processed date is $date");
            // Log::info("ProcessNotionPost - The saved date is " . $this->post->scheduled_date);
            // Log::info("Right now it's " . Carbon::now());
            // NOTE - Check if the user hasn't changed the scheduled date, if that's the case then update it in the DB and end the job prematurely so we can change it later
            if (Carbon::parse($this->post->scheduled_date)->equalTo($date)) {
                // Log::info("D1 matched");
            } else {
                Log::info("Post with name '".$this->post->post_name."' with ID ".$this->post->id.' was originally scheduled for '.$this->post->scheduled_date." but we found that the date was updated in their Notion Workspace, updating it to $date");

                // Check when we should post
                if ($date->isBefore(Carbon::now())) {
                    Log::info('Date is before NOW, so lets post immediately');
                } else {
                    Log::info('Date is AFTER NOW, so just update and return');
                    $this->post->scheduled_date = $date;
                    // Release the claim: the post stays 'scheduled' for a future run, so
                    // it must be re-selectable by performPosts once its time comes.
                    $this->post->in_flight = 0;
                    $this->post->in_flight_start = null;
                    $this->post->save();

                    return;
                }

            }

            /**
             * CASE - Guard
             * Check that we're submitting a post to an account that exists in the DB, if not that would mean that the user is scheduling a post, switching account, moving around, etc., we don't want that
             *
             * Will also be useful in protecting against people scheduling too many posts at the start and then
             */
            try {
                $slugs = NotionSocialAccounts::getAllSlugsFromUser($notion_database->id, $notion_database->userid);
                $prop_social = $page->properties()->getById($notion_database->column_social_account);
                // Log::debug("ProcessNotionPost 166");
                // Log::debug(json_encode($prop_social, JSON_PRETTY_PRINT));

                $slug = $prop_social->option->name;

                // Check if we actually have a prop
                if ($prop_social->isEmpty()) {
                    // TODO
                    // Here we should disengage the post and throw an error
                    // \App\Jobs\UpdateNotionPostInDatabaseAfterUpload::dispatch(
                    //     false,
                    //     "You haven't specified an account you want to post to.",
                    //     $this->post
                    // );
                    // return;
                    Log::warning('UNHANDLED');
                    Log::warning(164);
                } else {
                    if (! in_array($slug, array_keys($slugs))) {
                        // Here we should disengage the post and throw an error
                        // \App\Jobs\UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        //     false,
                        //     "The social media platform in your database doesn't match the one that this post was originally scheduled to.",
                        //     $this->post
                        // );
                        // return;
                        Log::warning('UNHANDLED');
                        Log::warning(167);
                        Log::info($slug);
                        Log::info($slugs);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning(159);
                Log::info($e);
            }

            // Get the social details for this post
            $social_account = NotionSocialAccounts::with('access_token')->where('id', $this->post->account_id)->first();

            // TODO - Check??
            if (! $social_account) {
                $this->fail('ERR122 - No corresponding social account found for post -> account_id');

                // Release the claim so a later run can retry once the account exists.
                $this->post->in_flight = 0;
                $this->post->in_flight_start = null;
                $this->post->save();

                return;
            }

            // Looks like we're all good to go. in_flight was already claimed atomically
            // at the top of this method, so there's nothing more to flag here — the post
            // stays in flight until UploadMedia finishes (or the reaper releases it).
            UploadMedia::dispatch(
                $this->post,
                $social_account,
                $content_final,
                $media,
                $thumbnail
            );

        } catch (\Exception $e) {

            // Make pretty
            $msg = $e->getMessage();
            // Log::debug(222);
            // Log::debug($e);
            // Log::debug($msg);

            $notionerror = NotionErrorManager::manageError(
                $this->post->userid,
                $e,
                $notion_token ?? null,
                'FindNotionPostsInDB',
                $this->post->database_id,
                $this->post->id
            );

            if (! isset($notionerror['action'])) {
                Log::info('NotionErrorManager has en empty action');
                Log::info($msg);
                Log::info($notionerror);
            } else {

                if ($notionerror['action'] == 'correct_scaffolding') {
                    CorrectNotionDatabaseScaffolding::dispatch($notion_database);
                }

                if ($notionerror['action'] != 'none') {
                    // Re-program the job
                    $this->release(120);
                }

            }

        }
    }

    /**
     * Release the in_flight claim if the job fails outright (exhausted retries or an
     * uncaught throwable). Without this, a post we claimed at the top of handle() would
     * stay in_flight = 1 forever — the reaper only rescues 'processing*' statuses, not a
     * claimed-but-still-'scheduled' post — so it would never be picked up again.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::info('ProcessNotionPost failed for post '.$this->post->id.' — releasing in_flight claim.');

        NotionPosts::where('id', $this->post->id)
            ->update(['in_flight' => 0, 'in_flight_start' => null]);
    }
}
