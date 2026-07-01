<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionSocialAccounts;
use App\Models\NotionSocialAccountsAccessTokens;
use App\Models\User;

use App\Models\SocialManagers\LinkedInTools;

use Pr4w\SocialMetrics\Facades\SocialMetrics;
use Pr4w\SocialMetrics\Support\AccountRef;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

use Carbon\Carbon;

use App\Support\Facades\Cloudinary;

use App\Mail\RemovedSocialAccount;
use Illuminate\Support\Facades\Mail;


use Illuminate\Contracts\Queue\ShouldBeUnique;

class CheckSocialTokens implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionSocialAccounts $account
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
        return $this->account->id;
    }

    public function disableAccount($line) {

        // Log the issue
        Log::withContext([
            'origin' => 'CheckSocialTokens Job',
            'account->id' => $this->account->id,
            'account->account_id' => $this->account->account_id,
            'account_platform' => $this->account->platform,
            'account_name' => $this->account->name
        ]);
        Log::channel('tokens')->warning("CheckSocialTokens $line - Wiping account");

        // Disable the account
        $this->account->is_valid = false;
        $this->account->save();

        // Get the user
        $user = User::find($this->account->userid);

        // Log
        Log::info("CheckSocialTokens - Sending an email to a user #" .$user->id. " with username '" . $user->username . "' about their " . $this->account->platform . "account (" . $this->account->name . ") being disabled...");

        // Email the user
        Mail::to($user->email)
            ->send(new RemovedSocialAccount(
                $user->toArray(),
                $this->account->toArray()
            )
        );

        

    }

    /**
     * Execute the job: run the (unchanged) token validation, then enrich the
     * account with fresh follower counts from the SocialMetrics package. The
     * enrichment is fully isolated (see refreshFollowerCount) so it can never
     * affect token refreshing/management.
     */
    public function handle(): void
    {
        $this->checkTokens();
        $this->refreshFollowerCount();
    }

    /**
     * Best-effort follower-count refresh via Pr4w\SocialMetrics. Runs after the
     * token checks, only for a still-valid account, and swallows every error so
     * it never interferes with the token flow. This is the single source of
     * truth for `followers` now — the per-platform inline updates were migrated
     * here so every network is aligned on the same package.
     */
    protected function refreshFollowerCount(): void
    {
        try {
            $account = $this->account->fresh();

            // Skip if the token checks disabled the account (or it vanished).
            if (! $account || ! $account->is_valid) {
                return;
            }

            // Only platforms the package has a driver for. Twitter/X has none.
            $supported = ['facebook', 'instagram', 'threads', 'linkedin', 'tiktok', 'youtube'];
            if (! in_array($account->platform, $supported, true)) {
                return;
            }

            // Facebook reads page-level stats via the page token; the rest use
            // the account token. YouTube is key-based (needs no token here).
            $tokenRow = $account->access_token;
            $token = $account->platform === 'facebook'
                ? ($tokenRow->access_token_page ?? $tokenRow->access_token ?? null)
                : ($tokenRow->access_token ?? null);

            if ($account->platform !== 'youtube' && ! $token) {
                return;
            }

            $accountId = (string) ($account->account_id ?? $account->id);

            // Pass the native id under every meta key a driver might read; only
            // the matching platform's driver looks at its own key.
            $ref = AccountRef::make($account->platform, $accountId, $token, [
                'ig_user_id' => $account->account_id,
                'page_id' => $account->account_id,
                'threads_user_id' => $account->account_id,
                'channel_id' => $account->account_id,
                'organization_urn' => $account->account_full_identifier,
            ]);

            // fetchAccounts never throws for partial failures — errors land in
            // the result. We only act on a real number coming back.
            $metrics = SocialMetrics::fetchAccounts([$ref])->accounts->first();

            if ($metrics && $metrics->followers !== null) {
                $account->followers = $metrics->followers;
                if ($metrics->following !== null) {
                    $account->followings = $metrics->following;
                }
                if ($metrics->posts !== null) {
                    $account->post_count = $metrics->posts;
                }
                $account->save();
            }
        } catch (\Throwable $e) {
            Log::info('CheckSocialTokens - refreshFollowerCount skipped (non-fatal)', [
                'account_id' => $this->account->id ?? null,
                'platform' => $this->account->platform ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Token validation + management. (Formerly handle(); unchanged in body — its
     * per-platform early returns now return from here, not the whole job.)
     */
    protected function checkTokens(): void
    {

        Log::withContext([
            'origin' => 'CheckSocialTokens Job',
            'account->id' => $this->account->id,
            'account->account_id' => $this->account->account_id,
            'account_platform' => $this->account->platform
        ]);


        // NOTE - This definitely works, it gives you the number of hours since the last scan
        $time_since_last_token_check_scan = Carbon::parse($this->account->last_token_check_scan)->diffInHours(Carbon::now());
        // Log::info("Last token check scan for this occurred $time_since_last_token_check_scan hours ago");


        try {


            /**
             * SECTION - Instagram
             */
            if ($this->account->platform == 'instagram') {

                // Check /me/accounts
                $response = Http::facebook()->get('/me/accounts', [
                    'access_token' => $this->account->access_token->access_token,
                    'fields' => "access_token,name,id,instagram_business_account,picture"
                ]);

                // Check if we actually have data
                $rep = $response->json();

                if (!$response->ok()) {

                    // New version of this script
                    if (!isset($rep['error']['error_subcode'])) {
                        if (!isset($rep['error']['message'])) {
                            Log::info("138 UNHANDLED");
                            Log::info($rep);
                        } else {
                            $msg = $rep['error']['message'];
                            if (Str::of($msg)->contains([
                                'An unexpected error has occurred',
                                'An unknown error has occurred'
                                ])) {
                                $this->release(60);
                            } else {
                                Log::info("CheckSocialTokens - 110 error - UNHANDLED");
                                Log::info($msg);
                            }
                        }
                    } else {
                        if (in_array($rep['error']['error_subcode'], [460, 459, 458, 463, 464, 467, 492])) {
                            $this->disableAccount(114);
                        } else {
                            Log::info("CheckSocialTokens - Unhandled 118");
                            Log::info($rep);
                        }
                    }
                    return;

                    // NOTE - This was the original script
                    // if (isset($rep['error']['error_subcode'])) {
                    //     if (in_array($rep['error']['error_subcode'], [460, 459, 458, 463, 464, 467, 492])) {
                    //         Log::channel('tokens')->info("CheckSOcialTokens 91 - Wiping account");
                    //         $this->account->is_valid = false;
                    //         $this->account->save();
                    //     } else {
                    //         Log::info("CheckSocialTokens - Unhandled 175");
                    //         Log::info($rep);
                    //     }
                    // } else {
                    //     // TODO
                    //     Log::info("CheckSocialTokens Job - Unhandled response 223");
                    //     Log::info($response);
                    //     Log::info($response->json());
                    // }
                    // return;
                } else {

                    

                    if (empty($rep['data'])) {
                        // Response is empty, remove the account
                        $this->disableAccount(174);
                    } else {

                        // Have we found the account
                        $foundAccount = false;

                        // Go through the data
                        foreach ($rep['data'] as $account) {

                            if (isset($account['instagram_business_account']['id'])) {
                                if ($account['instagram_business_account']['id'] == $this->account->account_id) {
                                    $foundAccount = $account;
                                    break;
                                }
                            }
                        }

                        // try {

                        //     if ($this->account->userid == 1) {
                             
                        //         Log::info("CheckSocialTokens Checker 286");
                        //         Log::info([
                        //             'data' => $rep['data'],
                        //             'account' => $this->account
                        //         ]);
                                
                        //     }


                        // } catch (\Throwable $e) {
                        //     Log::info(285);
                        //     Log::info($e);
                        // }
                        

                        // We run this query as well as the previous one because sometimes the previous one won't return an account_id for random reasons, even though we still have access to that account
                        // Lets get some more data on that account
                        $response = Http::facebook()->get($this->account->account_id, [
                            'access_token' => $this->account->access_token->access_token,
                            'fields' => "id,username,profile_picture_url,ig_id"
                        ]); 

                        // Make pretty
                        $rep = $response->json();

                        // if (!$foundAccount) {

                        //     Log::info("225");
                        //     Log::info("Account ID NOT FOUND - Trying to double check anyway just in case...");

                        //     // Log it
                        //     Log::info($rep);

                        // }

                        // Check
                        if (!$response->ok()) {

                            if (isset($rep['error']['message'])) {

                                $rem = $rep['error']['message'];

                                // Error? Restart
                                if (Str::of($rem)->contains("An unknown error occ")) {
                                    // Do nothing, let the script re-run itself?
                                    $this->release(60);
                                    return;

                                // No longer have access
                                } elseif(Str::of($rem)->contains([
                                    'does not exist, cannot be loaded due to missing permissions, or does not support this operation'
                                ])) {

                                    $this->disableAccount(259);
                                    return;

                                } else {
                                    Log::info("CheckSocialTokens221");
                                    Log::info($rep);
                                    $this->release(60);
                                    return;
                                }

                            } else {

                                // TODO
                                Log::info("CheckSocialTokens Job - Unhandled response 285");
                                Log::info($response);
                                Log::info($response->json());
                                $this->release(60);
                                return;

                            }

                            
                        } else {

                            // Mark the account as found
                            $foundAccount = true;

                            // Update the fields on the account
                            if (isset($rep['profile_picture_url'])) {
                                $this->account->profile_picture = $rep['profile_picture_url'];
                            }
                            
                            $this->account->last_token_check_scan = Carbon::now();
                            $this->account->save();

                        }


                        // Check if we've found an account
                        if (!$foundAccount) {
                            // Response is empty, remove the account
                            $this->disableAccount(194);
                        }


                    }

                }

            }

            /**
             * SECTION - Facebook
             */
            if ($this->account->platform == 'facebook') {

                // Log::info("Running a facebook refresh on account " . $this->account->id);

                // Get all the accounts and scopes
                $scopes_received = NotionSocialAccounts::facebookGetAllScopesAndAccounts($this->account->access_token->access_token);

                if (!$scopes_received['success']) {
                    if ($scopes_received['message'] == 'fail_debug_token') {
                        Log::info("CheckSocialTokens - Fail 223");
                    } elseif ($scopes_received['message'] == 'accounts_data_empty') {
                        $this->disableAccount(179);
                    } elseif ($scopes_received['message'] == 'failed_to_get_accounts') {
                        if (!isset($scopes_received['error']['error']['error_subcode'])) {
                            if (!isset($scopes_received['error']['error']['message'])) {
                                Log::info("272 error - UNHANDLED");
                                Log::info($scopes_received);
                            } else {
                                $msg = $scopes_received['error']['error']['message'];
                                if (Str::of($msg)->contains([
                                    'An unexpected error has occurred',
                                    'An unknown error has occurred'
                                ])) {
                                    // Try again in 10 minutes?
                                    $this->release(600);
                                } else {
                                    Log::info("UNHANDLED 195");
                                    Log::info($msg);
                                }
                            }
                        } else {
                            if (in_array($scopes_received['error']['error']['error_subcode'], [460, 459, 458, 463, 464, 467, 492])) {
                                $this->disableAccount(172);
                            } else {
                                Log::info("CheckSocialTokens - Unhandled 175");
                                Log::info($scopes_received);
                            }
                        }
                    } else {
                        Log::info("CheckSocialTokens - Unhandled 173");
                        Log::info($scopes_received);
                    }
                } else {
                    $accounts = $scopes_received['data']['accounts'];
                    $scopes_received = $scopes_received['data']['scopes_received'];

                    // Check to see if the account we're ACTUALLY scanning was somehow removed from the accounts that that token has access to
                    // This happens if a Facebook account has a bunch of tokens, and we happen to be scanning the ONE token of an account that was deleted while the other ones still work
                    if (!isset($accounts[$this->account->account_id])) {
                        // Log::info("TODO - Remove the main account from the DB, all other accounts should be fine?");
                        $this->disableAccount(225);
                    }
                    

                    // Check the received scopes, add the business_management scope if it isn't there?
                    foreach ($scopes_received as $key => $account_with_scopes) {
        
                        // Init
                        $actual_scopes = $account_with_scopes['scopes'];

                        if ($account_with_scopes['platform'] == 'facebook') {
                            $reference_scopes = Config::get('services.facebook.fb_scopes');
                            $scope_platform = 'facebook';
                        }

                        if ($account_with_scopes['platform'] == 'instagram') {
                            $reference_scopes = Config::get('services.facebook.ig_scopes');
                            $scope_platform = 'instagram';
                        }

                        // Compute the difference
                        // CASE - There are some missing scopes
                        if (array_diff($reference_scopes, $actual_scopes)) {

                            $do = NotionSocialAccounts::where('account_id', (string) $key)
                                ->where('is_active', 1)
                                ->where('is_valid', 1)
                                ->get();

                            if ($do->count() > 0) {
                                $do->update([
                                    'is_valid' => false
                                ]);

                                // Check
                                if (isset($accounts[$key])) {
                                    Log::info("CheckSocialTokens - 313 - Dieing account with id $key");
                                } else {
                                    Log::info("CheckSocialTokens - 324 - Dieing account with id $key");
                                }
                            } else {
                                // CASE - This is the case where we can't find an active account that is valid and active that is in the token
                                // So we have a token that has access to some accounts, but in any case that account has missing scopes, so...
                                // Technically we should just do nothing
                                // Log::info("368 CheckSocialTokenJob0");
                                // Log::info([
                                //     'key' => $key,
                                //     'reference_scopes' => $reference_scopes,
                                //     'actual_scopes' => $actual_scopes
                                // ]);
                            }
                            
                        // CASE - The are no missing scopes
                        } else {
                            if (isset($accounts[$key])) {
                                // Get the account
                                $db = NotionSocialAccounts::where('account_id', (string) $accounts[$key]['account_id'])
                                    ->where('is_valid', 1)
                                    ->update([
                                        'name' => $accounts[$key]['name'],
                                        'profile_picture' => $accounts[$key]['profile_picture'],
                                        'last_token_check_scan' => Carbon::now(),
                                        // 'followers' now refreshed via SocialMetrics (see refreshFollowerCount)
                                        'followings' => $accounts[$key]['followings'],
                                        'engagement' => $accounts[$key]['engagement'],
                                        'post_count' => $accounts[$key]['post_count'],
                                ]);
                            } else {
                                $this->disableAccount(366);
                            }
                            
                        }

                    }

                }

            }


            /**
             * SECTION - Twitter
             */
            if ($this->account->platform == "twitter") {


                // Check if token is within 10 minutes of expiring
                $time_to_expire = Carbon::now()->diffInMinutes(Carbon::parse($this->account->access_token->expiry_date));
                $token_validity_in_minutes = 10;
                
                // Check if the token expires in less than 10 minutes, if so, refresh
                if ($time_to_expire < $token_validity_in_minutes) {

                    // Log::info("Refreshing twitter token");

                    $auth = base64_encode(Config::get('services.twitter-oauth-2.client_id') . ':' .Config::get('services.twitter-oauth-2.client_secret'));

                    // Perform query
                    $response = Http::twitter()->asForm()
                        ->withHeaders(
                            [
                                'Authorization' => "Basic $auth"
                            ]
                        )
                        ->post('oauth2/token', [
                            "grant_type" => "refresh_token",
                            "refresh_token" => $this->account->access_token->refresh_token,
                            "client_id" => Config::get('services.twitter-oauth-2.client_id')
                    ]);

                    // Make pretty
                    $rep = $response->json();

                    // Check result
                    if (!$response->ok()) {

                        if (isset($rep['title'])) {
                            if ($rep['title'] == 'Unauthorized') {
                                $this->disableAccount(447);
                                return;
                            } else {
                                Log::info("CheckSocialTokens Job - Twitter - 449 Failed to refresh token of account with id " . $this->account->id. " - This error is unhandled and doesn't do anything, you need to fix this");
                                Log::info($response->json());
                            }
                        } elseif(isset($rep['error_description'])) {
                            $edesc = Str::of($rep['error_description']);
                            if ($edesc->contains('Value passed for the token was invalid')) {
                                $this->disableAccount(490);
                                return;
                            }
                        } else {
                            Log::info("CheckSocialTokens Job - Twitter - 450 Failed to refresh token of account with id " . $this->account->id. " - This error is unhandled and doesn't do anything, you need to fix this");
                            Log::info($response->json());
                        }
                    
                        
                    } else {
                        
                        // Update our model
                        $this->account->access_token->access_token = $rep['access_token'];
                        $this->account->access_token->refresh_token = $rep['refresh_token'];
                        $this->account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                        $this->account->access_token->save();

                    }
                }

                // Perform the actual check
                $response = Http::twitter()->withHeaders(
                    [
                        'Authorization' => "Bearer " . $this->account->access_token->access_token
                    ]
                )->get('users/me', [
                    'user.fields' => 'profile_image_url,username'
                ]);

                // Make pretty
                $rep = $response->json();

                // Check
                if (!$response->successful()) {

                    if (isset($rep['detail'])) {
                        if (Str::of($rep['detail'])->contains('Your account is temporarily locked.')) {
                            $this->disableAccount(576);
                            return;
                        } 
                        if (Str::of($rep['detail'])->contains('Too Many Request')) {
                            Log::info("Twitter gave us a 'Too Many Requests' error, slowing down this single job");
                            $this->release(600);
                            return;
                        }
                        if (Str::of($rep['detail'])->contains('Service Unavailable')) {
                            Log::info("Twitter gave us a 'Service Unavailable' error, retrying later...");
                            $this->release(600);
                            return;
                        }
                    }
                    if (isset($rep['title'])) {
                        if ($rep['title'] == 'Unauthorized') {
                            $this->disableAccount(612);
                            return;
                        }
                    }

                    Log::info("CheckSocialTokens Job - Twitter - Failed to get user data " . $this->account->id . " - This error is unhandled and doesn't do anything, you need to fix this");
                    Log::info($response);
                    Log::info($response->json());
                } else {

                    $uploadedFileUrl = null;
                    if (isset($rep['data']['profile_image_url'])) {
                        $uploadedFileUrl = Cloudinary::uploadFile($rep['data']['profile_image_url'])->getSecurePath();
                    }

                    // Everything looks good
                    $this->account->profile_picture = $uploadedFileUrl;
                    $this->account->last_token_check_scan = Carbon::now();
                    $this->account->save();

                }

            }

            /**
             * SECTION - Threads
             */
            if ($this->account->platform == 'threads') {

                // Refresh the token
                $time_to_expire = Carbon::now()->diffInDays(Carbon::parse($this->account->access_token->expiry_date));
                $days_before_refresh = 5;

                // Token is about to expire, lets refresh it
                if ($time_to_expire < $days_before_refresh) {

                    Log::info("Running a threads token refresh");

                    // Perform query
                    $response = Http::threads()->get('refresh_access_token', [
                        'grant_type' => 'th_refresh_token',
                        'access_token' => $this->account->access_token->access_token
                    ]);

                    // Make pretty
                    $rep = $response->json();

                    // Check
                    if (!$response->successful()) {
                        Log::warning("UNHANDLED - Failed Threads refresh");
                        Log::info($rep);
                        Log::info($response);
                        return;
                    } else {

                        // Log
                        Log::info("Threads token refresh was successful");

                        // Save everything
                        $this->account->access_token->access_token = $rep['access_token'];
                        $this->account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                        $this->account->access_token->save();

                    }

                }

                // Get the basic info about this account
                $response = Http::threads()->get('me', [
                    'fields' => 'id,username,threads_profile_picture_url',
                    'access_token' => $this->account->access_token->access_token
                ]);

                // Make pretty
                $rep = $response->json();

                // Check
                if (!$response->successful()) {

                    $error_message = data_get($rep, 'error.message');

                    if ($error_message) {

                        $error_message = Str::of($error_message);

                        if ($error_message->contains("Error validating access token: The user has not authorized application")) {

                            $this->disableAccount(624);
                            return;

                        } else {

                            Log::warning(675);
                            Log::info($rep);
                            Log::info($response);

                        }

                    } else {

                        Log::warning(637);
                        Log::info($rep);
                        Log::info($response);

                    }

                   
                } else {

                    // Update the data
                    if (isset($rep['threads_profile_picture_url'])) {
                        $this->account->profile_picture = $rep['threads_profile_picture_url'];
                    }
                    $this->account->name = $rep['username'];
                    $this->account->last_token_check_scan = Carbon::now();
                    $this->account->save();

                    // Follower count is refreshed via SocialMetrics after the
                    // token checks (see refreshFollowerCount) — no inline fetch.

                    return;

                }


            }

            /**
             * SECTION - LinkedIn PRO
             */
            if ($this->account->platform == "linkedin") {

                // Refresh the token
                $time_to_expire = Carbon::now()->diffInDays(Carbon::parse($this->account->access_token->expiry_date));
                $days_before_refresh = 5;
                if ($time_to_expire < $days_before_refresh) {

                    // Log::info("Refreshing linkedin token");
                
                    $response = Http::asForm()->post("https://www.linkedin.com/oauth/v2/accessToken", [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->account->access_token->refresh_token,
                        'client_id' => Config::get('services.linkedin.client_id'),
                        'client_secret' => Config::get('services.linkedin.client_secret')
                    ]);
                    $rep = $response->json();

                    // Check
                    if (!$response->successful()) {

                        $stat = $response->status();
                        if ($stat == 429) {
                            Log::info("797 - Error 429  - TODO TODO TODO - Move the other loggers somewhere else");
                            $this->release(600);
                            return;
                        }

                        if (isset($rep['error'])) {
                            $rerr = $rep['error'];

                            if ($rerr == 'invalid_grant') {
                                $this->disableAccount(623);
                                return;
                            }
                        }


                        
                        Log::warning("UNHANDLED - CheckSocialToken - Failed to refresh LinkedIn Page token");
                        Log::info($response->json());
                        Log::info($response);
                        
                    } else {

                        

                        $this->account->access_token->access_token = $rep['access_token'];
                        $this->account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                        $this->account->access_token->refresh_token = $rep['refresh_token'];
                        $this->account->access_token->refresh_token_expiry_date = Carbon::now()->addSeconds($rep['refresh_token_expires_in']);
                        $this->account->access_token->save();
                        
                        // Update all of them in one go, since we may have multiple accounts with a single token
                        $updates = NotionSocialAccounts::where('token_id', $this->account->access_token->id)
                            ->update([
                                'last_token_check_scan' => Carbon::now()
                            ]);

                    }

                } else {

                    if ($time_since_last_token_check_scan < 24 * 5) {
                        return;
                    } else {

                        // We aren't in a case where we need to refresh the token, so lets just see if we can access basic resources
                        $response = LinkedInTools::queryMe($this->account->access_token->access_token);
                        $rep = $response->json();

                        if (!$response->successful()) {
                            if (isset($rep['status'])) {
                                if ($rep['status'] == 500) {
                                    // Error 500, we might want to retry this, so lets just return
                                    return;
                                } else {
                                    if (isset($rep['code'])) {
                                        if (
                                            in_array(
                                                $rep['code'],
                                                [
                                                    'REVOKED_ACCESS_TOKEN',
                                                    'RESTRICTED_MEMBER'
                                                ]
                                            )
                                        ) {
                                            $this->disableAccount(655);
                                            return;
                                        } else {
                                            Log::info("CheckSocialTokens - UNHANDLED 658 - Failed to get Linkedin");
                                            Log::info($rep);
                                        }
                                    } else {
                                        Log::info("CheckSocialTokens - UNHANDLED 661 - Failed to get Linkedin");
                                        Log::info($rep);
                                    }
                                }
                            } else {
                                Log::info("CheckSocialTokens - UNHANDLED 422 - Failed to get Linkedin");
                                Log::info($rep);
                            }
                        } else {

                            // Log::channel('tokens')->info("Checking LinkedIn, updating details...");

                            // Get profile picture
                            $profile_picture = LinkedInTools::getPersonalProfilePictureFromRep($rep);
                            // TODO - Do we want to always update the profile picture? Or only occasionally?
                            if ($profile_picture) {
                                $profile_picture = Cloudinary::uploadFile($profile_picture)->getSecurePath();
                            }

                            // TODO - Upload to cloudinary

                            // Save personal account id
                            $personal_account_id = $rep['id'];

                            // Looks like we're all good on this front, we got the user, so lets update him
                            $update = NotionSocialAccounts::where('account_id', $personal_account_id)->update([
                                'last_token_check_scan' => Carbon::now(),
                                'profile_picture' => $profile_picture
                            ]);

                            // Now get the organizations this user can manage
                            $response = LinkedInTools::queryOrganizations($this->account->access_token->access_token);

                            // Make pretty
                            $rep = $response->json();

                            // Check
                            if (!$response->successful()) {

                                if (isset($rep['status'])) {

                                    $estatus = $rep['status'];

                                    if ($estatus == 500) {
                                        $this->release(60);
                                        return;
                                    }

                                }

                                Log::info("CheckSocialTokens - UNHANDLED 448 - Failed to get Linkedin");
                                Log::info($rep);
                            } else {

                                

                                // TODO - What we want to do now is loop through all of them and add/remove accounts as necessary?
                                // If the user is still an admin for the account then keep it, if not we then remove it
                                // Create an array of organizations that the user is still an admin of?

                                // Create initial array
                                $organizations = [];

                                // Loop through them
                                foreach ($rep['elements'] as $org) {
                                    $org_rep = LinkedInTools::getOrganizationDataFromRep($org, true);
                                    if ($org_rep) {
                                        $organizations[] = $org_rep;
                                    }
                                }

                                // Account ids - Add the personal one as well so we avoid de-activating it by mistake
                                $account_ids = [$personal_account_id];

                                // Check
                                if (count($organizations) > 0) {
                                    // Loop through
                                    foreach ($organizations as $org) {
                                        $update = NotionSocialAccounts::where('account_id', (string) $org['id'])
                                            ->update([
                                                'last_token_check_scan' => Carbon::now(),
                                                'profile_picture' => $org['profile_picture']
                                            ]);

                                        $account_ids[] = (string) $org['id'];
                                    }
                                }

            

                                // Update the ones that are IN the token_id, but are not in the organizations array
                                $update = NotionSocialAccounts::where('token_id', $this->account->access_token->id)
                                    ->whereNotIn('account_id', $account_ids)
                                    ->update([
                                        'is_valid' => 0
                                    ]);


                            }

                        }

                    }

                }
            }


            /**
             * SECTION - TikTok
             */
            if ($this->account->platform == "tiktok") {

                // Refresh the token
                $time_to_expire = Carbon::now()->diffInHours(Carbon::parse($this->account->access_token->expiry_date));
                $hours_before_refresh = 5;

                // Log::channel('tokens')->info("Tiktok time to expire is $time_to_expire hours");
                if ($time_to_expire < $hours_before_refresh) { // FIXME - Add or true to test

                    // Log::channel('tokens')->info("Refreshing tiktok token");
                
                    $response = Http::tiktok()->asForm()->post("oauth/token/", [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->account->access_token->refresh_token,
                        'client_key' => Config::get('services.tiktok.client_id'),
                        'client_secret' => Config::get('services.tiktok.client_secret')
                    ]);

                    // Check
                    if (!$response->successful()) {

                        $status = $response->status();
                        if (
                            in_array($status, [
                                    500,
                                    503
                                ])) 
                            {
                            $this->release(120);
                            return;
                        }

                        Log::info("CheckSocialToken - Failed to refresh Tiktok Page token");
                        Log::info($response->json());
                        Log::info($response->headers());
                        Log::info($response->status());

                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Check
                        if (isset($rep['error'])) {

                            // Make string
                            $emsg = Str::of($rep['error']);
                            $edesc = Str::of($rep['error_description']);

                            // Switch 
                            if ($edesc->contains('Refresh token is invalid or expired')) {
                                $this->disableAccount(814);
                                return;
                            }

                            Log::info(818);
                            Log::info("Check social tokens - UNHANDLED");

                        } else {

                            $this->account->access_token->access_token = $rep['access_token'];
                            $this->account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                            $this->account->access_token->refresh_token = $rep['refresh_token'];
                            $this->account->access_token->refresh_token_expiry_date = Carbon::now()->addSeconds($rep['refresh_expires_in']);
                            $this->account->access_token->save();
                            
                            // Update all of them in one go, since we may have multiple accounts with a single token
                            $updates = NotionSocialAccounts::where('token_id', $this->account->access_token->id)
                                ->update([
                                    'last_token_check_scan' => Carbon::now()
                                ]);

                        }

                    }
                } else {

                    // Log::info("Getting TikTok data - CheckSocialTokens");

                    $response = Http::tiktok()->withToken($this->account->access_token->access_token)->get('user/info/', [
                        'fields' => 'open_id,union_id,avatar_url,display_name,username'
                    ]);

                    if (!$response->successful()) {



                        Log::info("CheckSocialTokens - TikTok Failed...?");
                        Log::info($response);
                        Log::info($response->json());


                        $rep = $response->json();

                        $error_code = data_get($rep, 'error.code');

                        if ($error_code == 'internal_error') {

                            $this->release(60 * $this->attempts());
                            return;

                        } elseif ($error_code == 'scope_not_authorized') {

                            $this->disableAccount(956);
                            return;

                        } else {


                            Log::info("UNHANDLED");


                        }

                    } else {

                        // Make pretty
                        $rep = $response->json()['data']['user'];

                        // Update the data
                        $this->account->profile_picture = $rep['avatar_url'];
                        $this->account->name = $rep['username'];
                        $this->account->last_token_check_scan = Carbon::now();
                        $this->account->save();

                    }
                }

            }



            /**
             * SECTION - YouTube
             */
            if ($this->account->platform == "youtube") {

                /**
                 * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
                 * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
                 * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
                 */

                // Perform query
                $response = Http::post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->account->access_token->refresh_token,
                    'client_id' => Config::get('services.youtube.client_id'),
                    'client_secret' => Config::get('services.youtube.client_secret')
                ]);
                $rep = $response->json();

                // Check response
                if (!$response->successful()) {

                    if (isset($rep['error_description'])) {

                        $emsg = Str::of($rep['error_description']);

                        if ($emsg->contains('Token has been expired or revoked')) {
                            $this->disableAccount(914);
                            return;
                        }

                    }

                    Log::info("CheckSocialToken - Failed to refresh YouTube token - UNHANDLED");
                    Log::info($response);
                    Log::info($rep);
                } else {

                    // Update token
                    $this->account->access_token->access_token = $rep['access_token'];
                    $this->account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                    $this->account->access_token->save();

                    // Update last scan
                    $updates = NotionSocialAccounts::where('token_id', $this->account->access_token->id)
                        ->update([
                            'last_token_check_scan' => Carbon::now()
                        ]);

                }

            }

            /**
             * SECTION - LinkedIn
             * 
             * NOTE - This USED to be the LinkedIn script before we sunset it in favor of the CommunityManagement API that covers both personal & pro accounts
             */
            /*
            if ($this->account->platform == 'linkedin') {

                // Perform a query
                $response = Http::linkedin()->withToken($this->account->access_token->access_token)->get('userinfo');

                // Check
                if (!$response->successful()) {
                    Log::info("CheckSocialTokens Job - LinkedIn - Failed to get user data " . $this->account->id . " - This error is unhandled and doesn't do anything, you need to fix this");
                    Log::info($response);
                    Log::info($response->json());
                } else {

                    // Get all the data
                    $rep = $response->json();

                    $uploadedFileUrl = null;
                    if ($rep['picture']) {
                        $uploadedFileUrl = Cloudinary::uploadFile($rep['picture'])->getSecurePath();
                    }

                    $this->account->profile_picture = $uploadedFileUrl;
                    $this->account->name = $rep['name'];
                    $this->account->last_token_check_scan = Carbon::now();
                    $this->account->save();

                }

            } */

            /**
             * SECTION - Facebook
             */
            if ($this->account->platform == 'facebook_bak') {

                // Debug 
                $response = Http::facebook()->get('/me', [
                    'access_token' => $this->account->access_token->access_token_page,
                    'fields' => 'id,name'
                ]);

                if (!$response->ok()) {
                    // TODO
                    Log::info("CheckSocialTokens Job - Unhandled response 329");
                    Log::info($response);
                    Log::info($response->json());
                } else {

                    // Get an app token
                    $app_token = NotionSocialAccountsAccessTokens::getAppAccessToken();

                    // Log::info($app_token);

                    // Lets debug our token
                    $response = Http::facebook()->get('debug_token', [
                        'input_token' => $this->account->access_token->access_token_page,
                        'access_token' => $app_token
                    ]);

                    // Check
                    if (!$response->ok()) {
                        // TODO
                        Log::info("CheckSocialTokens Job - Unhandled response 341");
                        Log::info($response);
                        Log::info($response->json());
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Log::info($rep);

                        // Check
                        if (empty($rep['data'])) {
                            // Response is empty, remove the account
                            $this->disableAccount(403);
                        } else {

                            // Get the required scopes
                            $required_scopes_base = Config::get('services.facebook.scopes');

                            // Make a pretty array
                            $required_scopes_detailed = [];

                            // Make a pretty array of scpês
                            $ig_scopes = Config::get('services.facebook.ig_scopes');
                            $fb_scopes = Config::get('services.facebook.fb_scopes');
                            $all_scopes = array_merge($ig_scopes, $fb_scopes);
                            foreach ($required_scopes_base as $req_scope) {
                                $plat = 'facebook';
                                if (in_array($req_scope, $ig_scopes)) {
                                    $plat = 'instagram';
                                }
                                $required_scopes_detailed[$req_scope] = $plat;
                            }

                            // Make pretty
                            $scopes = $rep['data']['scopes'];
                            $granular_scopes = $rep['data']['granular_scopes'];

                            // Look at all the scopes that we NEED and compare them to the scopes that we have

                            // Init
                            $found_facebook_scopes = [];
                            $found_instagram_scopes = [];

                            // Loop through all of our granular scopes
                            foreach ($granular_scopes as $granular_scope) {

                                // Make things pretty
                                $scope_name = $granular_scope['scope'];
                                $scope_target_ids = $granular_scope['target_ids'];

                                // Ignore the business_management scope
                                if ($scope_name != 'business_management') {

                                    // Check if the scope IS in the array of target IDs
                                    if (in_array($this->account->account_id, $scope_target_ids)) {

                                        // Look where we might need to add it
                                        if (in_array($scope_name, $ig_scopes)) {
                                            $found_instagram_scopes[] = $scope_name;
                                        }
                                        if (in_array($scope_name, $fb_scopes)) {
                                            $found_facebook_scopes[] = $scope_name;
                                        }
                                    }

                                }
                            }

                            // Check FB scopes
                            $found_facebook_scopes[] = 'business_management';
                            $diff = array_diff($fb_scopes, $found_facebook_scopes);
                            if (count($diff) < 1) {
                                
                                // We have ALL scopes for this FB account, all is well in the world
                                $this->account->last_token_check_scan = Carbon::now();
                                $this->account->save();

                            } else {

                                Log::channel('tokens')->info("CheckSocialTokens Job - Missing FB scopes for user social account with ID " . $this->account->id);
                                Log::channel('tokens')->info($diff);
                            }


                        }

                    }
                    
                }
            }

        

        } catch (\Throwable $e) {

            // Create a string of the error message
            $emsg = Str::of($e->getMessage());

            // Switch the case, see what the error is about
            if ($emsg->contains([
                'cURL error 56',
                'cURL error 18',
                'cURL error 28',
                'cURL error 35',
                'cURL error'
            ])) {
                $this->release(60 * $this->attempts());
                return;
            }



            Log::info("CheckSocialTokens Throwable 1053 end of file - UNHANDLED");
            Log::info($e);
            Log::info($e->getMessage());


        }

    }

    public function failed(?Throwable $exception): void
    {
        // Send user notification of failure, etc...
        Log::withContext([
            'origin' => 'CheckSocialTokens Job',
            'account->id' => $this->account->id,
            'account->account_id' => $this->account->account_id,
            'account_platform' => $this->account->platform
        ]);
        Log::info("Failed job handler");
        Log::info($exception);

    }
}