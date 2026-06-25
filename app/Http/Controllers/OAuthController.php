<?php

namespace App\Http\Controllers;

use App\Jobs\FindNotionPostsInDB;
use App\Jobs\UpdateNotionPostInDatabaseAfterUpload;
use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionPosts;
use App\Models\NotionSocialAccounts;
use App\Models\NotionSocialAccountsAccessTokens;
use App\Models\SocialManagers\LinkedInTools;
use App\Support\Facades\Cloudinary;
use Auth;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Notion\Notion;
use Notion\Pages\Page;
use Notion\Search\Query;

class OAuthController extends Controller
{
    private function stashOAuthReturn(Request $request): void
    {
        $allowed = ['dashboard', 'setup'];
        $target = $request->query('return_to');
        session(['oauth_return' => in_array($target, $allowed, true) ? $target : 'dashboard']);
    }

    private function redirectBackToApp(string $platform, bool $isSuccess, array $errors)
    {
        $base = Config::get('services.frontend.url', 'https://app.notionscheduler.app');
        $return = session('oauth_return', 'dashboard');
        session()->forget('oauth_return');

        $path = $return === 'setup' ? '/app/setup' : '/app/dashboard';

        $query = [
            'oauth_status' => $isSuccess ? 'success' : 'error',
            'oauth_platform' => $platform,
        ];

        if (! $isSuccess && ! empty($errors)) {
            $query['oauth_message'] = $errors[0]['message'] ?? '';
        }

        return redirect($base.$path.'?'.http_build_query($query));
    }

