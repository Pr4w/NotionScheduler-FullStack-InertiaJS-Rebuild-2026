<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionPosts;
use App\Models\NotionDatabases;
use App\Models\NotionAccessTokens;
use App\Models\NotionErrorManager;

use App\Models\NotionHttp;

use App\Jobs\CorrectNotionDatabaseScaffolding;

use Notion\Notion;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

class UpdateNotionDatabaseEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionPosts $post,
        public $situation
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $context = [
            'origin' => 'UpdateNotionDatabaseEntry Job',
            'post_id' => $this->post->id,
            'post_name' => $this->post->post_name
        ];
        Log::withContext($context);

        // Init the usual suspects
        $situations = [
            'unschedule',
            'reschedule'
        ];

        // Have a guard in place just in case
        if (!in_array($this->situation, $situations)) {
            $this->fail("Unhandled situation in UNDBE");
            return;
        }

        // Get the Notion object from the Post
        $notion_database = NotionDatabases::where('id', $this->post->database_id)->first();

        // Check
        if (!$notion_database) {
            $this->fail("UNDBE ERR39 - No Notion Database found");
        }

        // Get the corresponding token
        $notion_token = NotionAccessTokens::where('id', $notion_database->token_id)->first();
        if (!$notion_token) {
            $this->fail("UNDBE ERR46 - No Notion Database found");
        }

        // Create Notion object
        $notion = Notion::create($notion_token->token);

        // Load the scaffolding
        $scaffolding = NotionDatabases::getDefaultScaffolding();
        
        /**
         * CASE - Remove a post from the schedule, so update it in the DB
         */
        if ($this->situation == "unschedule") {

            try {

                // Get the page
                $page = $notion->pages()->find($this->post->post_page_id);
                $database = $notion->databases()->find($notion_database->database_id);

                $column_comments = $database->properties()->getById($notion_database->column_ns_comments)->metadata()->name;
                $column_checkbox = $database->properties()->getById($notion_database->column_is_ready)->metadata()->name;
                $column_status = $database->properties()->getById($notion_database->column_ns_status)->metadata()->name;

                $do = new NotionHttp(
                    $context,
                    $notion_token->token,
                    $this->post->post_page_id,
                    $column_comments,
                    $column_status,
                    $column_checkbox
                );

                $do = $do->resetPostToDefault();


                /* 
                // Mark as scheduled and clear comments
                $props = $page->properties()
                    // NOTE - Removing comments
                    ->change(
                        // $scaffolding['properties']['comments']['name'],
                        $column_comments,
                        \Notion\Pages\Properties\RichTextProperty::fromString("")
                    )
                    // NOTE - Removing status
                    ->change(
                        // $scaffolding['properties']['notion_status']['name'],
                        $column_status,
                        \Notion\Pages\Properties\Select::createEmpty()
                    )
                    // NOTE - Removing tick
                    ->change(
                        // $scaffolding['properties']['is_ready']['name'],
                        $column_checkbox,
                        \Notion\Pages\Properties\Checkbox::createUnchecked()
                    );

                // Save changes
                $page = $page->changeProperties($props->getAll());
                $notion->pages()->update($page); */


            } catch (\Exception $e) {

                // Make pretty
                $msg = $e->getMessage();
                Log::debug($e);
                Log::debug($msg);

                // Check if we have an issue with a property not being found
                if (Str::of($msg)->containsAll(['Property', 'not found'])) {

                    // Property not found, this could mean an issue with one of the columns, so lets dispatch a job to correct it, and then re-attempt this jpb
                    CorrectNotionDatabaseScaffolding::dispatch($notion_database);
                    $this->release(120);

                } else {


                    NotionErrorManager::manageError(
                        $this->post->userid,
                        $e,
                        $notion_token->token,
                        "UpdateNotionDatabseEntry Job - Unschedule",
                        $this->post->database_id,
                        $this->post->id
                    );

                }   

            }

        }


        /** 
         * SECTION - Re-schedule a post, so update it in the DB
         */
        if ($this->situation == "reschedule") {

            try {

                // Get the page
                $page = $notion->pages()->find($this->post->post_page_id);
                $database = $notion->databases()->find($notion_database->database_id);

                // Convert to array because fuck it
                // $arr = $page->toArray();

                // Date
                // $date = $arr['properties'][
                //     $scaffolding['properties']['schedule_post_date']['name']
                // ]['date']['start'];
                $date = $page->properties()->getById($notion_database->column_post_date)->start();
                $date = Carbon::parse($date)->setTimezone(
                    date_default_timezone_get()
                );

                $this->post->scheduled_date = $date;
                $this->post->save();
                
            } catch (\Exception $e) {

                // Make pretty
                $msg = $e->getMessage();
                Log::debug($e);
                Log::debug($msg);

                // Check if we have an issue with a property not being found
                if (Str::of($msg)->containsAll(['Property', 'not found'])) {

                    // Property not found, this could mean an issue with one of the columns, so lets dispatch a job to correct it, and then re-attempt this jpb
                    CorrectNotionDatabaseScaffolding::dispatch($notion_database);
                    $this->release(120);

                } else {

                    NotionErrorManager::manageError(
                        $this->post->userid,
                        $e,
                        $notion_token->token,
                        "UpdateNotionDatabseEntry Job - Reschedule",
                        $this->post->database_id,
                        $this->post->id
                    );

                }

            }
            


        }

    }
}