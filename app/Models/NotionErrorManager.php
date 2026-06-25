<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\NotionDatabases;
use App\Models\User;

use App\Mail\RemovedDatabase;
use Illuminate\Support\Facades\Mail;

class NotionErrorManager extends Model
{
    
    /** 
     * SECTION - Manage errors that are thrown by Notion queries
     * 
     * user_id = string
     * database = Laravel DB object
     * $error = \Exception
     */
    public static function manageError(
        $user_id, 
        $error, 
        $token,

        $origin_of_error = null,

        $database_id = null,
        $post_id = null,
        ) {

        // Convert error to message
        $msg = $error->getMessage();

        // CASE - Api token is invalid
        if ($msg == "API token is invalid.") {

            // TODO - Remove token from DB? Or mark it as invalid?
            if ($database_id) {
                // Log::info("NotionErrorManager - Origin is $origin_of_error - DB with id $database_id for user $user_id can't be accessed / we are not authorized - Removing it now");
                self::disableDatabase(
                    $database_id, 
                    "Our services could no longer find your database. Was it deleted, moved, or did you remove NotionScheduler's access to your workspace?"
                );
            }
            return [
                'type' => 'danger',
                'message' => "CODE #42 - It looks like your Notion Access Token is invalid. Try re-authorizing NotionScheduler to access your account via your Dashboard.",
                'action' => 'none',
            ];

        // CASE - Could not find database with ID
        } elseif (str_contains($msg, "Could not find database with ID")) {

            // TODO
            if ($database_id) {
                // Log::info("NotionErrorManager - Origin is $origin_of_error - DB with id $database_id for user $user_id not found - Marking it is as invalid.");
                self::disableDatabase(
                    $database_id, 
                    "Our services could no longer find your database. Was it deleted, moved, or did you remove NotionScheduler's access to your workspace?"
                );
            }

            // TODO
            return [
                'type' => 'danger',
                'message' => "CODE #65 - There was an issue querying this specific Database, does it still exist? If the issue persists, please contact an admin.",
                'action' => 'none',
            ];
        
        // CASE - Could not find a page with ID
        } elseif (str_contains($msg, "Could not find page with ID")) {

            if ($post_id) {
                // Log::info("NotionErrorManager - Origin is $origin_of_error - Post page with id $post_id for user $user_id not found - Marking it is as invalid.");
                $do = NotionPosts::find($post_id);
                $do->is_valid = 0;
                $do->save();

            }            

            return [
                'type' => 'danger',
                'message' => "CODE #88 - There was an issue querying this specific Page, does it still exist? If the issue persists, please contact an admin.",
                'action' => 'none',
            ];

        // CASE - Can't find a block, this usually happens when we're using the findChildrenRecursive on a page, but that page was deleted by the user, so it fails to get everything. In this case, we should delete the post from the integran
        } elseif (str_contains($msg, "Could not find block with ID")) {

            if ($post_id) {
                // Log::info("NotionErrorManager - Origin is $origin_of_error - Post page with id $post_id for user $user_id is missing blocks, usually this occurs when the parent page was deleted - Marking it is as invalid.");
                $do = NotionPosts::find($post_id);
                $do->is_valid = 0;
                $do->save();
            }

            return [
                'type' => 'danger',
                'message' => "CODE #99 - There was an issue querying this specific Page BLOCK, does it still exist? If the issue persists, please contact an admin.",
                'action' => 'none',
            ];

        // CASE - Page has been archived
        } elseif (Str::of($msg)->contains("Can't edit page on block with an archived ancestor")) {

            if ($post_id) {
                // Log::info("NotionErrorManager - Origin is $origin_of_error - Post page with id $post_id for user $user_id has an archived parent, usually this occurs when the parent page was deleted - Marking it is as invalid.");
                $do = NotionPosts::find($post_id);
                $do->is_valid = 0;
                $do->save();
            }

            return [
                'type' => 'danger',
                'message' => "CODE #108 - There was an issue querying this specific Page BLOCK, does it still exist? If the issue persists, please contact an admin.",
                'action' => 'none',
            ];

        // CASE - One of our scripts is trying to edit a block that has been archived or deleted, this might happen if a user decides to delete a post within their dashboard 
        } elseif (Str::of($msg)->contains("Can't edit block that is archived")) {

            return [
                'type' => 'warning',
                'message' => "It looks like you're trying to edit a page or database that has been archived or deleted. If this isn't the case and the issue persists, please contact an admin",
                'action' => 'none'
            ];


        // CASE - One of our jobs is looking for a property that doesn't exist in the DB, so all we want to do here is return an action that says to correct the scaffolding
        } elseif (Str::of($msg)->containsAll(['Property', 'not found'])) {

            return [
                'action' => 'correct_scaffolding'
            ];

        } elseif(Str::of($msg)->containsAll(['Notion\Databases\Properties\PropertyCollection::getById()', 'must be of type string, null given'])) {

            // Log::warning("Database with ID $database_id is showing signs of missing columns, trying to correct scaffolding...");
            return [
                'action' => 'correct_scaffolding'
            ];

        } elseif(Str::of($msg)->contains([
            'is expected to be', // Like "NotionScheduler Comments is expected to be url"
        ])) {

            // Log::warning("Database with ID $database_id is showing signs of broken / modified columns, trying to correct scaffolding...");
            return [
                'action' => 'correct_scaffolding'
            ];

        // CASE - Notion API is tired 
        } elseif (Str::of($msg)->contains([
            'Public API service is temporarily unavailable',
            'Notion is unavailable, please try again later'
            ])) {

            return [
                'type' => 'warning',
                'message' => "Notion's API is currently experiencing isues, please try again. If the issue persists, please contact an admin.",
                'action' => 'release',
            ];

        // CASE - cURL error, here we don't want to do anything, just release the job
        } elseif (Str::of($msg)->contains(['cURL error 56', 'cURL error 35', 'cURL error'])) {

            return [
                'action' => 'release'
            ];

        // CASE - cURL error where we can't reach the API for some reason
        } elseif (Str::of($msg)->contains('Could not resolve host'))  {
            
            return [
                'action' => 'release'
            ];

        // CASE - Not entirely sure what this means, lets monitor it
        } elseif(Str::of($msg)->contains("Notion\Infrastructure\Http::parseBody(): Return value must be of type array, null")) {

            // Log::warning("Http::parseBody has returned null, is there an issue with the Notion connecter? Not sure - I've released the job but lets keep an eye on it.");

            // Try again
            return [
                'action' => 'release'
            ];

        // CASE - This error occurs if we're trying to updata a database that has formulas in it that link to other databases that NotionScheduler doesn't have access to
        // It's a limitation in Notion's API, not much we can do about it really
        // So what we should do is display an error for the user?
        // Somehow? 
        // FIXME
        } elseif (Str::of($msg)->contains('Type error with formula')) {

            // Log 
            // Log::error("NotionErrorManager - Origin is $origin_of_error - Database with ID $database_id contains formulas that can't be updated. Marking the DB as invalid and mailing the user.");

            // Write down the error
            $emsg = "Notion's API restricts access to databases containing formulas. In order to enable formula use, you either have to grant NotionScheduler access to the external databases you're referencing, or remove references to external assets.";

            // Update the DB
            self::disableDatabase(
                $database_id, 
                $emsg
            );

            return [
                'type' => 'danger',
                'message' => $emsg,
                'action' => 'none',
            ];


        // CASE - User is trying to add a database that has an internal cover image 
        } elseif (Str::of($msg)->contains("Internal cover image is not supported")) {

            // Log::info("Notion Error Manager - Internal cover image warning");
            return [
                'type' => 'warning',
                'message' => "Notion's API doesn't currently support pages or databases that have internal cover images. Try removing the cover image and trying again.",
                'action' => 'none'
            ];

        // CASE - This happens if someone changes one of the fields that NotionScheduler uses from a "Rich text" to a "Title" for example, so we need to switch it back
        } elseif(Str::of($msg)->contains("Type mismatch between request for property")) {

            Log::warning("Type mismatch error - Dispatching CorrectNotionScaffolding");
            return [
                'action' => 'correct_scaffolding'
            ];

        // CASE - Notion rejected a property schema on write.
        // Since CorrectNotionDatabaseScaffolding now only PATCHes the columns we
        // manage (foreign columns are stripped from the payload), this should only
        // fire for a column *we* generate — i.e. an internal bug, not something the
        // user can fix. So we no longer disable the database or email the user.
        // We log loudly for ourselves and let the job retry.
        } elseif(Str::of($msg)->contains('is not a valid property schema')) {

            $field = Str::of($msg)->before('is not a valid property schema');

            // Log loudly for us — this means one of our managed columns produced an
            // invalid schema, which we need to investigate.
            Log::error("NotionErrorManager - Origin is $origin_of_error - Database with ID $database_id was rejected with an invalid property schema. Offending field: $field");

            // 'none' = stop cleanly, no retry. This rejection is deterministic, so a
            // release (retry in 120s) would loop forever in callers that honor it.
            return [
                'action' => 'none',
            ];


        // CASE - Unmanaged error
        } else {

            if (!empty($msg)) {

                if (isset($error->notionCode)) {

                    $notionCode = Str::of($error->notionCode);

                    if ($notionCode->contains([
                        'service_unavailable',
                        'internal_server_error',
                    ])) {

                        return [
                            'type' => 'warning',
                            'message' => "It looks like Notion's API is currently experiencing some issues. Please try again. If the issue persists, please contact support.",
                            'action' => 'release'
                        ];

                    } elseif ($notionCode->contains([
                        'rate_limited',
                    ])) {

                        sleep(60);
                        return [
                            'type' => 'warning',
                            'message' => "It looks like Notion's API is currently experiencing some issues. Please try again. If the issue persists, please contact support.",
                            'action' => 'release'
                        ];


                    } else {
                        Log::error(278);
                        Log::error($notionCode);
                        Log::error($msg);
                    }

                } else {

                    Log::info("NotionErrorManager - UNHANDLED ERROR - $msg ");
                    Log::info($error);
                }


                Log::info(json_decode(json_encode($error), true));
            } else {
                // Empty error code, but instance of ApiException, this happens occasionally
                if ($error instanceof \Notion\Exceptions\ApiException) {
                    return [
                        'type' => 'warning',
                        'message' => "ERR148 - Notion's API is currently experiencing isues, please try again. If the issue persists, please contact an admin.",
                        'action' => 'release',
                    ];
                } else {
                    Log::info("134");
                    Log::info($error);
                    Log::info(get_class($error));
                    Log::info(gettype($error));
                }  
            }
        }

        Log::warning("NotionErrorManager has a completely unhandled event, we'll display a bogus error for the user");
        Log::info($error);
        Log::info(get_class($error));
        Log::info(gettype($error));
        if (isset($error->notionCode)) {
            Log::info($error->notionCode);
        }
        return [
            'type' => 'warning',
            'message' => "It looks like Notion's API is currently experiencing some issues. Please try again. If the issue persists, please contact support.",
            'action' => 'release'
        ];


    }


    public static function disableDatabase($id, $msg) {
    
        // Disable it
        $do = NotionDatabases::find($id);
        if ($do) {
            $do->error_message = $msg;
            $do->is_valid = 0;
            $do->save();

            // Get the corresponding user
            $user = User::find($do->userid);

            // Send the email
            Mail::to($user->email)
                ->send(new RemovedDatabase(
                    $user->toArray(), 
                    $do->toArray(),
                    $msg
                )
            );
        } else {
            // Log::info("NotionErrorManager - DisableDatabase - Couldn't find DB so couldn't delete, problem treats iself.");
        }
        

    }
    
    
}