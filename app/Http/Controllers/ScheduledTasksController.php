<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionSocialAccounts;
use App\Models\NotionPosts;
use App\Models\AccessTokens;
use App\Models\NotionSocialAccountsAccessTokens;

use App\Models\ErrorManager;
use App\Models\NotionScaffolding;

use Illuminate\Support\Facades\Config;

use Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Notion\Notion;
use Notion\Databases\Database;

use Notion\Databases\Query;
use Notion\Databases\Query\Sort;
use Notion\Databases\Query\CompoundFilter;
use Notion\Databases\Query\CheckboxFilter;
use Notion\Databases\Query\SelectFilter;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use VStelmakh\UrlHighlight\UrlHighlight;

use App\Jobs\ProcessNotionPost;


class ScheduledTasksController extends Controller
{


    /**
     * SECTION - This function checks against a given Notion Database and corrects any issues with it
     */
    public function correctNotionDatabaseScaffolding() {

        // // INIT
        // $interval_hours = 2;

        // // Get all the DBs we haven't scanned in a while
        // $to_scan = NotionDatabases::with('token')
        //     ->where('is_active', 1)
        //     ->where('is_valid', 1)
        //     ->where('last_check_scaffolding_scan', '<', Carbon::now()->subHours($interval_hours))
        //     // ->orderByDesc('last_check_scaffolding_scan')
        //     ->orderBy('last_check_scaffolding_scan', 'asc')
        //     ->limit(10)
        //     ->get();

        // if ($to_scan->count() < 1) {
        //     die("There are not Notion Databases that need their scaffoldings checked");
        // }

        // foreach ($to_scan as $db) {

        //     echo "Dispatching JOB - Databse with ID " . $db->id;
        //     \App\Jobs\CorrectNotionDatabaseScaffolding::dispatch($db);

        // }
    }

    /**
     * SECTION - Fix NotionScheduler issues
     */
    public function correctNotionDatabaseScaffolding2() {

        //  // INIT
        //  $interval_hours = 2;

        // //  funcfeifhzoie();

        //  // Get all the DBs we haven't scanned in a while
        //  $to_scan = NotionDatabases::with('token')
        //     ->where('is_active', 1)
        //     ->where('is_valid', 1)
        //     ->where('id', 34)
        //      ->where('last_check_scaffolding_scan', '<', Carbon::now()->subHours($interval_hours)) // FIXME 
        //     // ->orderByDesc('last_check_scaffolding_scan')
        //     ->orderBy('last_check_scaffolding_scan', 'asc')
        //     ->limit(10)
        //     ->get();
 
        //  if ($to_scan->count() < 1) {
        //      die("There are not Notion Databases that need their scaffoldings checked");
        //  }
 
        //  foreach ($to_scan as $db) {

        //     // Looks like we're all set to go
        //     $notion = Notion::create($db->token->token);
        //     $database = $notion->databases()->find($db->database_id);

        //     // Get properties
        //     $props = $database->properties();
            
        //     $file_prop_name = $props->getById($db->column_media)->metadata()->name;
        //     dump($file_prop_name);
        //     dump($props->getById($db->column_social_account));
        //     dump($props->getById($db->column_social_account)->options);

        //     $pages = $notion->databases()->queryAllPages($database);

        //     foreach ($pages as $page) {

        //         dump($page);
        //         dump($page->id);
        //         dump($page->properties());

        //         $clean_props = [];
        //         foreach ($page->properties()->getAll() as $name => $prop) {
        //             $clean_props[
        //                 $prop->metadata()->id
        //             ] = $name;
        //         }

        //         dump("Cleanprops");
        //         dump($clean_props);


                

        //         $files = $page->properties()->getById($db->column_media)->files;
        //         dump($files);
        //         dump(empty($page->properties()->getById($db->column_media_thumbnail)->files));

        //         dump($page->properties()->getById($db->column_social_account)->option);
        //         dump($page->properties()->getById($db->column_social_account)->isEmpty());
        //         // dump($page->properties()->getById($db->column_social_account)->option->name);

        //         $date = $page->properties()->getById($db->column_post_date);
        //         dump($date);
        //         $date = $date->start();
        //         dump($date);
        //         $date = Carbon::parse($date);
        //         dump($date);
        //         $date = $date->setTimezone(
        //             date_default_timezone_get()
        //         );
        //         dump($date);

        //         die();

        //         // $test = $page->properties()->getById('inexistant_id');

        //         dump($clean_props);

        //     }

        //     // Get all our social accounts
        //     $slugs = NotionSocialAccounts::getAllSlugsFromUser($db->id, $db->userid);
        //     $slug_keys = array_keys($slugs);

        //     // Get default scaffolding
        //     $scaffolding = NotionDatabases::getDefaultScaffolding();

        //     dump($scaffolding);

        //     $propertiesToAdd = [];

        //     foreach ($scaffolding['properties'] as $scaff) {

        //         $column = $scaff['column'];
        //         $col_id = $db->$column;
        //         $prop = $props->getById($col_id);

        //         dump($prop);

        //     }

        //     // Update all entries in the DB once everything is fixed?


        //     die("Death after 1");
 
        //  }

    }

