<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionPosts;
use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use Notion\Notion;

use App\Models\NotionHttp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Carbon\Carbon;

class UpdateNotionPostInDatabaseAfterUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $is_success,
        public $message,
        public NotionPosts $post,
        public $foreign_id = null
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        config('logging.default', 'posts');
        $context = [
            'origin' => 'UpdateNotionPostInDatabaseAfterUpload Job',
            'post_id' => $this->post->id,
            'post_platform' => $this->post->platform,
            'post_name' => $this->post->post_name,
            'success' => $this->is_success,
            'message' => $this->message,
            'post user id' => $this->post->userid
        ];
        Log::withContext($context);

        /**
         * SECTION - Update DB
         */
        // Now check according to the status
        if ($this->is_success) {
            $key = 'posted';
            $this->post->status = 'posted';
            $this->post->posted_date = Carbon::now();
            // Never overwrite a previously-stored id with a blank one. A missing
            // upstream id (e.g. an absent LinkedIn response header) would otherwise
            // wipe posted_foreign_id and silently break metrics/webhook matching.
            if (filled($this->foreign_id)) {
                $this->post->posted_foreign_id = $this->foreign_id;
            } else {
                Log::warning("Post #" . $this->post->id . " (" . $this->post->platform . ") submitted but returned no foreign id; leaving posted_foreign_id unchanged.");
            }
            Log::info("Successfully submitted post with ID #" . $this->post->id);
        } else {
            $this->post->status = 'error';
            $key = 'error'; 
            Log::info("Failed at submitting post with ID #" . $this->post->id);
        }

        // In either case, reset the in_flight
        $this->post->in_flight = 0;

        // Perform the update in the DB
        $this->post->save();



        /**
         * SECTION - Update the Notion page
         */

        // Get the database from the post id
        $notion_database = NotionDatabases::where('id', $this->post->database_id)->first();

        if ($notion_database) {

            try {

                // Get the token from the database
                $token = NotionAccessTokens::where('id', $notion_database->token_id)->first();

                $notion = Notion::create($token->token);
                $database = $notion->databases()->find($notion_database->database_id);

                $column_comments = $database->properties()->getById($notion_database->column_ns_comments)->metadata()->name;
                $column_checkbox = $database->properties()->getById($notion_database->column_is_ready)->metadata()->name;
                $column_status = $database->properties()->getById($notion_database->column_ns_status)->metadata()->name;

                // Create new NotionHttp object
                $do = new NotionHttp(
                    $context,
                    $token->token,
                    $this->post->post_page_id,
                    $column_comments,
                    $column_status,
                    $column_checkbox
                );

                // Switch the case
                if ($this->is_success) {
                    $do = $do->markPostAsSuccessful($this->message);
                } else {
                    $do = $do->markPostAsError($this->message);
                }

                /* 
                
                // Create the Notion object
                $notion = Notion::create($token->token);
                $page = $notion->pages()->find($this->post->post_page_id);
                $database = $notion->databases()->find($notion_database->database_id);

                $column_comments = $database->properties()->getById($notion_database->column_ns_comments)->metadata()->name;
                $column_checkbox = $database->properties()->getById($notion_database->column_is_ready)->metadata()->name;
                $column_status = $database->properties()->getById($notion_database->column_ns_status)->metadata()->name;

                // Get the scaffolding
                $scaffolding = NotionDatabases::getDefaultScaffolding();

                // Update the properties
                $updated_props = $page->properties()
                    ->change(
                        // $scaffolding['properties']['notion_status']['name'],
                        $column_status,
                        \Notion\Pages\Properties\Select::fromName(
                            $scaffolding['properties']['notion_status']['sub_options'][$key]['name']
                        )
                    )->change(
                        // $scaffolding['properties']['comments']['name'],
                        $column_comments,
                        \Notion\Pages\Properties\RichTextProperty::fromString($this->message)
                    )->change(
                        // $scaffolding['properties']['is_ready']['name'],
                        $column_checkbox,
                        \Notion\Pages\Properties\Checkbox::createUnchecked()
                    );
                

                // Save changes
                $page = $page->changeProperties($updated_props->getAll());
                $notion->pages()->update($page);

                */

            } catch (\Exception $e) {

                // Make pretty
                $msg = $e->getMessage();

                // Check if we have an issue with a property not being found
                if (Str::of($msg)->containsAll(['Property', 'not found'])) {

                    // Property not found, this could mean an issue with one of the columns, so lets dispatch a job to correct it, and then re-attempt this jpb
                    CorrectNotionDatabaseScaffolding::dispatch($notion_database);
                    $this->release(120);

                } else {

                    Log::debug("UpdateNotionPostInDatabaseAfterUpload - 175");
                    Log::debug($e);
                    Log::debug($msg);

                }

            }

            

        } else {
            Log::warning("UpdateNotionPostInDatabaseAfterUpload - Couldn't update database after submitting a post...");
        }

        

        

    }
}