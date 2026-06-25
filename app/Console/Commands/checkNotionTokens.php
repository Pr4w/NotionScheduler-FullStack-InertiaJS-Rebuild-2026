<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\NotionAccessTokens;
use App\Models\NotionErrorManager;
use Notion\Notion;
use Notion\Search\Query;

use Illuminate\Support\Facades\Http;

class checkNotionTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-notion-tokens';

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

        // Get all the tokens that weren't scanned in a while
        $interval_hours = 2;

        // Get all the DBs we haven't scanned in a while
        $to_scan = NotionAccessTokens::where('is_active', 1)
            ->where('is_valid', 1)
            ->where('last_check_scan', '<', Carbon::now()->subHours($interval_hours))
            ->limit(10)
            ->get();

        if ($to_scan->count() < 1) {
            echo "There are no Notion Tokens that need to be checked";
            return "There are no Notion Tokens that need to be checked";
        }

        // TODO 
        // FIXME - Move this to it's own job?
        // TODO 
        foreach ($to_scan as $token) {

            Log::withContext([
                'origin' => 'CheckNotionTokens COMMAND (not Job)',
                'token->id' => $token->id,
                'token->token' => $token->token
            ]);

            // Log::debug("Running Notion CheckNotionTokens scan on token $token->id");

            echo "Running a scan on token...";

            try {

                // Try a basic HTTP request
                $req = Http::notion()->withToken($token->token)
                    ->get('users/me');

                // Check if success
                if ($req->successful()) {

                    // Do nothing, we save at the bottom of the script

                } else {

                    $req = $req->json();

                    $msg = data_get($req, 'message');

                    if ($msg) {

                        $msg = Str::of($msg);

                        if (Str::of($msg)->contains('Public API service is temporarily unavailable')) {

                            // Skip to next one
                            continue;

                        } elseif (Str::of($msg)->contains('API token is invalid')) {

                            $token->is_active = false;
                            $token->is_valid = false;

                        } else {


                            Log::info(87);
                            Log::info("CheckNotionTokens - Unhandled error - We won't save the scan");
                            Log::info("Issue while refreshing token with ID " . $token->id);
                            Log::info($msg);
                            Log::info("e");
                            Log::info($e);

                        }

                    } else {

                        Log::info(88);
                        Log::info("CheckNotionTokens - Unhandled error - We won't save the scan");
                        Log::info("Issue while refreshing token with ID " . $token->id);
                        Log::info($msg);
                        Log::info("e");
                        Log::info($req);

                    }


                }


                // Create a Notion object
                // $notion = Notion::create($token->token);
                // $query = Query::all()->filterByPages();
                // $results = $notion->search()->search($query);

            } catch (\Throwable $e) {

                // Make pretty
                $msg = $e->getMessage();

                // Manage cases
                if (Str::of($msg)->contains('is not a valid backing value for enum')) {
                    // Do nothing, let it run through
                    continue;
                } elseif (Str::of($msg)->contains('Public API service is temporarily unavailable')) {
                    // Skip to next one
                    continue;
                
                } else {

                    if (!empty($msg)) {

                        if (Str::of($msg)->contains('API token is invalid')) {

                            $token->is_active = false;
                            $token->is_valid = false;

                        } elseif (Str::of($msg)->contains([
                            'cURL error 56',
                            'cURL error 6',
                            'cURL error 28'
                        ])) {

                            // Skip to next one
                            continue;

                        } else {

                            Log::info(83);
                            Log::info("CheckNotionTokens - Unhandled error - We won't save the scan");
                            Log::info("Issue while refreshing token with ID " . $token->id);
                            Log::info($msg);
                            Log::info("e");
                            Log::info($e);

                        }

                        

                    } else {

                        if ($e instanceof \Notion\Exceptions\ApiException) {
                            Log::info(181);
                            Log::info($e);
                            // Empty message with API excpetion, return and try again
                            continue;
                        } else {
                            Log::info(96);
                            Log::info("CheckNotionTokens - Unhandled error - We won't save the scan");
                            Log::info("Issue while refreshing token with ID " . $token->id);
                            Log::info($msg);
                            Log::info("e");
                            Log::info($e);
                        }

                    }
                }
            }

            // Update it
            $token->last_check_scan = Carbon::now();
            $token->save();

        }

        

        // Return
        return;

    }
}