    /** 
     * SECTION - Query database to see if there are any posts that are ready to go
     */
    public function queryDBAndFindReadyPosts() {

        // // INIT
        // $interval_minutes = 5;

        // // Get all the DBs we haven't scanned in a while
        // $to_scan = NotionDatabases::with('token')
        //     ->where('is_active', 1)
        //     ->where('is_valid', 1)
        //     ->where('last_check_for_new_posts', '<', Carbon::now()->subMinutes($interval_minutes)) // FIXME - Comment this if you want to constantly check
        //     ->limit(10)
        //     ->orderBy('last_check_for_new_posts', 'asc')
        //     ->get();

        // if ($to_scan->count() < 1) {
        //     die("There are not Notion Databases that need their posts checked");
        // }

        // dump($to_scan);

        // foreach ($to_scan as $db) {

        //     echo "Dispatching JOB - Databse with ID " . $db->id;
        //     \App\Jobs\FindNotionPostsInDB::dispatch($db);

        // }

        // die('DEATH');

    }

    /**
     * SECTION - Reset the in_flight status for posts that have been in-flight for too long
     */
    public function resetInFlightStatus() {

        // Get all the posts that have in-flight issues
        // echo "<h2>Getting posts with in-flight issues</h2>";
        // $interval_mins = 20;

        // // Get
        // $get = NotionPosts::where('status', 'error')
        //     ->where('in_flight', 1)
        //     ->where('in_flight_start', '<', Carbon::now()->subMinutes($interval_mins))
        //     ->get();

        // // Check to see if we have any
        // if ($get->count() < 1) {
        //     die("We have no in_flight scheduled posts older than $interval_mins minutes");
        // }

        // // Perform task
        // echo "We have " . $get->count() . " posts that been inflight more than $interval_mins minutes. Resetting their status now";
        // $do = NotionPosts::whereIn('id', $get->pluck('id')->all())
        //     ->update(['in_flight' => 0]);

    }




    /** 
     * SECTION - This is to post social posts
     */
    public function postScheduledPosts() {

        // // Get all scheduled posts
        // $scheduled_posts = NotionPosts::with('account')->with('user')
        //     ->where('scheduled_date', '<=', Carbon::now())
        //     ->where('status', 'scheduled')
        //     ->where('in_flight', 0)
        //     ->where('is_active', 1)
        //     ->where('is_valid', 1)
        //     ->get();

        // // Check if we have any posts to work on
        // if (!$scheduled_posts->count()) {
        //     die("No posts to handle");
        // }

        // foreach ($scheduled_posts as $post) {
        //     ProcessNotionPost::dispatch($post);
        // }


    }


    /**
     * SECTION - Check Social accounts
     */
    public function checkSocialTokens() {

        // // INIT
        // $interval_hours = 6;

        // // Get all the DBs we haven't scanned in a while
        // \DB::statement("SET SQL_MODE=''"); // NOTE - Temporarily disables safe mode
        // $to_scan = NotionSocialAccounts::with('access_token')
        //     ->where('is_active', 1)
        //     ->where('is_valid', 1)
        //     ->where('last_token_check_scan', '<', Carbon::now()->subHours($interval_hours))
        //     // ->orderByDesc('last_token_check_scan')
        //     ->orderBy('last_token_check_scan', 'asc')
        //     ->groupBy("token_id")
        //     ->get();
            

        // if ($to_scan->count() < 1) {
        //     die("There are not Social Accounts that need checking");
        // }

        // foreach ($to_scan as $social_account) {

        //     \App\Jobs\CheckSocialTokens::dispatch($social_account);

        //     // continue;
        //     // die("DEATH");

        // }

        

        // die("Dieing here");



    }



    /**
     * SECTION - Update the scaffolding
     */
    public function setDefaultScaffolding() {
        

        // // Get MHH's token
        // $token = NotionAccessTokens::where('userid', 1)->first();

        // // Get the page where everything is located
        // $pageId = "b5e01560c65f41b796f7e1d635370b71";
        // $notion = Notion::create($token->token);
        // $content = $notion->blocks()->findChildrenRecursive($pageId);

        // // Create the content array
        // $new_content = [];
        // foreach ($content as $con) {
        //     $new_content[] = $con->toArray();
        // }

        // // Upload it to the DB
        // $new = new NotionScaffolding;
        // $new->scaffolding = json_encode($new_content);
        // $new->save();


    }

    /**
     * SECTION - Remove unused tokens
     */
    public function removeUnusedSocialTokens() {

        // $social_accounts = NotionSocialAccounts::get()->pluck('token_id')->all();
        // $unusedTokens = NotionSocialAccountsAccessTokens::whereNotIn('id', $social_accounts)->delete();

        // $unusued_databases = NotionDatabases::where('is_valid', 0)->where('is_active', 0)->delete();

    }


    /**
     * SECTION - Remove temp files
     */
    public function deleteOldUploads() {

        // $folder = '/public/uploadable_media';
        // $files = Storage::files($folder);

        // if (!count($files)) {
        //     die("Nothing to do here");
        // }

        // dump($files);

        // // Make
        // $now = Carbon::now();

        // // Loop
        // foreach ($files as $file) {

        //     $root = Str::of($file);
        //     $filename = $root->remove('public/uploadable_media/')->explode('-')->last();
        //     $time_posted = Str::of($filename)->explode('.')->first();
        
        //     // Check time difference
        //     if (Carbon::createFromTimestamp($time_posted)->diffInHours(Carbon::now()) > 12) {
        //         Storage::delete($file);
        //         dump("Deleting $filename...");
        //     }

        // }


    }
    




}