    public function notionAuth(Request $request)
    {
        $this->stashOAuthReturn($request);

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('notion')->redirect()->getTargetUrl(),
        ];
    }

    public function twitterAuth(Request $request)
    {
        $this->stashOAuthReturn($request);

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('twitter-oauth-2')
                ->scopes(Config::get('services.twitter-oauth-2.scopes'))
                ->redirect()->getTargetUrl(),
        ];
    }

    public function youtubeAuth(Request $request)
    {
        $this->stashOAuthReturn($request);
        $parameters = ['access_type' => 'offline', 'prompt' => 'consent select_account'];

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('youtube')
                ->scopes(Config::get('services.youtube.scopes'))
                ->with($parameters)
                ->redirect()->getTargetUrl(),
        ];
    }

    public function threadsAuth(Request $request)
    {
        $this->stashOAuthReturn($request);

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('threads')
                ->scopes(Config::get('services.threads.scopes'))
                ->redirect()->getTargetUrl(),
        ];
    }

    public function linkedinProAuth(Request $request)
    {
        $this->stashOAuthReturn($request);

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('linkedin')
                ->setConfig(
                    new \SocialiteProviders\Manager\Config(
                        Config::get('services.linkedin.client_id'),
                        Config::get('services.linkedin.client_secret'),
                        Config::get('services.linkedin.redirect')
                    )
                )
                ->setScopes(Config::get('services.linkedin.scopes'))
                ->redirect()->getTargetUrl(),
        ];
    }

    public function facebookAuth(Request $request)
    {
        $this->stashOAuthReturn($request);
        $scopes = Config::get('services.facebook.scopes');
        if (Auth::id() == 1) {
            $scopes[] = 'read_insights';
        }

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('facebook')
                ->with(['auth_type' => 'reauthorize'])
                ->scopes($scopes)
                ->redirect()->getTargetUrl(),
        ];
    }

    public function tiktokAuth(Request $request)
    {
        $this->stashOAuthReturn($request);

        return [
            'status' => 'OK',
            'messages' => [],
            'data' => Socialite::driver('tiktok')
                ->scopes(Config::get('services.tiktok.scopes'))
                ->with(['auth_type' => 'rerequest'])
                ->redirect()->getTargetUrl(),
        ];
    }

    /**
     * SECTION - Old code
     */
    private function updateOrCreateAccount($existing, $old)
    {

        // Perform the insert
        $insert = NotionSocialAccounts::updateOrCreate(
            $existing,
            $old
        );

        // Add to DB if unique
        $this->addAccountToDatabaseIfUnique(Auth::id(), $insert);

        // Return
        return $insert;

    }

    private function addAccountToDatabaseIfUnique($userid, $account)
    {

        try {

            // Check to see how many active DBs the user has
            $dbs = NotionDatabases::where('userid', $userid)
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->get();

            // Check
            if ($dbs->count() < 1) {
                // No DBs, do nothing
            } elseif ($dbs->count() > 1) {
                // More than one DB, do nothing
            } else {

                // Get the first one only
                $db = $dbs->first();

                // Assign it
                $account->database_id = $db->id;
                $account->save();

            }

        } catch (\Exception $e) {

            Log::info('OAuthController - addAccountToDatabaseIfUnique');
            Log::info($e);

        }

    }

    private function errorHandler($e, $platform)
    {

        // Generic error
        $vmsg = "There was an issue connecting your $platform account to NotionScheduler, please try again. If the issue persists, please contact an admin.";

        // Clean up
        $msg = $e->getMessage();

        // CASE - Notion
        if ($platform == 'Notion') {

            if ($e instanceof ClientException) {

                $response = $e->getResponse();
                if ($response) {
                    $body = $response->getBody()->getContents();
                    $body = json_decode($body, true);

                    if (isset($body['error_description'])) {
                        $emsg = $body['error_description'];

                        if (Str::of($emsg)->contains('Internal cover image is not supported')) {
                            $vmsg = "Unfortunately, Notion doesn't support Internal Cover Images when using third-party services like NotionScheduler. Try removing the Internal Cover Image and try again";

                        } elseif (Str::of($emsg)->contains('body.code should be a string')) {
                            $vmsg = "It looks like Notion didn't return an authorization code when connecting to your database. Did you refresh the page? Are you on a mobile device? Please try again. If the issue persists, please contact Support.";

                        } elseif (Str::of($emsg)->contains('this code has already been used')) {
                            $vmsg = 'It looks like your Notion authorization code has already been used. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support.';

                        } elseif (Str::of($emsg)->contains('this code has been revoked')) {
                            $vmsg = 'It looks like your Notion authorization code has been revoked. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support.';

                        } else {

                            // Return a generic message and log it
                            $this->logOAuthError(197, $platform, $e);
                            if (isset($body)) {
                                Log::info($body);
                            }

                        }

                    } else {
                        $this->logOAuthError(202, $platform, $e);
                        if (isset($body)) {
                            Log::info($body);
                        }
                    }
                } else {
                    $this->logOAuthError(206, $platform, $e);
                }

            } else {

                if (Str::of($msg)->contains('Internal cover image is not supported')) {
                    $vmsg = "Unfortunately, Notion doesn't support Internal Cover Images when using third-party services like NotionScheduler. Try removing the Internal Cover Image and try again";

                } elseif (Str::of($msg)->contains('body.code should be a string')) {
                    $vmsg = "It looks like Notion didn't return an authorization code when connecting to your database. Did you refresh the page? Are you on a mobile device? Please try again. If the issue persists, please contact Support.";

                } elseif (Str::of($msg)->contains('this code has already been used')) {
                    $vmsg = 'It looks like your Notion authorization code has already been used. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support.';

                } elseif (Str::of($msg)->contains('this code has been revoked')) {
                    $vmsg = 'It looks like your Notion authorization code has been revoked. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support.';

                } else {

                    // Return a generic message and log it
                    $this->logOAuthError(184, $platform, $e);

                }

            }

            // CASE - Twitter
        } elseif ($platform == 'Twitter') {

            // Return a generic message and log it
            $this->logOAuthError(195, $platform, $e);

            // TODO - Error handler

            // CASE - YouTube
        } elseif ($platform == 'YouTube') {

            if ($e instanceof ClientException) {

                try {

                    $response = $e->getResponse();
                    if ($response) {
                        $body = $response->getBody()->getContents();
                        $body = json_decode($body, true);

                        if (isset($body['error'])) {
                            $emsg = $body['error'];

                            // Switch errors
                            if (Str::of($emsg)->contains('invalid_grant')) {
                                $vmsg = 'The Authorization code YouTube sent back has already expired. Did you accidentally refresh the page? Please try again. If the issue persists, please contact an admin.';
                            } else {
                                $this->logOAuthError(224, $platform, $e);
                                Log::info($body);
                            }
                        } else {
                            $this->logOAuthError(228, $platform, $e);
                        }
                    } else {
                        $this->logOAuthError(229, $platform, $e);
                    }

                } catch (\Throwable $x) {
                    $this->logOAuthError(571, $platform, $e);
                }

            } else {

                if (Str::of($msg)->contains('missing_scopes')) {
                    $vmsg = "It looks like you haven't granted the necessary authorizations to NotionScheduler. In order to post on YouTube on your behalf, NotionScheduler requires all the requested authorizations. These can be revoked at any time from within your YouTube account.";

                } elseif (Str::of($msg)->contains('err_access_denied')) {
                    $vmsg = 'Access was denied to that YouTube account. Did you not grant the necessary scopes / cancel out of the process? Please try again';

                } elseif (Str::of($msg)->contains('missing_youtube_id')) {
                    $vmsg = "It looks like you didn't select a YouTube account and / or provide all the necessary authorizations to NotionScheduler.  In order to post on YouTube on your behalf, NotionScheduler requires all the requested authorizations. These can be revoked at any time from within your YouTube account.";

                } else {
                    $this->logOAuthError(213, $platform, $e);
                }
            }

            // CASE - Threads
        } elseif ($platform == 'Threads') {

            if (Str::of($msg)->contains('req_timeout')) {
                $vmsg = 'It looks like your request timed out. Please try again. If the issue persists, please contact an admin.';
            } elseif (Str::of($msg)->contains('long_live_issue')) {
                $vmsg = 'There was an issue obtaining your long-lived access token. If the issue persists, please contact an admin.';
            } else {
                $this->logOAuthError(253, $platform, $e);
            }

            // CASE - LinkedIn
        } elseif ($platform == 'LinkedIn') {

            if (Str::of($msg)->contains('oauth_issue')) {
                $vmsg = 'There was an issue obtaining your LinkedIn profile from OAUTH - If the issue persists, please contact an admin.';
            } elseif (Str::of($msg)->contains('oauth_queryme')) {
                $vmsg = 'There was an issue obtaining your LinkedIn account information from OAUTH - If the issue persists, please contact an admin.';
            } elseif (Str::of($msg)->contains('resource_level_throttle')) {
                $vmsg = "It looks like you've tried this action too many times, LinkedIn throttles the number of times you can access this resource. Please try again in 24 hours.";
            } else {
                $this->logOAuthError(263, $platform, $e);
            }

            // CASE - Facebook
        } elseif ($platform == 'Facebook') {

            if ($e instanceof ClientException) {

                try {

                    Log::error('Facebook API Exception: '.$e->getMessage());

                    $response = $e->getResponse();
                    if ($response) {
                        $body = $response->getBody()->getContents();
                        $body = json_decode($body, true);
                        Log::info($body);

                        if (isset($body['error']['message'])) {
                            $emsg = $body['error']['message'];

                            // Switch errors
                            if (Str::of($emsg)->contains('Missing authorization code')) {
                                $vmsg = 'The authorization code is missing from Facebook. Did you manually refresh the page or go through a mobile browser? Please try again. If the issue persists, please contact an admin via the support page';

                            } elseif (Str::of($emsg)->contains('This authorization code has expired')) {
                                $vmsg = 'The authorization code has expired. Did you manually refresh the page? Please try again. If the issue persists, please contact an admin via the support page';

                            } else {
                                $this->logOAuthError(297, $platform, $e);
                            }
                        }
                    } else {
                        $this->logOAuthError(301, $platform, $e);
                    }

                } catch (\Throwable $x) {
                    $this->logOAuthError(305, $platform, $e);
                }

            } else {

                if (Str::of($msg)->contains('generic_facebook_unhandled')) {
                    $vmsg = 'There was an issue generating your Facebook token. Admins have been notified and will look into it shortly.';
                } elseif (Str::of($msg)->contains('fail_debug_token')) {
                    $vmsg = 'There was an issue checking the generated Facebook token. Admins have been notified.';
                } elseif (Str::of($msg)->contains('accounts_data_empty')) {
                    $vmsg = "It looks like you didn't select any accounts to add to NotionScheduler.";
                } elseif (Str::of($msg)->contains('no_accounts_added')) {
                    $vmsg = "Facebook didn't return any accounts associated with NotionScheduler. Are you sure that you granted access to any account?";
                } else {
                    $this->logOAuthError(287, $platform, $e);
                }

            }

        } else {

            Log::warning('MAJOR ERROR IN OAUTHCONTROLLER ERROR HANDLER');
            Log::warning('MAJOR ERROR IN OAUTHCONTROLLER ERROR HANDLER');
            Log::warning('MAJOR ERROR IN OAUTHCONTROLLER ERROR HANDLER');
            Log::warning('MAJOR ERROR IN OAUTHCONTROLLER ERROR HANDLER');
            Log::warning("Platform doesn't exist: $platform");

        }

        try {
            Log::info("Your OAUTHController ErrorHandler is active and displaying errors. Currently displaying the following error for our user #######  $vmsg", [$e]);
        } catch (\Exception $ex) {
            Log::info($ex);
        }

        return [
            'type' => 'warning',
            'message' => $vmsg,
        ];

    }

    private function logOAuthError($line, $platform, $e)
    {
        Log::warning($line);
        Log::warning("OAuthController - $platform - UNHANDLED ERROR: ".$e->getMessage());
        Log::warning($e);
    }

    public function closeWindow()
    {
        // echo "<script>window.close();</script>";
        exit();
    }

    // public function notionAuth() {

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('notion')->redirect()->getTargetUrl()
    //     ];

    // }

    public function handleNotionCallback()
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'Notion';

        // Start
        try {
            // try {
            $user = Socialite::driver('notion')->stateless()->user(); // FIXME - Moved this to stateless because why not
            // } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            //     $user = Socialite::driver('notion')->stateless()->user();

            // } catch (\Throwable $e) {

            // if ($e instanceof \GuzzleHttp\Exception\ClientException) {

            //     $response = $e->getResponse();
            //     if ($response) {
            //         $body = $response->getBody()->getContents();
            //         $body = json_decode($body, true);

            //         if (isset($body['error_description'])) {
            //             $emsg = $body['error_description'];

            //             throw new \Exception($emsg);

            //         } else {
            //             Log::info("152 NO EMSG");
            //         }
            //     }

            //     Log::info("UNHANDLED OauthController 141");
            //     Log::info($e);
            //     if (isset($body)) {
            //         Log::info($body);
            //     }

            // } else {

            //     Log::info("OAuthController 99");
            //     Log::info($e);

            // }

            // // Throw generic error
            // throw new \Exception("There was an issue connecting your Notion account to NotionScheduler, please try again. If the issue persists, please contact an admin.");

            // }

            // Calculate expiry
            if ($user->expiresIn) {
                $expiry_date = Carbon::now()->addSeconds($user->expiresIn);
            } else {
                $expiry_date = Carbon::now()->addYears(10);
            }

            // Add to DB
            $insert = NotionAccessTokens::updateOrCreate(
                [
                    'userid' => Auth::id(),
                    'notion_user_id' => $user->id,
                    'workspace_id' => $user->user['workspace_id'],
                ],
                [
                    'nickname' => $user->nickname,
                    'token' => $user->token,
                    'expiry_date' => $expiry_date,
                    'is_valid' => 1,
                    'is_active' => 1,
                ]
            );

            // // Get the corresponding pages that we can use
            $notion = Notion::create($user->token);

            // Clean up databases?
            $query = Query::all()->filterByDatabases();

            $results = $notion->search()->search($query);

            // Check
            $databases_to_keep = [];
            if ($results->results) {

                foreach ($results->results as $result) {

                    $databases_to_keep[] = $result->id;

                }

            }

            // Perform clean
            $do = NotionDatabases::where('userid', Auth::id())
                ->whereNotIn('database_id', $databases_to_keep)
                ->update(
                    [
                        'is_valid' => 0,
                        'is_active' => 0,
                    ]
                );

            // All is good
            $view_is_success = true;

        } catch (\Exception $e) {

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // $msg = $e->getMessage();

            // if (Str::of($msg)->contains('Internal cover image is not supported')) {
            //     $view_errors[] = [
            //         'type' => 'warning',
            //         'message' => "Unfortunately, Notion doesn't support Internal Cover Images when using third-party services like NotionScheduler. Try removing the Internal Cover Image and try again"
            //     ];
            // } elseif (Str::of($emsg)->contains('body.code should be a string')) {
            //     $view_errors[] = [
            //         'type' => 'warning',
            //         'message' => "It looks like Notion didn't return an authorization code when connecting to your database. Did you refresh the page? Are you on a mobile device? Please try again. If the issue persists, please contact Support."
            //     ];
            // } elseif (Str::of($emsg)->contains('this code has already been used')) {
            //     $view_errors[] = [
            //         'type' => 'warning',
            //         'message' => "It looks like your Notion authorization code has already been used. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support."
            //     ];
            // } elseif (Str::of($emsg)->contains('this code has been revoked')) {
            //     $view_errors[] = [
            //         'type' => 'warning',
            //         'message' => "It looks like your Notion authorization code has been revoked. Did you accidentally refresh the page? Please try again. If the issue persists, please contact support."
            //     ];
            // } else {
            //     Log::warning(221);
            //     Log::warning("OAuthController - $view_platform - UNHANDLED ERROR: " . $e->getMessage());
            //     Log::warning($e);
            //     $view_errors[] = [
            //         'type' => 'warning',
            //         'message' => $msg
            //     ];
            // }

        }

        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    // public function twitterAuth() {

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('twitter-oauth-2')
    //             ->scopes(Config::get('services.twitter-oauth-2.scopes'))
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleTwitterCallback()
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'Twitter';

        try {

            try {
                $user = Socialite::driver('twitter-oauth-2')->user();
            } catch (InvalidStateException $e) {
                $user = Socialite::driver('twitter-oauth-2')->stateless()->user();
            } catch (\Throwable $e) {
                Log::info(260);
                Log::info($e);
                throw new \Exception('It looks like your request timed out. Please try again. If the issue persists, please contact an admin.');
            }

            $uploadedFileUrl = null;
            if ($user->avatar) {
                $uploadedFileUrl = Cloudinary::uploadFile($user->avatar)->getSecurePath();
            }

            // Insert into DB
            $insert = new NotionSocialAccountsAccessTokens;
            $insert->platform = 'twitter';
            $insert->userid = Auth::id();
            $insert->access_token = $user->token;
            $insert->refresh_token = $user->refreshToken;
            $insert->expiry_date = Carbon::now()->addSeconds($user->expiresIn);
            $insert->save();

            // Add user to DB
            // $account = NotionSocialAccounts::updateOrCreate(
            $account = $this->updateOrCreateAccount(
                [
                    'account_id' => $user->id,
                    'userid' => Auth::id(),
                    'platform' => 'twitter',
                ],
                [
                    'token_id' => $insert->id,
                    'name' => $user->nickname,
                    'profile_picture' => $uploadedFileUrl,
                    'is_active' => 1,
                    'is_valid' => 1,
                ]
            );

            // Set success
            $view_is_success = true;

        } catch (\Exception $e) {

            // Set the errors
            // $view_errors[] = [
            //     'type' => 'warning',
            //     'message' => $e->getMessage()
            // ];

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // Warn
            // Log::warning("OAuthController - $view_platform - " . $e->getMessage());
            // Log::warning($e);
        }

        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    // public function youtubeAuth() {

    //     $parameters = ['access_type' => 'offline', "prompt" => "consent select_account"];

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('youtube')
    //             ->scopes(Config::get('services.youtube.scopes'))
    //             ->with($parameters)
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleYoutubeCallback(Request $request)
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'YouTube';

        Log::withContext([
            'Controller' => 'OAUthController',
            'Function' => 'HandleYoutubeCallback',
        ]);

        try {

            /**
             * TODO -
             * - Verify scopes?
             * App Access Token - 7975341742531137|WVaC8XMPDw2Bpj1QtCQRdGwHKuU
             */
            try {
                $user = Socialite::driver('youtube')->user();
            } catch (InvalidStateException $e) {
                $user = Socialite::driver('youtube')->stateless()->user();
            } catch (\Throwable $e) {
                if ($request->error) {
                    if ($request->error == 'access_denied') {
                        throw new \Exception('err_access_denied');
                    }
                    Log::info($request->error);
                }
                Log::info(365);
                Log::info($e);
                throw new \Exception("It looks like your request timed out / you didn't authorize your YouTube account. Please try again. If the issue persists, please contact an admin.");
            }

            // Check if the ID is null first
            if (! $user->id or empty($user->id) or is_null($user->id)) {
                Log::info('Empty YouTube ID');
                Log::info(print_r($user, true));
                throw new \Exception('missing_youtube_id');
            }

            Log::info(print_r($user, true));

            // Check if we have the required scopes
            if (! isset($user->accessTokenResponseBody)) {
                Log::error(372);
                Log::error($user);
                throw new \Exception('There was an issue with your request, please try again');
            } else {
                // Get the scopes
                $scopes = $user->accessTokenResponseBody['scope'];

                // Turn it into an array
                $scopes = explode(' ', $scopes);

                // Check all of our scopes
                $actual_scopes = Config::get('services.youtube.scopes');
                foreach ($actual_scopes as $ac_scope) {
                    if (! in_array($ac_scope, $scopes)) {
                        // throw new \Exception("It looks like you haven't granted the necessary authorizations to NotionScheduler. In order to post on YouTube on your behalf, NotionScheduler requires all the requested authorizations. These can be revoked at any time from within your YouTube account.");
                        throw new \Exception('missing_scopes');
                    }
                }

                // Insert into DB
                $insert = new NotionSocialAccountsAccessTokens;
                $insert->platform = 'youtube';
                $insert->userid = Auth::id();
                $insert->access_token = $user->token;
                $insert->expiry_date = Carbon::now()->addSeconds($user->expiresIn);
                $insert->refresh_token = $user->refreshToken;
                $insert->save();

                // Add user to DB
                // $account = NotionSocialAccounts::updateOrCreate(
                $account = $this->updateOrCreateAccount(
                    [
                        'account_id' => $user->id,
                        'userid' => Auth::id(),
                        'platform' => 'youtube',
                    ],
                    [
                        'token_id' => $insert->id,
                        'name' => $user->nickname,
                        'profile_picture' => $user->avatar,
                        'is_active' => 1,
                        'is_valid' => 1,
                    ]
                );

                // Set success
                $view_is_success = true;

            }

        } catch (\Exception $e) {

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // Set Generic error
            // $verror = [
            //     'type' => 'warning',
            //     'message' => "There was an error getting your access token, did you manually refresh this page? If not, please try adding your YouTube account again."
            // ];

            // try {

            //     Log::error('YouTube API Exception: ' . $e->getMessage());

            //     $response = $e->getResponse();
            //     if ($response) {
            //         $body = $response->getBody()->getContents();
            //         $body = json_decode($body, true);

            //         if (isset($body['error'])) {
            //             $emsg = $body['error'];

            //             // Switch errors
            //             if (Str::of($emsg)->contains('invalid_grant')) {
            //                 $verror = [
            //                     'type' => 'warning',
            //                     'message' => "The Authorization code YouTube sent back has already expired. Did you accidentally refresh the page? Please try again. If the issue persists, please contact an admin."
            //                 ];
            //             } else {
            //                 Log::warning("OAuthController 562 - Unhandled");
            //                 Log::info($body);
            //             }
            //         }
            //     } else {
            //         Log::error("UNHANDLED 567");
            //     }

            // } catch (\Throwable $x) {
            //     Log::info(571);
            //     Log::info($x);
            // }

            // // FIXME - Change this?
            // $view_errors[] = $verror;

            // Warn
            // Log::warning("OAuthController - $view_platform - " . $e->getMessage());
            // Log::info($e);
        }

        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    // public function threadsAuth() {

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('threads')
    //             ->scopes(Config::get('services.threads.scopes'))
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleThreadsCallback(Request $request)
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'Threads';

        Log::withContext([
            'Controller' => 'OAUthController',
            'Function' => 'handleThreadsCallback',
        ]);

        try {

            /**
             * TODO -
             * - Verify scopes?
             * App Access Token - 7975341742531137|WVaC8XMPDw2Bpj1QtCQRdGwHKuU
             */
            try {
                $user = Socialite::driver('threads')->user();
            } catch (InvalidStateException $e) {
                $user = Socialite::driver('threads')->stateless()->user();
            } catch (\Throwable $e) {
                Log::info(365);
                Log::info($e);
                // throw new \Exception("It looks like your request timed out. Please try again. If the issue persists, please contact an admin.");
                throw new \Exception('req_timeout');
            }

            // Get a long lived access token
            $response = Http::get('https://graph.threads.net/access_token', [
                'client_id' => Config::get('services.threads.client_id'),
                'client_secret' => Config::get('services.threads.client_secret'),
                'grant_type' => 'th_exchange_token',
                'access_token' => $user->token,
            ]);
            $rep = $response->json();

            if (! $response->successful()) {
                Log::info(310);
                Log::info($rep);
                Log::info($response);
                throw new \Exception('long_live_issue');
                // throw new \Exception("There was an issue obtaining your long-lived access token. If the issue persists, please contact an admin.");
            }

            // Get some details about the user
            $followers = null;
            $details = Http::threads()->get('me/threads_insights', [
                'metric' => 'followers_count',
                'access_token' => $rep['access_token'],
            ]);
            $drep = $details->json();
            if ($details->successful()) {
                foreach ($drep['data'] as $account_data) {
                    if ($account_data['name'] == 'followers_count') {
                        $followers = $account_data['total_value']['value'];
                    }
                }
            }

            // Insert into DB
            $insert = new NotionSocialAccountsAccessTokens;
            $insert->platform = 'threads';
            $insert->userid = Auth::id();
            $insert->access_token = $rep['access_token'];
            $insert->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
            $insert->save();

            // Add user to DB
            // $account = NotionSocialAccounts::updateOrCreate(
            $account = $this->updateOrCreateAccount(
                [
                    'account_id' => $user->id,
                    'userid' => Auth::id(),
                    'platform' => 'threads',
                ],
                [
                    'token_id' => $insert->id,
                    'name' => $user->nickname,
                    'profile_picture' => $user->avatar,
                    'is_active' => 1,
                    'is_valid' => 1,
                    'followers' => $followers,
                ]
            );

            // Set success
            $view_is_success = true;

        } catch (\Exception $e) {

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // // Set the errors
            // $view_errors[] = [
            //     'type' => 'warning',
            //     'message' => $e->getMessage()
            // ];

            // // Warn
            // Log::warning("OAuthController - $view_platform - " . $e->getMessage());
            // Log::info($e);
        }

        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    public function handleThreadsWebhook(Request $request)
    {
        Log::info('OAuthController 352');
        Log::info($request);
    }

    public function exchangeThreadsAccessToken(string $accessToken): string
    {

        $response = Http::accept('application/json')
            ->post('https://graph.threads.net/access_token', [
                'client_id' => Config::get('services.threads.client_id'),
                'client_secret' => Config::get('services.threads.client_secret'),
                'code' => $accessToken,
                'grant_type' => 'authorization_code',
            ],
            );

        $response = json_decode((string) $response->getBody(), true);

        Log::info($response);

        return $response['access_token'];
    }

    public function refreshThreadsAccessToken(string $accessToken): string
    {
        $response = Http::post('https://graph.threads.net/refresh_access_token', [
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            RequestOptions::FORM_PARAMS => [
                'access_token' => $accessToken,
                'grant_type' => 'th_refresh_token',
            ],
        ]);

        $response = json_decode((string) $response->getBody(), true);

        return $response['access_token'];
    }

    // public function linkedinProAuth() {

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('linkedin')
    //             ->setConfig(
    //                 new \SocialiteProviders\Manager\Config(
    //                     Config::get('services.linkedin.client_id'),
    //                     Config::get('services.linkedin.client_secret'),
    //                     Config::get('services.linkedin.redirect')
    //                 )
    //             )
    //             ->setScopes(Config::get('services.linkedin.scopes'))
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleLinkedinproCallback(Request $request)
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'LinkedIn';

        // Create an array
        $organizations = [];

        try {

            $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'client_id' => Config::get('services.linkedin.client_id'),
                'client_secret' => Config::get('services.linkedin.client_secret'),
                'redirect_uri' => Config::get('services.linkedin.redirect'),
            ]);

            if (! $response->successful()) {
                Log::info('OAUTHController - 294 - Failed to get Linkedin');
                Log::info($response->json());
                throw new \Exception('oauth_issue');
                // throw new \Exception("There was an issue obtaining your LinkedIn profile from OAUTH - If the issue persists, please contact an admin.");
            }

            // Make pretty
            $rep = $response->json();

            // Elements received
            $access_token = $rep['access_token'];
            $expiresIn = $rep['expires_in'];
            $refresh_token = $rep['refresh_token'];
            $refresh_token_expiresIn = $rep['refresh_token_expires_in'];

            // Make a query to ME
            $response = LinkedInTools::queryMe($access_token);

            if (! $response->successful()) {
                Log::info('OAUTHController - 312 - Failed to get Linkedin');
                Log::info($response->json());
                throw new \Exception('oauth_queryme');
                // throw new \Exception("There was an issue obtaining your LinkedIn account information from OAUTH - If the issue persists, please contact an admin.");
            }
            $rep = $response->json();

            // Get the profile picture
            $profile_picture = LinkedInTools::getPersonalProfilePictureFromRep($rep);
            if ($profile_picture) {
                $profile_picture = Cloudinary::uploadFile($profile_picture)->getSecurePath();
            }

            // Ad the user to the organisation
            $organizations[] = [
                'name' => $rep['localizedFirstName'].' '.$rep['localizedLastName'],
                'id' => $rep['id'],
                'full_id' => 'urn:li:person:'.$rep['id'],
                'profile_picture' => $profile_picture,
            ];

            // Now get the organizations this user can manage
            $response = LinkedInTools::queryOrganizations($access_token);

            if (! $response->successful()) {
                $this->handleLinkedinErrorMessages(
                    $response,
                    'There was an issue obtaining your LinkedIn organization authorizations, the issue has been logged and will be looked into.'
                );
            }

            // Make pretty
            $rep = $response->json();

            // Check if array is well formed
            if (! isset($rep['elements'])) {
                // NOTE - Removing this as the LinkedIn PRO is becoming the main source of truth for everything, replacing linkedin classic
                // throw new \Exception("It looks like you're not managing any LinkedIn pages.");
            } else {

                // Loop through them
                foreach ($rep['elements'] as $org) {
                    $org_rep = LinkedInTools::getOrganizationDataFromRep($org);
                    if ($org_rep) {
                        $organizations[] = $org_rep;
                    }
                }
            }

            if (count($rep['elements']) < 1) {
                // NOTE - Removing this as the LinkedIn PRO is becoming the main source of truth for everything, replacing linkedin classic
                // throw new \Exception("It looks like you're not managing any LinkedIn pages.");
            }

            // Check
            if (count($organizations) < 1) {
                // throw new \Exception("It looks like you're not an admin / content admin on any of the LinkedIn pages we found associated with your account");
                // NOTE - Removing this as the LinkedIn PRO is becoming the main source of truth for everything, replacing linkedin classic
            }

            // Looks like we have access to some stuff, so lets go
            $token = new NotionSocialAccountsAccessTokens;
            $token->platform = 'linkedin';
            $token->userid = Auth::id();
            $token->access_token = $access_token;
            $token->expiry_date = Carbon::now()->addSeconds($expiresIn);
            $token->refresh_token = $refresh_token;
            $token->refresh_token_expiry_date = Carbon::now()->addSeconds($refresh_token_expiresIn);
            $token->save();

            // Set success
            $view_is_success = true;

            // Add each org
            foreach ($organizations as $org) {

                // $new = NotionSocialAccounts::updateOrCreate(
                $new = $this->updateOrCreateAccount(
                    [
                        'account_id' => $org['id'],
                        'account_full_identifier' => $org['full_id'],
                        'platform' => $token->platform,
                        'userid' => Auth::id(),
                    ],
                    [
                        'token_id' => $token->id,
                        'name' => $org['name'],
                        'profile_picture' => $org['profile_picture'],
                        'is_active' => 1,
                        'is_valid' => 1,
                    ]
                );
            }

        } catch (\Exception $e) {

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // Set the errors
            // $view_errors[] = [
            //     'type' => 'warning',
            //     'message' => $e->getMessage()
            // ];

            // // Warn
            // Log::warning("OAuthController - $view_platform - " . $e->getMessage());
            // Log::warning($e);
            // Log::warning(json_encode($e));

        }

        // Return
        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    // public function facebookAuth() {

    //     $scopes = Config::get('services.facebook.scopes');

    //     If (Auth::id() == 1) {
    //         $scopes[] = 'read_insights';
    //     }

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('facebook')
    //             ->with(['auth_type' => 'reauthorize'])
    //             // ->reRequest()
    //             ->scopes($scopes)
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleFacebookCallback()
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'Facebook';

        try {

            // Get the data from Socialite
            try {
                $user = Socialite::driver('facebook')->user();
            } catch (InvalidStateException $e) {
                $user = Socialite::driver('facebook')->stateless()->user();
            }

            if (Auth::user()->isAdmin()) {
                dump($user);
            }

            // Get the long-term access token
            $response = Http::facebook()->get('oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => Config::get('services.facebook.client_id'),
                'client_secret' => Config::get('services.facebook.client_secret'),
                'fb_exchange_token' => $user->token,
            ]);

            if (! $response->ok()) {
                $this->handleFacebookErrorMessages($response);
                // $view_errors[] = [
                //     'type' => 'warning',
                //     'message' => "There was an issue generating your Facebook token. Admins have been notified and will look into it shortly."
                // ];
            } else {

                // Make pretty
                $user_token_response = $response->json();
                $access_token = $user_token_response['access_token'];

                // Get all the accounts and scopes
                $scopes_received = NotionSocialAccounts::facebookGetAllScopesAndAccounts($access_token);

                if (! $scopes_received['success']) {

                    if ($scopes_received['message'] == 'fail_debug_token') {
                        throw new \Exception('fail_debug_token');
                        // $view_errors[] = [
                        //     'type' => 'warning',
                        //     'message' => "There was an issue checking the generated Facebook token. Admins have been notified."
                        // ];
                    } elseif ($scopes_received['message'] == 'accounts_data_empty') {
                        throw new \Exception('accounts_data_empty');
                        // $view_errors[] = [
                        //     'type' => 'warning',
                        //     'message' => "It looks like you didn't select any accounts to add to NotionScheduler."
                        // ];
                    }
                } else {

                    $accounts = $scopes_received['data']['accounts'];
                    $scopes_received = $scopes_received['data']['scopes_received'];

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
                        if (array_diff($reference_scopes, $actual_scopes)) {

                            // Don't do anything, we're missing scopes for that account

                        } else {

                            if (isset($accounts[$key])) {
                                $accounts_to_add[] = $accounts[$key];
                            }

                        }
                    }

                    // Run a check
                    if (count($accounts_to_add) < 1) {
                        throw new \Exception('no_accounts_added');
                        // $view_errors[] = [
                        //     'type' => 'warning',
                        //     'message' => "Facebook didn't return any accounts associated with NotionScheduler. Are you sure that you granted access to any account?"
                        // ];
                    } else {

                        // Add all of our tokens
                        foreach ($accounts_to_add as $account) {

                            // Add the Facebook account Token
                            $insert_token = new NotionSocialAccountsAccessTokens;
                            $insert_token->platform = $account['platform'];
                            $insert_token->userid = Auth::id();
                            $insert_token->access_token = $access_token;
                            if (isset($account['page_access_token'])) {
                                $insert_token->access_token_page = $account['page_access_token'];
                            }

                            // Check if expiresIn
                            if ($user->expiresIn) {
                                $insert_token->expiry_date = Carbon::now()->addSeconds($user->expiresIn);
                            } else {
                                $insert_token->expiry_date = Carbon::now()->addYears(10);
                            }
                            $insert_token->save();

                            // Add the Account
                            // $do = NotionSocialAccounts::updateOrCreate(
                            $do = $this->updateOrCreateAccount(
                                [
                                    'account_id' => $account['account_id'],
                                    'userid' => Auth::id(),
                                    'platform' => $account['platform'],
                                ],
                                [
                                    'token_id' => $insert_token->id,
                                    'name' => $account['name'],
                                    'profile_picture' => $account['profile_picture'],
                                    'is_active' => 1,
                                    'is_valid' => 1,
                                    'followers' => $account['followers'],
                                    'followings' => $account['followings'],
                                    'engagement' => $account['engagement'],
                                    'post_count' => $account['post_count'],
                                ]
                            );

                            // Set success
                            $view_is_success = true;

                        }

                    }

                }
            }
            // } catch (\GuzzleHttp\Exception\ClientException $e) {

            //     $verror = [
            //         'type' => 'warning',
            //         'message' => "There was an error getting your access token, did you manually refresh this page? If not, please try adding your Facebook / Instagram account again."
            //     ];

            //     try {

            //         Log::error('Facebook API Exception: ' . $e->getMessage());

            //         $response = $e->getResponse();
            //         if ($response) {
            //             $body = $response->getBody()->getContents();
            //             $body = json_decode($body, true);
            //             Log::info($body);

            //             if (isset($body['error']['message'])) {
            //                 $emsg = $body['error']['message'];

            //                 // Switch errors
            //                 if (Str::of($emsg)->contains('Missing authorization code')) {
            //                     $verror = [
            //                         'type' => 'warning',
            //                         'message' => "The authorization code is missing from Facebook. Did you manually refresh the page or go through a mobile browser? Please try again. If the issue persists, please contact an admin via the support page"
            //                     ];
            //                 } elseif (Str::of($emsg)->contains('This authorization code has expired')) {
            //                     $verror = [
            //                         'type' => 'warning',
            //                         'message' => "The authorization code has expired. Did you manually refresh the page? Please try again. If the issue persists, please contact an admin via the support page"
            //                     ];
            //                 } else {
            //                     Log::warning("OAuthController 1176 - Unhandled");
            //                     Log::info($body);
            //                 }
            //             }
            //         } else {
            //             Log::error("UNHANDLED 1194");
            //         }

            //     } catch (\Throwable $x) {
            //         Log::info(963);
            //         Log::info($x);
            //     }

            //     // FIXME - Change this?
            //     $view_errors[] = $verror;

        } catch (\Exception $e) {

            $view_errors[] = $this->errorHandler($e, $view_platform);

            // // Set the errors
            // $view_errors[] = [
            //     'type' => 'warning',
            //     'message' => $e->getMessage()
            // ];

            // // Warn
            // Log::warning("OAuthController - $view_platform - " . $e->getMessage());
            // Log::warning($e);
            // Log::warning(json_encode($e));

        }

        // Return
        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    public function handleFacebookErrorMessages($query_response)
    {

        // Lets make the query pretty
        $response = $query_response->json();

        // Check if there is an actual error message
        if (! isset($response['error']['error_user_msg'])) {

            Log::warning('OAuthController - HandleFacebookErrorMessages - Unhandled error - ');
            Log::warning(json_encode($response));

        } else {

            Log::warning('OAuthController - HandleFacebookErrorMessages - One of our users encountered an error when uploading - '.$response['error']['error_user_msg']);

        }

        throw new \Exception('generic_facebook_unhandled');
    }

    public function handleFacebookWebhook(Request $request)
    {

        $tokens = [
            'permissions' => 'EAAirLhZB6zpQBO7xVHcMWpV3S4PQrZCqBVQZApVcxaMmr59W3gkZ',
            'instagram' => '4f69c70b8aa6e19493bfe3bb9eca4ce0a61584184e6f1b86f9b5f60ea54cb4b6',
            'user' => 'AAAKqqsgEAAAAAuwytYlD239syqFz63N0xn4%2FCCtE%3D',
            'page' => 'XxE0BUcdnIqAyhMCwAEcU9PYOUBMe6fxsjH3Aebhz30vod',
        ];

        if ($request->hub_mode == 'subscribe') {
            if (in_array($request->hub_verify_token, array_values($tokens))) {
                return $request->hub_challenge;
            }
        }

        // Check if everything is signed correctly
        if ($request->hasHeader('X-Hub-Signature-256')) {

            $signature = $request->header('X-Hub-Signature-256');
            $signature = Str::of($signature)->remove('sha256=');

            $client_secret = Config::get('services.facebook.client_secret');
            $hash = hash_hmac('sha256', $request->getContent(), $client_secret);

            // We have a valid signature, lets go
            if ($hash != $signature) {
                Log::warning('OAuthController - HandleFacebookWebhook - There was a signature / hash mismatch. Attack?');
                Log::info($request->all());

                return 'OK';
            }

            // If not, good to go
            Log::info('Object block is '.$request->object);

            // Switch the object block
            // CASE - Page
            if ($request->object == 'page') {

                // Loop through entries
                foreach ($request->entry as $entry) {

                    // Get the account id
                    $account_id = $entry['id'];

                    // See if we can find the account
                    $account = NotionSocialAccounts::where('account_id', $account_id)->get();

                    // Check
                    if ($account->count() > 0) {

                        // Loop through the changes
                        foreach ($entry['changes'] as $change) {

                            // Check
                            if ($change['field'] == 'name') {
                                $account->update([
                                    'name' => $change['value'],
                                ]);
                            }
                        }
                    }
                }

            } else {

                Log::warning('Received a post on HandleFacebookWebhook - UNHANDLED');
                Log::warning($request->all());
                Log::warning($request);

            }

        } else {
            if ($request->signed_request) {
                // Do nothing, I'm not sure why I keep receiving these?
            } else {
                Log::warning('OAuthController - HandleFacebookWebhook - No SHA256 signature - Under attack?');
                Log::info($request->all());
            }
        }

        return 'OK';

    }

    public function handleLinkedinErrorMessages($query_response, $default_response)
    {

        // Lets make the query pretty
        $err = $query_response->json();

        if (isset($err['message'])) {
            if (str_contains($err['message'], 'Resource level throttle')) {
                throw new \Exception('resource_level_throttle');
                // throw new \Exception("It looks like you've tried this action too many times, LinkedIn throttles the number of times you can access this resource. Please try again in 24 hours.");
            } else {
                Log::info('OAUTHController - HandleLinkedinErrorMessages - UNHANDLED MESSAGE - '.$err['message']);
                throw new \Exception($default_response);
            }
        } else {
            Log::info('OAUTHController - 322 - Failed to get Linkedin');
            Log::info($response->json());
            throw new \Exception('There was an issue obtaining your LinkedIn organization authorizations, the issue has been logged and will be looked into.');
        }

        // Return default
        throw new \Exception($default_response);
    }

    public function handleTikTokWebhook(Request $request)
    {

        // TODO - Check signature
        // https://developers.tiktok.com/doc/webhooks-verification/

        if ($request->hasHeader('Tiktok-Signature')) {

            // Get the tiktok signature
            $tiktok_signature = $request->header('Tiktok-Signature');
            $tiktok_signature = explode(',', $tiktok_signature);

            // Extract the timestamp
            $timestamp = $tiktok_signature[0];
            $timestamp = explode('=', $timestamp);
            $timestamp = $timestamp[1];

            // Extract the signature
            $signature = $tiktok_signature[1];
            $signature = explode('=', $signature);
            $signature = $signature[1];

            // Get our client secret
            $client_secret = Config::get('services.tiktok.client_secret');

            // Compute the hash
            $hash = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $client_secret);

            // Check
            if ($hash != $signature) {
                Log::info("TikTok Webhook had invalid hash... Signature was $signature - Computed was $hash");
            } else {

                // Check what kind of event it is
                if ($request->event) {

                    // Get the content from the request
                    $content = json_decode($request->content);

                    // CASE - A user has revoked our access
                    if ($request->event == 'authorization.removed') {

                        $do = NotionSocialAccounts::where('account_id', $request->user_openid)->first();
                        if (! $do) {
                            Log::info("OAUTHController - HandleTiktokWebhook - Couldn't find the user ".$request->user_openid);
                        } else {
                            $do->is_valid = 0;
                            $do->save();
                        }

                        // CASE - Post was delivered to TikTok
                    } elseif (
                        $request->event == 'post.publish.inbox_delivered'
                    ) {

                        $post = NotionPosts::where('posted_foreign_id', $content->publish_id)->latest()->first();
                        if (! $post) {
                            Log::info("OAuthController - Received a request from a post that doesn't exist...? 1107");
                            Log::info($request->publish_id);
                        } else {

                            // Get the associated social account and token
                            $social_account = NotionSocialAccounts::with('access_token')->where('id', $post->account_id)->first();

                            // Check the status of the post
                            $response = Http::tiktok()->withToken($social_account->access_token->access_token)
                                ->post('post/publish/status/fetch/', [
                                    'publish_id' => $content->publish_id,
                                ]);

                            // Check
                            if (! $response->successful()) {
                                Log::info("OAuthController 1125 - Received a message saying a post was delivered to inbox, but can't retrieve it's status... Post ID is ".$post->id);

                                // NOTE - Don't do anything, presume that UploadMedia will have already picked up the error and dealt with it
                                return;
                            } else {

                                // Looks like the post is live
                                $rep = $response->json();

                                // Mark the post as posted
                                $post->status = 'posted';
                                $post->save();

                                // Update the Notion Entry
                                UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                    true, // Success level
                                    'Your video is ready to go in your TikTok App Inbox, all you need to do is submit it from there.', // Message we want to share?
                                    $post, // The post object,
                                    $content->publish_id
                                );

                                return;

                            }

                        }

                        // CASE - Publishing of video is successful, mark it as successful in the DB
                    } elseif ($request->event == 'post.publish.complete') {

                        $post = NotionPosts::where('posted_foreign_id', $content->publish_id)->latest()->first();
                        if (! $post) {
                            Log::info("OAuthController - Received a request from a post that doesn't exist...? 1806");
                            Log::info($request->publish_id);
                        } else {

                            $post->status = 'processing_part2';
                            $post->save();

                            // Update the Notion Entry
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                'Your video is currently processing and should be available soon.', // Message we want to share?
                                $post, // The post object,
                                $content->publish_id
                            );

                            return;

                        }

                        // CASE - Post is now publicly available
                    } elseif ($request->event == 'post.publish.publicly_available') {

                        $post = NotionPosts::where('posted_foreign_id', $content->publish_id)->latest()->first();
                        if (! $post) {
                            Log::info("OAuthController - Received a request from a post that doesn't exist...? 1829");
                            Log::info($request->publish_id);
                        } else {

                            $post->posted_foreign_id = $content->post_id;
                            $post->status = 'posted';
                            $post->save();

                            // Update the Notion Entry
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                '', // Message we want to share?
                                $post, // The post object,
                                $content->post_id
                            );

                            return;

                        }

                        // CASE - User has deleted the post or it was removed
                    } elseif ($request->event == 'post.publish.no_longer_publicly_available') {

                        $post = NotionPosts::whereIn('posted_foreign_id', [$content->publish_id, $content->post_id])->latest()->first();
                        if (! $post) {
                            Log::info("OAuthController - Received a request from a post that doesn't exist...? 1829");
                            Log::info($request->publish_id);
                        } else {

                            $post->posted_foreign_id = $content->post_id;
                            $post->status = 'deleted';
                            $post->save();

                            // Update the Notion Entry
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                'This post was removed either by you or by TikTok.', // Message we want to share?
                                $post, // The post object,
                                $content->post_id
                            );

                            return;

                        }

                        // CASE - Post publish failed
                    } elseif ($request->event == 'post.publish.failed') {

                        // NOTE - Content is an object
                        Log::info(json_encode($content));

                        $post = NotionPosts::whereIn('posted_foreign_id', [$content->publish_id, $content->post_id])->latest()->first();
                        if (! $post) {
                            Log::info("OAuthController - Received a request from a post that doesn't exist...? 2208");
                            Log::info($request->publish_id);
                        } else {

                            // Switch the reason for failure
                            if ($content->reason == 'file_format_check_failed') {
                                $message = "The video you tried to upload isn't in the right format. Are you sure it's a valid mp4 file?";
                            } elseif ($content->reason == 'duration_check_failed') {
                                $message = "The video didn't meet the duration restrictions. Is it too long?";
                            } elseif ($content->reason == 'frame_rate_check_failed') {
                                $message = "The video has an unusual frame rate. Are you sure it's between 23 and 120fps?";
                            } else {
                                Log::info('OAUthController - 1164 - Unusual TikTok publish failure error, check it out');
                                Log::info($content->reason);
                                Log::info($request->getContent());
                                $message = 'An unexpected error occurred uploading your post, admins have been notified and will look into it ASAP';
                            }

                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                false, // Success level
                                $message, // Message we want to share?
                                $post // The post object,
                            );

                            return;

                        }

                    } else {

                        Log::info('OAUTHController - HandleTiktokWebhook - Unhandled webhook event');
                        Log::info($request->event);

                    }
                } else {
                    Log::info('Received TikTok webhook with no event');
                    Log::info($request);
                    Log::info($request->getContent());
                    // Log::info($request->all());

                }

            }

        }

        return true;
    }

    // public function tiktokAuth() {

    //     return [
    //         'status' => 'OK',
    //         'messages' => [],
    //         'data' => Socialite::driver('tiktok')
    //             ->scopes(Config::get('services.tiktok.scopes'))
    //             ->with(['auth_type' => 'rerequest'])
    //             ->redirect()
    //             ->getTargetUrl()
    //     ];

    // }

    public function handleTiktokCallback(Request $request)
    {

        // Init
        $view_is_success = false;
        $view_errors = [];
        $view_platform = 'TikTok';

        try {

            // Get the user info
            $user = Socialite::driver('tiktok')->user();

            // Check if we have all scopes
            $diff = array_diff($user->approvedScopes, Config::get('services.tiktok.scopes'));
            if (count($diff) < 1) {

                // We have all scopes, lets add the account
                $insert_token = new NotionSocialAccountsAccessTokens;
                $insert_token->platform = 'tiktok';
                $insert_token->userid = Auth::id();
                $insert_token->access_token = $user->token;
                $insert_token->refresh_token = $user->refreshToken;

                // Check if expiresIn
                if ($user->expiresIn) {
                    $insert_token->expiry_date = Carbon::now()->addSeconds($user->expiresIn);
                } else {
                    $insert_token->expiry_date = Carbon::now()->addYears(10);
                }
                $insert_token->save();

                // Use a fallback chain for the nickname
                $nickname = $user->nickname ?: $user->name;

                if (! $nickname) {
                    Log::info('OauthController - 2311 - Name missing, using raw data or ID');
                    // Log raw data safely
                    Log::info(json_encode($user->getRaw()));

                    // Fallback to display_name in raw response or the ID
                    $nickname = $user->user['display_name'] ?? 'User_'.$user->id;
                }

                // Add the Account
                // $do = NotionSocialAccounts::updateOrCreate(
                $do = $this->updateOrCreateAccount(
                    [
                        'account_id' => $user->id,
                        'account_full_identifier' => $user->user['union_id'],
                        'userid' => Auth::id(),
                        'platform' => $insert_token->platform,
                    ],
                    [
                        'token_id' => $insert_token->id,
                        'name' => $nickname,
                        'profile_picture' => $user->avatar,
                        'is_active' => 1,
                        'is_valid' => 1,
                    ]
                );

                $view_is_success = true;

            } else {
                $view_errors[] = [
                    'type' => 'warning',
                    'message' => "It looks like you didn't approve all the required permissions to use NotionScheduler. You're missing the following: ".implode(', ', $diff),
                ];
            }

        } catch (\Exception $e) {

            // Make pretty
            $msg = $e->getMessage();

            // Handle the errors

            // CASE - This happens if the driver('tiktok')->user() fails - This is due to not having the right scopes
            if (Str::contains($msg, 'The user did not authorize the scope required for')) {
                $view_errors[] = [
                    'type' => 'warning',
                    'message' => "It looks like you didn't enable all the required scopes, we were unable to get your profile from TikTok. Please try again and tick all the access requirements.",
                ];
            } else {
                $view_errors[] = [
                    'type' => 'warning',
                    'message' => 'There was an unhandled error when processing your request. The admins have been notified and will look into it shortly',
                ];
                Log::info('OAuthController - HandleTikTokCallback - UNHANDLED Error');
                Log::info($msg);
                Log::info($e);
            }

        }

        // Return
        return $this->redirectBackToApp($view_platform, $view_is_success, $view_errors);

        return view('oauthcallback',
            [
                'platform' => $view_platform,
                'is_success' => $view_is_success,
                'errors' => $view_errors,
            ]
        );

    }

    public function GEThandleNotionWebhook(Request $request)
    {

        Log::debug('Received GET from Notion Webhook, not sure why...?');
        Log::debug('Request data', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'files' => $request->allFiles(),
        ]);

        return response()->json(['status' => 'processed'], 200);

    }

    public function handleNotionWebhook(Request $request)
    {

        // 1. Your verification token
        $verification_token = Config::get('services.notion.verification_secret');

        // 2. Get the signature from the request header
        $notionSignature = $request->header('X-Notion-Signature');

        // 3. Get the raw body content (critical for hashing)
        $payload = $request->getContent();

        // 4. Calculate the signature
        // Notion expects: "sha256=" followed by the hex hmac
        $computedHash = hash_hmac('sha256', $payload, $verification_token);
        $calculatedSignature = 'sha256='.$computedHash;

        // 5. Use hash_equals for timing-safe comparison
        if (! $notionSignature || ! hash_equals($calculatedSignature, $notionSignature)) {
            // Log::warning("Notion Webhook - Unauthorized: Signature mismatch.");
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // --- Validation Passed ---
        // Log::info("Notion Webhook - Verified successfully");

        // Now you can safely use the data
        $data = $request->all();

        // 2. ISOLATE data.parent.id
        // Laravel allows dot notation to reach into nested JSON arrays
        $parentId = $request->input('data.parent.id');

        if (! $parentId) {
            Log::info('Notion Webhook - Received, but data.parent.id was missing.');

            return response()->json(['message' => 'Parent ID not found in payload'], 200);
        }

        // 3. CHECK NotionDatabases model
        $databaseRecord = NotionDatabases::where('database_id', $parentId)->first();

        if ($databaseRecord) {
            // Log::info("Notion Webhook - Database match found: " . $parentId);

            // 1. Get the array of updated properties from the request
            // We default to an empty array [] if it's missing to avoid errors
            $updatedProperties = $request->input('data.updated_properties', []);

            // 2. Get the specific property name you are looking for from your DB record
            $targetProperty = $databaseRecord->column_is_ready;

            // 3. Check if your target property is in the list of what was updated
            if (in_array($targetProperty, $updatedProperties)) {

                // Log::info("Match! The property (IS READY) - '{$targetProperty}' was updated.", ['data_PROUT PROUT' => $data, 'databaseRecord' => $databaseRecord]);
                // Log::info("Dispatching a job with a delay to scan that DB");
                $databaseRecord->load('token');
                FindNotionPostsInDB::dispatch($databaseRecord)
                    ->delay(now()->addSeconds(120));

                // --- DO YOUR "MORE STUFF" HERE ---
                // Example: Trigger an automation, update a status, etc.

            } else {
                // Log::info("The updated properties did not include '{$targetProperty}'.");
            }

        } else {
            // Log::info("Notion Webhook - No matching database found for ID: " . $parentId);
        }

        // Always return a 200 to Notion so they don't keep retrying the webhook
        return response()->json(['status' => 'processed'], 200);

    }
}
