<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionDatabases;
use App\Models\NotionSocialAccounts;
use App\Models\NotionErrorManager;

use Notion\Notion;
use Notion\Databases\Database;
use Notion\Databases\Properties\Title;


use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Contracts\Queue\ShouldBeUnique;

class CorrectNotionDatabaseScaffolding implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        // Perform some clean up
        $userid = $this->database->userid;
        $token = $this->database->token->token;
        $saveDb = false;

        Log::withContext([
            'origin' => 'CorrectNotionDatabaseScaffolding Job',
            'database_id' => $this->database->id
        ]);


        try {
            // Looks like we're all set to go
            $notion = Notion::create($token);
            $database = $notion->databases()->find($this->database->database_id);

            // Get properties
            $props = $database->properties();

            // Get all our social accounts
            $slugs = NotionSocialAccounts::getAllSlugsFromUser($this->database->id, $userid);
            $slug_keys = array_keys($slugs);

            // Get default scaffolding (beta users also get the trial columns)
            $scaffolding = NotionDatabases::getDefaultScaffolding($this->database->userid);

            // Array of unfound elements
            $unfound_columns = [];

            foreach ($scaffolding['properties'] as $key => $element) {

                // Clean up
                $column = $element['column'];

                try {

                    // Reset
                    $get = false;

                    // Resolve the property by NAME, not by stored ID.
                    // The name is the stable key we control in our scaffolding; Notion property
                    // IDs rotate whenever a user duplicates the template or deletes/re-adds a
                    // column, so they can't be the lookup key. The stored column ID is only a
                    // cache of the last value we saw.
                    try {

                        $get = $props->get($element['name']);

                    } catch (\Exception $e) {

                        // Genuinely absent by name → hand off to the create path in the catch below
                        throw new \Exception("Property " . $element['name'] . " not found");

                    }

                    // Self-heal: if the cached ID drifted (recreated DB, duplicated template),
                    // refresh it. The end-of-job $this->database->save() persists it.
                    $liveId = $get->metadata()->id;
                    if ($this->database->$column !== $liveId) {
                        $this->database->$column = $liveId;
                    }

                    // We found the prop, lets get its type
                    // if ($get = $props->get($element['name'])) {

                        // Make pretty
                        $get = $get->toArray();
                        $name = $element['name'];
                        $prop_id = $get['id'];

                        // Check if the property is correct
                        if ($element['type'] != $get['type']) {
                            
                            throw new \Exception("Property '$name' is not the right type, currently type " . $get['type'] . " should be " . $element['type']);

                        }

                        // Perform sub-check for options
                        if ($get['type'] == "select") {

                            // Reset our missing options parameter
                            $missingOptions = [];
                            $excessOptions = [];

                            // CASE - Case for the Notion Status checker
                            if ($element['name'] == $scaffolding['properties']['notion_status']['name']) {

                                // Check if we have options
                                if (!$get['select']['options']) {
                                    $missingOptions = [];
                                    // Get ALL the missing options
                                    foreach ($scaffolding['properties']['notion_status']['sub_options'] as $keyc => $correct_option) {
                                        $missingOptions[] = $keyc;
                                    }
                                } else {
                                    
                                    // We have options, lets check to see if we have all of them
                                    $current_options = [];
                                    foreach ($get['select']['options'] as $option) {
                                        $current_options[] = $option['name'];
                                    }
                                    $correct_options = [];
                                    foreach ($scaffolding['properties']['notion_status']['sub_options'] as $keyc => $correct_option) {
                                        $correct_options[] = $correct_option['name'];
                                        if (!in_array($correct_option['name'], $current_options)) {
                                            $missingOptions[] = $keyc;
                                        }
                                    }
                                
                                }


                            }
                            // CASE - Case Accounts
                            if ($element['name'] == $scaffolding['properties']['social_accounts']['name']) {

                                // Check if we have 0 options
                                if (!$get['select']['options']) {
                                    $missingOptions = array_keys($slugs);
                                } else {

                                    // Loop through all of the options to see which ones are missing
                                    $current_options = [];
                                    foreach ($get['select']['options'] as $option) {
                                        $current_options[] = $option['name'];
                                    }

                                    // Loop through all the ones we have to see which ones are missing
                                    foreach ($slugs as $slug => $slug_content) {
                                        if (!in_array($slug, $current_options)) {
                                            $missingOptions[] = $slug;
                                        }
                                    }

                                    // Now loop through al the current_options to see if there are any that are in excess
                                    foreach ($current_options as $current_option) {
                                        if (!in_array($current_option, $slug_keys)) {
                                            $excessOptions[] = $current_option;
                                        }
                                    }

                                }

                            }

                            // Check if we have options to handle
                            if ($missingOptions or $excessOptions) {
                                // Log::info("Missing options");
                                // Log::info($missingOptions);
                                // Log::info("Excess options");
                                // Log::info($excessOptions);
                                echo "$name has incorrect options<br />";
                                throw new \Exception("Property '$name' has incorrect options");
                            }
                        

                        }
                    
                    // }

                } catch (\Exception $e) {

                    // Check if the case is that the prop isn't found
                    $msg = $e->getMessage();
                    Log::info($msg);
                    dump($e);
                    if (explode(' ', trim($msg ))[0] == "Property") {
                        if (str_contains($msg, $element['name'])) {

                            // Get the right type
                            $prop_type = $element['notion_type'];
                            $prop_name = $element['name'];

                            // CASE - Property not found
                            if (str_contains($msg, "not found")) {

                                $saveDb = true;

                                // Switch case where it's an option selector
                                if ($element['type'] == "select") {

                                    // CASE - This could be refactored to go somewhere else?
                                    // FIXME - Could this be refactored somewhere?
                                    if ($key == "notion_status") {
                                        $create = $prop_type::create(
                                            $prop_name,
                                            NotionDatabases::getDefaultScaffoldingForScheduledOptions()
                                        );
                                    }

                                    // CASE - Accounts
                                    if ($key == "social_accounts") {

                                        $social_options = [];
                                        /* foreach ($slugs as $slug) {
                                            $social_options[] = 
                                        } */

                                        // Create the base
                                        $create = $prop_type::create($prop_name);

                                        // Create handler
                                        $option_type = $scaffolding['options']['type'];

                                        // Add all the options
                                        foreach ($slugs as $slug => $slug_content) {
                                            $create = $create->addOption(
                                                $option_type::fromName($slug)
                                                    ->changeColor(
                                                        NotionSocialAccounts::getColorFromSlug($slug)
                                                    )
                                            );
                                        }
                                    }


                                } else {
                                    $create = $prop_type::create($prop_name);
                                }

                                $database = $database->addProperty($create);

                                $saveDb = true;

                            }

                            // CASE - Property has wrong type
                            if (str_contains($msg, "is not the right type")) {

                                $saveDb = true;

                                $create = $prop_type::create($prop_name);
                                $database = $database->removePropertyByName($prop_name);
                                $database = $database->addProperty($create);
                                

                            }

                            // CASE - Property of type "select" has MISSING options
                            if (str_contains($msg, "has incorrect options")) {

                                $saveDb = true;

                                // Get the prop ID we want to change
                                $toChange = $database->properties()->getById($prop_id);

                                // CASE - Case status
                                if ($key == "notion_status") {
                                    
                                    $correct_select_options = NotionDatabases::getDefaultScaffoldingForScheduledOptions();
                                    $toChange = $toChange->changeOptions(...$correct_select_options);
                                    $database = $database->changeProperty($toChange);

                                    /*
                                    foreach ($missingOptions as $missingOption) {
                                        $toChange = $toChange->addOption(
                                            NotionDatabases::getDefaultScaffoldingForScheduleOptionsByKey($missingOption)
                                        );
                                    }
                                    $database = $database->changeProperty($toChange); */

                                }

                                // CASE - Case accounts
                                if ($key == "social_accounts") {

                                    // Create handler
                                    $option_type = $scaffolding['options']['type'];

                                    // Log::info("Slug keys are...");
                                    // Log::info($slug_keys);
                                    // if (isset($current_options)) {
                                    //     Log::info("Current options are...");
                                    //     Log::info($current_options);
                                    // }
                                    // Log::info($get['select']['options']);

                                    // Create a collection
                                    $options_collection = collect($get['select']['options']);

                                    $correct_slug_options = [];
                                    foreach ($slug_keys as $slug_key) {

                                        // Check if we're in the array
                                        $found = $options_collection->firstWhere('name', $slug_key);
                                        if ($found) {
                                            $color = $found['color'];
                                            $color_obj = \Notion\Common\Color::from($color);
                                        } else {
                                            $color_obj = NotionSocialAccounts::getColorFromSlug($slug_key);
                                        }

                                        $correct_slug_options[] = $option_type::fromName($slug_key)
                                            ->changeColor($color_obj);
                                    }

                                    $toChange = $toChange->changeOptions(...$correct_slug_options);
                                    $database = $database->changeProperty($toChange);


                                    // Create handler
                                    // $option_type = $scaffolding['options']['type'];

                                    // foreach ($missingOptions as $missingOption) {
                                    //     $toChange = $toChange->addOption(
                                    //         $option_type::fromName($missingOption)
                                    //             ->changeColor(
                                    //                 NotionSocialAccounts::getColorFromSlug($missingOption)
                                    //             )
                                    //     );
                                    // }
                                    // $database = $database->changeProperty($toChange);

                                }
                            }

                        }
                    }
                }
            }

            // FIXME
            // FIXME
            // FIXME
            // This script goes through the default scaffolding and tries to find the default column names
            // If it finds a default column name, that usually means that it was recently fixed or added, which means we should save its ID in the DB 
            foreach ($scaffolding['properties'] as $key => $element) {
                try {
                    $column = $element['column'];
                    $prop_id = $database->properties()->get(
                        $element['name']
                    )->metadata()->id;
                    $this->database->$column = $prop_id;
                } catch (\Exception $e) {
                    // Run a check to see if we actually have it in the DB?
                    try {
                        $column = $element['column'];
                        $get = $props->getById($this->database->$column);
                        // Log::info("Found column for column $column with ID " . $this->database->$column);
                    } catch (\Exception $e) {
                        Log::info(394);
                        Log::info($e);
                    }
                }
            }

            // Save to DB
            if ($saveDb) {

                // Only round-trip the columns we manage. Client::update() PATCHes
                // the ENTIRE database back to Notion, re-serializing every property
                // — including user columns whose type our SDK can't model (they
                // become Unknown and replay their read-only shape). Notion rejects
                // those on write ("... is not a valid property schema"), which would
                // fail the whole update over a column we don't even touch.
                //
                // A PATCH leaves omitted properties untouched (only an explicit
                // null deletes one), and removePropertyByName() omits — it does not
                // null — so this never deletes the user's columns. The title
                // property must stay or the SDK refuses to build the database.
                $managed = array_map(fn($el) => $el['name'], $scaffolding['properties']);
                foreach ($database->properties()->getAll() as $name => $prop) {
                    if ($prop instanceof Title) {
                        continue;
                    }
                    if (in_array($name, $managed, true)) {
                        continue;
                    }
                    $database = $database->removePropertyByName($name);
                }

                // Save the DB
                $database = $notion->databases()->update($database);

                // NOTE - 
                // Perform a save on our social media accounts
                // $select = $database->properties()->get(
                //     $scaffolding['properties']['social_accounts']['name']
                // )->toArray();
                $select = $database->properties()->getById(
                    // $scaffolding['properties']['social_accounts']['name']
                    $this->database->column_social_account
                );

                // Go through them
                if (count($select->options) > 0) {
                    foreach ($select->options as $option) {
                        if (isset($slugs[$option->name])) {
                            NotionSocialAccounts::where('id', 
                                $slugs[$option->name]['id']
                            )
                            ->update(
                                [
                                    'option_select_id' => $option->id
                                ]
                            );
                        }
                    }
                }
                // if ($select) {
                //     if ($select['select']['options']) {
                //         foreach ($select['select']['options'] as $select_option) {
                //             if (isset($slugs[$select_option['name']])) {

                //                 // Make pretty
                //                 $cur_slug = $slugs[$select_option['name']];
                //                 dump($cur_slug);
                                
                //                 // Update the id
                //                 NotionSocialAccounts::where('id', $cur_slug['id'])
                //                     ->update(
                //                         [
                //                             'option_select_id' => $select_option['id']
                //                         ]
                //                         );

                //             }
                //         }
                //     }
                // }
            } else {
                dump("Looks like all is clean in this DB and there is nothing to do");
            }

            

            // Mark the database as scanned
            $this->database->last_check_scaffolding_scan = Carbon::now();
            $this->database->save();

        } catch (\Exception $e) {

            // Run the NotionErrorManager;
            NotionErrorManager::manageError(
                $userid,
                $e,
                $this->database->token,
                "CorrectNotionDatabaseScaffoldingJob",
                $this->database->id,
                null
            );

        }

        
    }
}