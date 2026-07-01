<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionDatabases;
use App\Models\NotionSocialAccounts;
use App\Models\NotionPosts;
use App\Models\NotionErrorManager;

use App\Models\NotionHttp;

use App\Jobs\CorrectNotionDatabaseScaffolding;

use Notion\Notion;
use Notion\Databases\Database;
use Notion\Databases\Query;
use Notion\Databases\Query\Sort;
use Notion\Databases\Query\CompoundFilter;
use Notion\Databases\Query\CheckboxFilter;
use Notion\Databases\Query\SelectFilter;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Carbon\Carbon;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Throwable;


class FindNotionPostsInDB implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionDatabases $database
    )
    {
        //
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
        return $this->database->id;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // INIT
        $token = $this->database->token->token;
        $userid = $this->database->userid;
        $saveDb = false;

        Log::withContext([
            'origin' => 'FindNotionPostsInDB Job',
            'database_id' => $this->database->id,
            'columns' => $this->database
        ]);

        if (!$this->database->is_valid or !$this->database->is_active) {
            Log::info("Database isn't valid or active, stopping JOB early");
            return;
        }

        // try {
        //     if ($this->database->userid == 1) {
        //         // Log::info("Starting a FindNotionPostInDb Job for user 1");
        //     }
        // } catch (\Throwable $e) {
        //     Log::info($e);
        // }

        // Log::info("Running FindNotionPostsInDB");

        try {
            // Looks like we're all set to go
            $notion = Notion::create($token);
            $database = $notion->databases()->find($this->database->database_id);

            // Check if we have NULL properties
            // if (
            //     is_null($this->database->column_ns_comments) or 
            //     is_null($this->database->column_is_ready) or 
            //     is_null($this->database->column_ns_status)
            // ) {
            //     Log::warning()
            // }

            // Get some column names
            $column_comments = $database->properties()->getById($this->database->column_ns_comments)->metadata()->name;
            $column_checkbox = $database->properties()->getById($this->database->column_is_ready)->metadata()->name;
            $column_status = $database->properties()->getById($this->database->column_ns_status)->metadata()->name;

            // Get scaffolding
            $scaffolding = NotionDatabases::getDefaultScaffolding();
    
            // CASE - Query to get all posts that are TICKED but don't have a status attributed to them
            // $query = Query::create()
            //     ->changeFilter(
            //         CompoundFilter::and(
            //             CheckboxFilter::property(
            //                 $scaffolding['properties']['is_ready']['name']
            //             ),
            //             SelectFilter::property(
            //                 $scaffolding['properties']['notion_status']['name']
            //             )->isEmpty()
            //         )
            //     )
            //     // ->addSort(Sort::property("Name")->ascending())
            //     ->changePageSize(20);
    
            // $result = $notion->databases()->query($database, $query);

    
            // CASE - Get all ticked posts that aren't Posted or Scheduled
            $query = Query::create()
                ->changeFilter(
                    CompoundFilter::and(
                        CheckboxFilter::property(
                            // $scaffolding['properties']['is_ready']['name']
                            $column_checkbox
                        ),
                        /* SelectFilter::property(
                            $scaffolding['properties']['notion_status']['name']
                        )->doesNotEqual(
                            $scaffolding['properties']['notion_status']['sub_options']['scheduled']['name']
                        ), */
                        SelectFilter::property(
                            // $scaffolding['properties']['notion_status']['name']
                            $column_status
                        )->doesNotEqual(
                            $scaffolding['properties']['notion_status']['sub_options']['posted']['name']
                        )
                    )
                )
                ->addSort(Sort::property(
                    // $scaffolding['properties']['schedule_post_date']['name']
                    $database->properties()->getById($this->database->column_ns_status)->metadata()->name
                )->ascending()) // NOTE - This is the CORRECT way to sort so that we get the newest ones first
                ->changePageSize(20);
    
            $result = $notion->databases()->query($database, $query);
    
            // Check if we actually have results
            if ($result->pages) {
                
                // Get all slugs
                $slugs =  NotionSocialAccounts::getAllSlugsFromUser($this->database->id, $userid);
    
                // Loop through all the pages
                foreach ($result->pages as $page) {
    
                    // Set errrors
                    $errors = [];

                    // Get the title
                    $title = $page->title()->toString();

                    // Security check to see if checkbox is checked or not
                    try {

                        $arr_check = $page->toArray();

                        if (isset($arr_check['properties'][$column_checkbox])) {

                            $checked = $arr_check['properties'][$column_checkbox];

                            if ($checked) {
                                // Do nothing, keep going in the loop
                            } else {
                                Log::error("NOT CHECKED - We should skip - UNHANDLED");
                                // Skip this item in the loop
                                continue;
                            }

                        } else {

                            Log::error("Column CHECKBOX isn't checked");
                            Log::error($arr_check['properties']);
                            Log::info(188);
                            Log::info($arr_check);
                            Log::info($column_checkbox);

                        }

                        

                        // TODO - What we want do to here is checked that the page is marked as ticked, as a failover, in case something acts weird


                    } catch (\Exception $e) {

                        Log::error("FindNotionPostsinDB 191");

                    }
    
                    // Convert to array
                    // $arr = $page->toArray();
    
                    // Check if we have a checkbox before doing anything else
                    // The checkbox is ticked since that was part of the initial query
                    // if (isset($arr['properties'][$scaffolding['properties']['is_ready']['name']])) {
    
                        // Are we posting to an actual platform?
                        $prop_social = $page->properties()->getById($this->database->column_social_account);
                        if ($prop_social->isEmpty()) {
                        // if (!isset($arr['properties'][$scaffolding['properties']['social_accounts']['name']]['select'])) {
                            $errors[] = "You haven't specified an account you want to post to.";
                        } else {
    
                            // dump("We're in the select");
    
                            // Get the slug so we know where we are
                            // $slug = $arr['properties'][$scaffolding['properties']['social_accounts']['name']]['select']['name'];
                            $slug = $prop_social->option->name;

                            // NOTE - Just above we're getting the raw slug entry from the user, but what we could alternatively do is get the id, and then query the DB for the option_select_id field
                            // $slug_id = $arr['properties'][$scaffolding['properties']['social_accounts']['name']]['select']['id'];
                            $slug_id = $prop_social->option->id;
                            $slug_id_account = NotionSocialAccounts::where('option_select_id', $slug_id)->first();
                            if ($slug_id_account) {
                                // Log::info("FindNotionPostsInDB Job - Found the corresponding slug in the DB, OK");
                                // Log::info($slug_id_account->platform);
                                // $platform = $slug_id_account->platform;
                            } else {
                                Log::warning("FindNotionPostsInDB Job - DID NOT find the corresponding slug in the DB - Slug_id is $slug_id - Account is $slug");
                            }
    
                            // Check if the slug is in the array
                            if (!in_array($slug, array_keys($slugs))) {
                                $errors[] = "Invalid social media platform - Code 422";
                            } else {
    
                                // Get platform from slug
                                $platform = $slugs[$slug];
                                $platform = $platform['platform'];

                                // CASE - Check data
                                $column_date = $page->properties()->getById($this->database->column_post_date);
                                if ($column_date->isEmpty()) {
                                    $errors[] = "Scheduled post date isn't set.";
                                } else {

                                    // Check the scheduled post date for this post
                                    $check = Carbon::parse(
                                        $column_date->start()
                                    );

                                    // Log::info('Date scheduled for this post: ' . $check->isoFormat('YYYY-MM-DD HH:mm'));

                                    // Check if the date is in the future
                                    if (!$check->isFuture()) {
                                        try {
                                            // A manual re-tick of a post that's stuck mid-flight (or
                                            // errored) is an explicit "retry this", so don't let a stale
                                            // scheduled date block the recovery: the re-processing below
                                            // resets it to 'scheduled' + in_flight=0 so perform-posts
                                            // picks it up again.
                                            $existing = NotionPosts::where('post_page_id', $page->id)->first();
                                            $is_stuck_retry = $existing
                                                && !$existing->posted_date
                                                && ($existing->in_flight || in_array($existing->status, ['processing', 'slow_processing', 'processing_part2', 'error']));

                                            if ($check->lt(now()->subWeek()) && !$is_stuck_retry) {
                                                // Post is scheduled for over a week ago, so lets spit an error
                                                $errors[] = "Your post was scheduled to be posted in the past. Please use a date set in the future.";
                                            } else {
                                                // Post is scheduled for recently (or is a stuck retry), so lets just post it anyway
                                                // Log::info("Date is NOT more than a week ago, so lets continue");
                                            }
                                        } catch (\Throwable $e) {
                                            Log::info(234);
                                            Log::info($e);
                                        }
                                    }
                                }


                                if (count($errors) < 1) {

                                    // Lets go through each field to see if it's empty or not
                                    // foreach ($scaffolding['properties'] as $key => $property) {
        
                                    //     // CASE - Check date
                                    //     if ($key == "schedule_post_date") {
                                    //         // Is it set?
                                    //         if (!$arr['properties'][$property['name']]['date']) {
                                    //             $errors[] = "Scheduled post date isn't set.";
                                    //         } else {
                                    //             // Is it in the future?
                                    //             // TODO - Does this really apply? Do we need to check this if the post is already scheduled?
                                    //             // FIXME - Remove this in the case of us posting
                                    //             $check = $arr['properties'][$property['name']]['date']['start'];
                                    //             $check = Carbon::parse($check);
                                    //             if (!$check->isFuture()) {
                                    //                 $errors[] = "Your post was scheduled to be posted in the past. Please use a date set in the future.";
                                    //             }
        
                                    //         }
                                    //     }
        
                                    //     // CASE - Check has files
                                    //     if ($key == 'files') {
                                    //         // This only applies if we're posting to Instagram
                                    //         if ($platform == 'instagram') {
                                    //             if (!$arr['properties'][$property['name']]['files']) {
                                    //                 $errors[] = "There was no media to upload.";
                                    //             }
                                    //         }
                                    //     }
        
                                    // }
                                    $media_base = $page->properties()->getById($this->database->column_media)->files;
                                    $media = NotionPosts::getAllMediaFromProps2($media_base);

                                    $thumbnail_base = $page->properties()->getById($this->database->column_media_thumbnail)->files;
                                    $thumbnail = NotionPosts::getThumbnailFromProps2($thumbnail_base);

                                    // Check the media?
                                    // $media_base = $arr['properties'][
                                    //     $scaffolding['properties']['files']['name']
                                    // ];
                                    // $media = NotionPosts::getAllMediaFromProps($media_base);

                                    // Check the thumbnail?
                                    // $thumbnail_base = $arr['properties'][
                                    //     $scaffolding['properties']['files_thumbnail']['name']
                                    // ];
                                    // $thumbnail = NotionPosts::getThumbnailFromProps($thumbnail_base);

                                    // Check the content
                                    $content = $notion->blocks()->findChildrenRecursive($page->id);
                                    $content = NotionPosts::getAllContentFromChildren($content);

                                    // Is this a story?
                                    // $is_story = $arr['properties'][$scaffolding['properties']['is_story']['name']]['checkbox'];
                                    $is_story = $page->properties()->getById($this->database->column_post_as_story)->checked;

                                    // Get the account info
                                    $account_info = $slugs[$slug];

                                    /**
                                     * SECTION
                                     * SECTION
                                     * SECTION
                                     * FIXME
                                     * FIXME
                                     * FIXME
                                     * 
                                     * This is where we modify the upload criteria
                                     */
                                    // Check the platform 
                                    $post = new \stdClass;
                                    $post->id = 1000;
                                    $post->userid = $userid;
                                    $post->platform = $platform;
                                    $post->platform_is_story = $is_story;

                                    $checks = NotionPosts::checkPostIsValid($post, $content, $media, $thumbnail);
                                    if ($checks['success']) {
                                        $errors = false;
                                    } else {
                                        $errors = $checks['errors'];
                                    }

                                    // Get the date at which the user wants to schedule the post
                                    $date = $column_date->start();
                                    $date = Carbon::parse($date)->setTimezone(
                                        date_default_timezone_get()
                                    );


                                    // Check if the post already exists in the DB
                                    $check_post = NotionPosts::where('post_page_id', $page->id)->first();

                                    if ($check_post) {

                                        /*
                                        Log::error(340);
                                        Log::error("FindNotionPostsInDB is re-scheduling a post that was already added for some reason...");
                                        Log::error("Breaking now...");
                                        Log::error("Page ID is " . $page->id);
                                        Log::error($check_post);
                                        Log::error("Post ID is " . $check_post->id);
                                        Log::info("345 TODO - Make some changes depending on the criteria of this thing");
                                        Log::error([
                                            'post_name' => $title,
                                            'userid' => $userid,
                                            'database_id' => $this->database->id,
                                            'account_id' => $account_info['id'],
                                            'platform' => $account_info['platform'],
                                            'platform_is_story' => $is_story,
                                            'status' => 'scheduled',
                                            'scheduled_date' => $date,
                                            'in_flight' => false,
                                            'is_active' => 1,
                                            'is_valid' => 1,
                                        ]);
                                        */

                                        // Check if this has already been posted
                                        if ($check_post->posted_date) {

                                            // Log
                                            // Log::info("Post with ID $check_post->id was already submitted to DB on " . $check_post->posted_date . " and has a scheduled post date of " . $date->format('Y-m-d H:i:s'));
                                            // Log::info($check_post);

                                            // Post has already been submitted, let's check the scheduled date
                                            if ($date->isFuture()) {

                                                // Post has already been submitted, but the new scheduled date is in the future, so let's reschedule it, no?
                                                // Do nothing
                                                // Log::info("Date is in the future, so let's reschedule it");

                                            } else {

                                                // Post is already submitted, but has a scheduled date in the past
                                                // Log::info("Date is in the past, so lets output an error message for it");
                                                $errors[] = "You're trying to re-schedule a post with a date in the past, please either create a new post or set a date in the future";
                                                

                                            }
                                            
                                        } else {

                                            // Log::info("Post ISN'T posted yet, what do? This usually occurs if something re-schedules a post that was already scheduled, but is an issue if it is on repeat / keeps looping. It is in the DB with an ID of $check_post->id");
                                            // Log::info($check_post);

                                        }

                                        // Log the object itself
                                        // try {

                                        //     Log::info("Page props...");
                                        //     Log::info($page->properties()->getAll());
                                        //     Log::info("Carbon parse for scheduled date is... " . $date->diffForHumans());

                                        // } catch (\Throwable $e) {
                                        //     Log::info("364 - Failed to log object");
                                        //     Log::info($e);
                                        // }

                                    }

        
                                    // All is clear, lets get the content of the page
                                    if (!$errors) {
        
                                        // Get the Carbon date
                                        // $date = $arr['properties'][
                                        //     $scaffolding['properties']['schedule_post_date']['name']
                                        // ]['date']['start'];
                                        
                        
                                        // Get column names
                                        // Log::info($column_date);



                                        /**
                                         * FIXME - This is an error that's started to crop up on 15 Nov 2025, seems like some old posts are being re-scheduled for posting, i'm assuming it's because below this there is an updateOrCreate that sometimes fucks up? So I'm adding some safeguards here
                                         */

                                        // Add the entry to the database
                                        $insert = NotionPosts::updateOrCreate(
                                            [
                                                'post_page_id' => $page->id, // $arr['id']
                                            ],
                                            [
                                                'post_name' => $title,
                                                'userid' => $userid,
                                                'database_id' => $this->database->id,
                                                'account_id' => $account_info['id'],
                                                'platform' => $account_info['platform'],
                                                'platform_is_story' => $is_story,
                                                'status' => 'scheduled',
                                                'scheduled_date' => $date,
                                                'in_flight' => false,
                                                'is_active' => 1,
                                                'is_valid' => 1,
                                            ]
                                        );

                                        // try {
                                        //     Log::info($page->properties()->getAll());
                                        // } catch (\Throwable $e) {
                                        //     Log::info(343);
                                        //     Log::info($e);
                                        // }

                                        $context = [
                                            'origin' => "FindNotinoPostsInDB Scheduledd",
                                            'userid' => $userid,
                                            $insert
                                        ];
                                        $mark = new NotionHttp(
                                            $context,
                                            $token,
                                            $page->id,
                                            $column_comments,
                                            $column_status,
                                            $column_checkbox
                                        );
                                        $mark = $mark->markPostAsScheduled();

                                        /* 

                                        // Mark as scheduled and clear comments
                                        $props = $page->properties()
                                            ->change(
                                                // $scaffolding['properties']['comments']['name'],
                                                $column_comments,
                                                \Notion\Pages\Properties\RichTextProperty::fromString("")
                                            )->change(
                                                // $scaffolding['properties']['notion_status']['name'],
                                                $column_status,
                                                \Notion\Pages\Properties\Select::fromName(
                                                    $scaffolding['properties']['notion_status']['sub_options']['scheduled']['name']
                                                )
                                            )->change(
                                                // $scaffolding['properties']['is_ready']['name'],
                                                $column_checkbox,
                                                \Notion\Pages\Properties\Checkbox::createUnchecked()
                                            );

                                        // Save changes
                                        $page = $page->changeProperties($props->getAll());
                                        $notion->pages()->update($page);


                                        */


                                       
        
                                    } 

                                }
    
                            }
    
                        }
                        
                    // }
    
                    // NOTE - Are there errors? If so, append them to the post
                    if ($errors) {

                        
                        $context = [
                            'origin' => "FindNotinoPostsInDB Errors",
                            'userid' => $userid,
                            'database_id' => $this->database->id
                        ];
                        try {
                            $context[] = $page->toArray();;
                        } catch (\Exception $e) {
                            Log::info(416);
                            Log::info($e);
                        }
                        $mark = new NotionHttp(
                            $context,
                            $token,
                            $page->id,
                            $column_comments,
                            $column_status,
                            $column_checkbox
                        );
                        $mark = $mark->markPostAsError($errors);

                        /*
    
                        // dump("We have errors - Appending them now");
                    
                        // Update the error message
                        $props = $page->properties()
                        ->change(
                            // $scaffolding['properties']['comments']['name'],
                            $column_comments,
                            \Notion\Pages\Properties\RichTextProperty::fromString(
                                "⚠️ - " .
                                implode(" - ", $errors)
                            )
                        )->change(
                            // $scaffolding['properties']['notion_status']['name'],
                            $column_status,
                            \Notion\Pages\Properties\Select::fromName(
                                $scaffolding['properties']['notion_status']['sub_options']['error']['name']
                            )
                        )->change(
                            // $scaffolding['properties']['is_ready']['name'],
                            $column_checkbox,
                            \Notion\Pages\Properties\Checkbox::createUnchecked()
                        );
                        $page = $page->changeProperties($props->getAll());
                        if ($this->database->id == 126) {
                            Log::info("Proprs for DB are");
                            Log::info($props->getAll());
                        }
                        $notion->pages()->update($page);

                        */
    
                    }
    
                }
    
    
            }

            // Perform the update 
            $this->database->last_check_for_new_posts = Carbon::now();
            $this->database->save();


        } catch (\Exception $e) {

            // Make pretty
            $msg = $e->getMessage();

            $notionerror =  NotionErrorManager::manageError(
                $userid,
                $e,
                $this->database->token,
                "FindNotionPostsInDB",
                $this->database->id,
                null
            );

            if (!isset($notionerror['action'])) {
                Log::info("NotionErrorManager has en empty action");
                Log::info($msg);
                Log::info($notionerror);
            } else {

                if ($notionerror['action'] == 'correct_scaffolding') {
                    Log::info("FindNotionPosts in DB is issueing a 'correct scaffolding' order");
                    CorrectNotionDatabaseScaffolding::dispatch($this->database);
                }
    
                if ($notionerror['action'] != 'none') {
                    // Re-program the job
                    $this->release(120);
                }



            }
            
        } catch (\Throwable $e) {

            Log::info(455);
            Log::info($e);
            

            // Make pretty
            $msg = $e->getMessage();

            try {
                if (Str::of($msg)->contains('length should be')) {
                    Log::info("Check this out...");
                    Log::info($page->toArray());
                } else {
                    Log::info("470 UNHANDLED");
                }
            } catch (\Throwable $e) {
                Log::info(464);
                Log::info($e);
            }

            $notionerror =  NotionErrorManager::manageError(
                $userid,
                $e,
                $this->database->token,
                "FindNotionPostsInDB",
                $this->database->id,
                null
            );

            if (!isset($notionerror['action'])) {
                Log::info("NotionErrorManager has en empty action");
                Log::info($msg);
                Log::info($notionerror);
            } else {

                if ($notionerror['action'] == 'correct_scaffolding') {
                    CorrectNotionDatabaseScaffolding::dispatch($this->database);
                }
    
                if ($notionerror['action'] != 'none') {
                    // Re-program the job
                    $this->release(120);
                }

            }

            
        }
    }

     /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Send user notification of failure, etc...
        Log::withContext([
            'origin' => 'FindNotionPostsInDB Job',
            'database_id' => $this->database->id
        ]);
        Log::info("Failed job handler");
        Log::info($exception);

    }
}