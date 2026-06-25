<?php

namespace App\Http\Controllers;

use App\Jobs\CorrectNotionDatabaseScaffolding;
use App\Jobs\UpdateNotionDatabaseEntry;
use App\Jobs\UploadMedia;
use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionErrorManager;
use App\Models\NotionPages;
use App\Models\NotionPosts;
use App\Models\NotionSocialAccounts;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Notion\Blocks\BlockFactory;
use Notion\Blocks\ToDo;
use Notion\Common\File;
use Notion\Common\Icon;
use Notion\Databases\Database;
use Notion\Notion;
use Notion\Pages\Page;
use Notion\Pages\PageParent;
use Notion\Pages\Properties\Number;
use Notion\Search\Query;

class DashboardController extends Controller
{
    /**
     * Inertia dashboard page. Loads the user's databases (with linked socials),
     * social accounts, and recent scheduled posts as page props. Mutations and
     * on-demand scans are handled by the JSON endpoints in routes/app.php
     * (consumed from the page via useHttp / router).
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $databases = NotionDatabases::with('socials')
            ->where('userid', $userId)
            ->where('is_active', 1)
            ->get();

        $socials = NotionSocialAccounts::where('userid', $userId)
            ->where('is_active', 1)
            ->get();

        // The "Scheduled" tab: upcoming / pending / errored — everything that
        // is NOT already posted. Submitted posts load lazily + paginated via
        // submittedPosts() so a heavy posting history doesn't bloat this page.
        $posts = NotionPosts::with('latestMetrics')
            ->where('userid', $userId)
            ->whereNotIn('status', ['deleted', 'posted'])
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->latest()
            ->limit(200)
            ->get();

        $accounts = NotionSocialAccounts::where('userid', $userId)
            ->whereIn('id', $posts->pluck('account_id')->unique()->filter())
            ->get();

        return Inertia::render('Dashboard', [
            'databases' => $databases,
            'socials' => $socials,
            'posts' => $posts,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Paginated history of already-published posts (the "Submitted" tab).
     */
    public function submittedPosts(Request $request)
    {
        $userId = Auth::id();

        $paginator = NotionPosts::with('latestMetrics')
            ->where('userid', $userId)
            ->where('status', 'posted')
            ->where('is_active', 1)
            ->latest('posted_date')
            ->paginate(20);

        $accounts = NotionSocialAccounts::where('userid', $userId)
            ->whereIn('id', collect($paginator->items())->pluck('account_id')->unique()->filter())
            ->get(['id', 'platform', 'name']);

        return Response::default('OK', [
            'posts' => $paginator->items(),
            'accounts' => $accounts,
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * FIXME - This function serves no purpose other than to get Facebook off my back and get them to validate my app because their teams are borderline braindead
     */
    public function fakeFacebookFunctionCreatePost(Request $request)
    {

        // INIT
        $errors = [];

        Log::info($request->all());

        // Do validator
        $validator = Validator::make(
            $request->all(),
            [
                'post_name' => ['required', 'filled', 'max:255'],
                'social' => ['required', 'filled', 'numeric'],
                'content' => ['required', 'filled', 'max:255'],
                'media' => ['required', 'filled', 'url'],
                'scheduling' => ['required', 'filled'],
                'date' => ['date'],

            ]
        );

        if ($validator->fails()) {
            $validator_errors = $validator->errors()->toArray();
            foreach ($validator_errors as $va) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => $va[0],
                ];
            }

            return Response::default(
                'FAIL',
                [],
                $errors
            );
        }

        // Check that the user we want to post to exists
        $account = NotionSocialAccounts::where('id', $request->social)
            // ->where('is_valid', 1)
            // ->where('is_active', 1)
            ->where('userid', Auth::id())
            ->first();
        if (! $account) {
            return Response::failWithMessage('warning', "We couldn't find the account you're trying to post to.");
        }
        if ($account->is_valid == 0 or $account->is_active == 0) {
            return Response::failWithMessage('warning', 'You are attempting to post to an account that is disabled / inactive. Please re-attach this social media account to NotionScheduler and try again');
        }

        if (! in_array($account->name, ['Allegrah - Lectrice', 'missbrokenback'])) {
            return Response::failWithMessage('warning', "For now, only the accounts 'missbrokenback' and 'Allegrah - Lectrice' have been approved for use by Facebook. Please only use one of the approved accounts for testing.");
        }

        // Parse the media
        $parse_url = parse_url($request->media, PHP_URL_PATH);
        $media = [
            'url' => $request->media,
            'filename' => pathinfo($parse_url, PATHINFO_FILENAME),
            'extension' => strtolower(pathinfo($parse_url, PATHINFO_EXTENSION)),
        ];
        if (! in_array($media['extension'], NotionPosts::getImageFileTypes()) && ! in_array($media['extension'], NotionPosts::getVideoFileTypes())) {
            return Response::failWithMessage('warning', 'Your file '.$media['filename']." isn't a valid photo or video format, we won't be able to upload it.");
        }
        // $media = [
        //     'type' => 'image',
        //     'extension' => strtolower(pathinfo(parse_url($media, PHP_URL_PATH), PATHINFO_EXTENSION)),
        //     'url' => $media
        // ];
        $media = [$media];

        // Check if we're posting now or not
        $delay = 0;
        $post_date = Carbon::now();
        if ($request->scheduling == 'later') {
            $delay = abs(Carbon::parse($request->date)->diffInHours(Carbon::now()));
            $post_date = Carbon::parse($request->date);
        }

        // Add the post to the DB
        $post = NotionPosts::create([
            'userid' => Auth::id(),
            'database_id' => 1337,
            'post_page_id' => 'facebook_test',
            'account_id' => $account->id,
            'post_name' => $request->post_name,
            'platform' => $account->platform,
            'platform_is_story' => false,
            'status' => 'posted',
            'in_queue' => 0,
            'in_flight' => 0,
            'scheduled_date' => $post_date,
            'posted_date' => $post_date,
        ]);

        // Dispatch the job
        UploadMedia::dispatch(
            $post,
            $account,
            $request->content,
            $media,
            null
        )->delay($delay);

        // Return
        return Response::successWithMessage('Your post was successfully scheduled!');

    }

    /**
     * SECTION - Get all connections and socials from a user
     */
    public function getConnections()
    {

        // INIT
        $status = 'OK';
        $data = [];
        $errors = [];

        // Get all Notion Databases
        $databases = NotionDatabases::with('socials')
            ->where('userid', Auth::id())
            ->where('is_active', 1)
            ->get();

        // Loop through them
        foreach ($databases as $database) {
            if ($database->is_valid == 0) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => "It looks like one of your Notion Databases is no longer connected to your Notion Account. Try refreshing your Notion connection using the button below. If you've deleted this database from your Notion account, you can also delete it from here.",
                ];
                break;
            }
        }

        // Get all Social accounts
        $socials = NotionSocialAccounts::where('userid', Auth::id())
            ->where('is_active', 1)
            ->get();

        // Loop through them in case of errors
        foreach ($socials as $social) {
            if ($social->is_valid == 0) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => 'It looks like NotionScheduler no longer has access to your '
                                .ucfirst($social->platform).
                                " account '"
                                .$social->name."'. Try re-adding to to NotionScheduler in order to enable it again.",
                ];
                break;
            }
        }

        // Set the data
        $data = [
            'socials' => $socials,
            'databases' => $databases,
        ];

        // if (Auth::user()->isAdmin()) {

        //     $active_dbs = Auth::user()->getActiveDatabaseIDs();
        //     $active_socials = NotionSocialAccounts::select('id', 'database_id', 'platform', 'name')
        //         ->whereIn('database_id', $active_dbs)
        //         ->where('is_active', 1)
        //         ->where('is_valid', 1)
        //         ->get();

        //     $data['test_2'] = [
        //         $active_dbs,
        //         $active_socials
        //     ];
        // }

        // All set? Return
        return Response::default($status, $data, $errors);
    }

    /**
     * SECTION - Unschedule a post from the Dashboard
     */
    public function removeScheduledPost(Request $request)
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Check if we have a request
        if (! $request->post_id) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'No post id provided. Has this post already been deleted?',
            ];
        } else {
            // Check if we can find the post
            $get = NotionPosts::where('id', $request->post_id)
                ->where('userid', Auth::id())
                ->first();

            // Check
            if (! $get) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => "We couldn't find the post you're trying to delete. Has it already been deleted?",
                ];
            } else {
                // Found the post, lets update it
                $get->update([
                    'is_active' => 0,
                    'status' => 'post_removed_in_dashboard',
                ]);

                // Set the status
                $status = 'OK';
                $data = $get;
                $errors[] = [
                    'type' => 'success',
                    'message' => 'Your post was successfully un-scheduled. Feel free to re-schedule it from within your Notion database if you would like to post it again.',
                ];
            }
        }

        // All set? Return
        return Response::default($status, $data, $errors);
    }

    /**
     * SECTION - Look for databases on an account
     */
    public function lookForNewDatabases()
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Get their Notion Access token
        $token = NotionAccessTokens::where('userid', Auth::id())
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->first(); // FIXME - Case where a user has more than one token???

        // Check
        if (! $token) {
            $errors[] = [
                'type' => 'danger',
                'message' => 'There are no active Notion Access Tokens associated with your account. You need to re-attach Notion Scheduler to your account for this to work. Please use the Notion connection button below.',
            ];
        } else {

            // Try connecting to the database
            try {

                // Create a Notion object
                $notion = Notion::create($token->token);
                $query = Query::all()->filterByDatabases();
                $results = $notion->search()->search($query);

                /**
                 * NOTE - We could implement our one thing in here, however removed databases only linger for a few seconds, so it is really worth it...?
                 */

                // dump($results);

                // // Lets try HTTP instead
                // $http = Http::withToken($token->token)
                //     ->acceptJson()
                //     ->withHeaders([
                //        "Notion-Version" => "2022-06-28"
                //     ])
                //     ->post('https://api.notion.com/v1/search', [
                //     "filter" => [
                //         "value" => "database",
                //         "property" => "object"
                //     ],
                // ]);
                // // TODO - What if key was revoked?
                // if (!$http->ok()) {
                //     return Response::failWithMessage("There was an issue querying your account for existing databases");
                // }

                // $results = $http->collect();

                // if (!$results->count() > 1) {
                //     return Response::failWithMessage("There are no Databases available on your Notion account. Create one first.");
                // }

                // $results = $results->where('archived', false);
                // dd($results);

                // Check if we have results
                if (empty($results->results)) {
                    $errors[] = [
                        'type' => 'warning',
                        'message' => "We couldn't find any Databases in your Notion workspace. If you have created one, make sure that NotionScheduler has access to the page that you've stored the database in. If you haven't created one, you can create one yourself manually, or go back and let NotionScheduler create one for you.",
                    ];
                } else {

                    // Lets get our current databases
                    $current_databases = NotionDatabases::where('token_id', $token->id) // NOTE - This should work fine if we end up using a foreach loop to have a look at ALL of our tokens
                        ->where('is_active', 1)
                        ->get()
                        ->pluck('database_id')
                        ->all();

                    // We have some results, lets create an array of them
                    $available_databases = [];

                    // Loop through all of our results
                    foreach ($results->results as $result) {

                        // Grab the title
                        if (empty($result->title)) {
                            $title = 'Untitled Database';
                        } else {
                            $title = $result->title[0]->toString();
                        }

                        // We'd need to make sure we're not looking for an existing database
                        if (! in_array($result->id, $current_databases)) {
                            $available_databases[] = [
                                'id' => $result->id,
                                'title' => $title,
                                'url' => $result->url,
                            ];
                        }

                    }

                    // Check if we actually have results
                    if (count($available_databases) < 1) {
                        $errors[] = [
                            'type' => 'warning',
                            'message' => "We couldn't find any new Databases in your Notion workspace. If you have created one, make sure that NotionScheduler has access to the page that you've stored the database in. If you haven't created one, you can create one yourself manually, or go back and let NotionScheduler create one for you.",
                        ];
                    } else {
                        $status = 'OK';
                        $data = $available_databases;
                    }
                }
            } catch (\Exception $e) {

                // Handle the error
                $errors[] = NotionErrorManager::manageError(
                    Auth::id(),
                    $e,
                    $token,
                    'Dashboard Controller 278'
                );
            }
        }

        // All set? Return
        return Response::default($status, $data, $errors);
    }

    /**
     * SECTION - Look for pages that we can add a DB to
     */
    public function lookForPages()
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Get their Notion Access token
        $token = NotionAccessTokens::where('userid', Auth::id())
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->first(); // FIXME - Case where a user has more than one token???

        // Check
        if (! $token) {
            $errors[] = [
                'type' => 'danger',
                'message' => 'There are no active Notion Access Tokens associated with your account. You need to re-attach Notion Scheduler to your account for this to work. Please use the Notion connection button below.',
            ];
        } else {

            // Try connecting to the database
            try {

                // Create a Notion object
                $notion = Notion::create($token->token);
                $query = Query::all()->filterByPages();
                $results = $notion->search()->search($query);

                // Check if we have results
                if (empty($results->results)) {
                    $errors[] = [
                        'type' => 'warning',
                        'message' => "We couldn't find any Pages in your Notion workspace. Are you sure you authorized NotionScheduler to access at least one page in your Notion workspace? Please try re-authorizing NotionScheduler to access your Notion account using the 'Re-connect Notion' button.",
                    ];
                } else {

                    // Create array
                    $pages = [];

                    // Loop through all of our results
                    foreach ($results->results as $result) {

                        // $result is of type Page;
                        // $result->parent is of type PageParent

                        // Grab the title
                        if ($result->title()->isEmpty()) {
                            $title = 'Untitled Page';
                        } else {
                            $title = $result->title()->toString();
                        }

                        // Grab the icon
                        $icon = null;
                        $icon_type = null;
                        if ($result->hasIcon()) {
                            if ($result->icon->isEmoji()) {
                                $icon_type = 'emoji';
                                $icon = $result->icon->emoji->toString();
                            }
                        }

                        // Check if the page is not deleted
                        if (! $result->archived) {
                            // Go through all the results and only add the ones that are workspace or page level parents
                            if ($result->parent->isPage() or $result->parent->isWorkspace()) {

                                $pages[] = [
                                    'id' => $result->id,
                                    'title' => $title,
                                    'url' => $result->url,
                                    'icon' => $icon,
                                    'icon_type' => $icon_type,
                                ];

                            }
                        }
                    }

                    // Check if we actually have results
                    if (count($pages) < 1) {
                        $errors[] = [
                            'type' => 'warning',
                            'message' => "We couldn't find any Pages in your Notion workspace. Are you sure you authorized NotionScheduler to access at least one page in your Notion workspace? Please try re-authorizing NotionScheduler to access your Notion account using the 'Re-connect Notion' button.",
                        ];
                    } else {
                        $status = 'OK';
                        $data = $pages;
                    }

                }
            } catch (\Exception $e) {
                // Handle the error
                $errors[] = NotionErrorManager::manageError(
                    Auth::id(),
                    $e,
                    $token,
                    'Dashboard controller 388'
                );
            } catch (\Throwable $e) {
                Log::error('UNHANDLED DashboardController 530');
                Log::info($e);
            }
        }

        // All set? Return
        return Response::default($status, $data, $errors);
    }

    /**
     * SECTION - Build scaffolding for existing page
     */
    public function buildPageScaffolding(Request $request)
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Check if we have a request element
        if (! $request->page_id) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'No Page ID was submitted.',
            ];
        } else {

            /**
             * NOTE - Guarding the page
             */
            $active_databases = Auth::user()->getActiveDatabaseCount();
            // $active_socials = Auth::user()->getTotalSocialAccountsConnectedToDatabases();
            $package = Auth::user()->getSubscriptionOptions();
            $max_databases = $package['databases'];
            // $max_socials = $package['social_accounts'];

            if ($active_databases >= $max_databases) {
                return Response::failWithMessage('warning', "Your current package allows you to manage a maximum of $max_databases Notion databases. In order to increase this limit, head over to the Subscription page.");
            }

            // Perform the query
            // Get their Notion Access token
            $token = NotionAccessTokens::where('userid', Auth::id())
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->first(); // FIXME - Case where a user has more than one token???

            // Check
            if (! $token) {
                $errors[] = [
                    'type' => 'danger',
                    'message' => 'There are no active Notion Access Tokens associated with your account. You need to re-attach Notion Scheduler to your account for this to work. Please use the Notion connection button below.',
                ];
            } else {

                // Try connecting to the database
                try {

                    // Create a Notion object
                    $notion = Notion::create($token->token);

                    // Generate page scaffolding
                    $page_title = 'Notion Scheduler';
                    $page_emoji = '📸';
                    $page = NotionPages::createDefaultScaffolding(
                        $notion,
                        $request->page_id,
                        $page_title,
                        $page_emoji
                    );

                    // Get default scaffolding
                    $scaffolding = NotionDatabases::getDefaultScaffolding();

                    $accounts = [];
                    if ($request->social_accounts) {
                        $accounts = NotionSocialAccounts::where('userid', Auth::id())
                            ->whereIn('id', $request->social_accounts)
                            ->get();
                    }

                    // Create the scaffolding
                    $database = NotionDatabases::createScaffolding(
                        $notion,
                        $scaffolding,
                        $accounts,
                        $page->id
                    );

                    // Get all properties
                    $database_arr = $database->toArray();

                    // Insert into DB
                    $insert = new NotionDatabases;
                    $insert->userid = Auth::id();
                    $insert->token_id = $token->id;
                    $insert->database_id = $database_arr['id'];
                    $insert->database_name = $scaffolding['title']['name'];
                    $insert->database_parent_page = $database_arr['parent']['page_id'];

                    // Try adding all the columns to the array
                    foreach ($scaffolding['properties'] as $key => $element) {
                        try {
                            $column = $element['column'];
                            $prop_id = $database->properties()->get(
                                $element['name']
                            )->metadata()->id;
                            $insert->$column = $prop_id;
                        } catch (\Exception $e) {
                            Log::info('DashBoard Error 359');
                            Log::info($key);
                            Log::info($element);
                            Log::info($e->getMessage());
                        }
                    }

                    // Save it
                    $insert->save();

                    // Check if we're attaching social accounts
                    if ($request->social_accounts) {

                        // Update the social accounts to point to new DB
                        $accounts = NotionSocialAccounts::where('userid', Auth::id())
                            ->whereIn('id', $request->social_accounts)
                            ->update(['database_id' => $insert->id]);

                        // Mark the other databases for updating
                        // $update = NotionDatabases::whereNot('id', $insert->id)
                        //     ->where('userid', Auth::id())
                        //     ->update([
                        //         'last_check_scaffolding_scan' => Carbon::now()->subDays(10)
                        //     ]);
                        $update = NotionDatabases::whereNot('id', $insert->id)
                            ->where('userid', Auth::id())
                            ->get();

                        foreach ($update as $db) {
                            CorrectNotionDatabaseScaffolding::dispatch($db);
                        }
                    } else {
                        CorrectNotionDatabaseScaffolding::dispatch($insert);
                    }

                    try {

                        // Perform a save on our social media accounts
                        $select = $database->properties()->get(
                            $scaffolding['properties']['social_accounts']['name']
                        )->toArray();

                        // Get slugs
                        $slugs = NotionSocialAccounts::getAllSlugsFromUser($insert->id, Auth::id());

                        // Go through them
                        if ($select) {
                            if ($select['select']['options']) {
                                foreach ($select['select']['options'] as $select_option) {
                                    if (isset($slugs[$select_option['name']])) {

                                        // Make pretty
                                        $cur_slug = $slugs[$select_option['name']];
                                        // dump($cur_slug);

                                        // Update the id
                                        NotionSocialAccounts::where('id', $cur_slug['id'])
                                            ->update(
                                                [
                                                    'option_select_id' => $select_option['id'],
                                                ]
                                            );

                                    }
                                }
                            }
                        }

                    } catch (\Exception $e) {
                        Log::info('DashboardController fail');
                        Log::info($e);
                    }

                    // Set stauts
                    $status = 'OK';

                    // Set the data
                    $data = $insert;

                } catch (\Exception $e) {
                    Log::info('676 DashboardController');
                    Log::info($e);
                    // return $e;
                    // Handle the error
                    $errors[] = NotionErrorManager::manageError(
                        Auth::id(),
                        $e,
                        $token,
                        'Dashboard controller 505'
                    );
                }
            }

        }

        // All set? Return
        return Response::default($status, $data, $errors);

    }

    public function testGetPageContent()
    {

        $token = NotionAccessTokens::where('userid', Auth::id())
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->first(); // FIXME - Case where a user has more than one token???

        $pageId = 'b5e01560c65f41b796f7e1d635370b71';
        $notion = Notion::create($token->token);
        // $page = $notion->pages()->find($pageId);
        $content = $notion->blocks()->findChildrenRecursive($pageId);

        //    dump($page);
        dump($content);

        $parent = PageParent::page($pageId);
        $page = Page::create($parent)
            ->changeTitle('Empty page')
            ->changeIcon(
                Icon::fromFile(
                    File::createExternal('https://notionscheduler.app/favicon.png')
                )
            );

        $new_content = [];
        foreach ($content as $con) {
            $new_content[] = $con->toArray();
        }

        dump($new_content);

        $newnew = [];
        foreach ($new_content as $newc) {
            $newnew[] = BlockFactory::fromArray($newc);
        }
        dump($newnew);

        $page = $notion->pages()->create($page, $newnew);
        exit();

        return $page;

    }

    public function buildDatabaseScaffolding(Request $request)
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Check if we have a request element
        if (! $request->database_id) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'No Database ID was submitted.',
            ];
        } else {

            /**
             * NOTE - Guarding the page
             */
            $active_databases = Auth::user()->getActiveDatabaseCount();
            // $active_socials = Auth::user()->getTotalSocialAccountsConnectedToDatabases();
            $package = Auth::user()->getSubscriptionOptions();
            $max_databases = $package['databases'];
            // $max_socials = $package['social_accounts'];

            if ($active_databases >= $max_databases) {
                return Response::failWithMessage('warning', "Your current package allows you to manage a maximum of $max_databases Notion databases. In order to increase this limit, head over to the Subscription page.");
            }

            // Perform the query
            // Get their Notion Access token
            $token = NotionAccessTokens::where('userid', Auth::id())
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->first(); // FIXME - Case where a user has more than one token???

            // Check
            if (! $token) {
                $errors[] = [
                    'type' => 'danger',
                    'message' => 'There are no active Notion Access Tokens associated with your account. You need to re-attach Notion Scheduler to your account for this to work. Please use the Notion connection button below.',
                ];
            } else {

                // Try connecting to the database
                try {

                    // Create a Notion object
                    $notion = Notion::create($token->token);

                    // Get default scaffolding
                    $scaffolding = NotionDatabases::getDefaultScaffolding();
                    $database = $notion->databases()->find($request->database_id);

                    // Have we found it
                    if ($database) {

                        $database_arr = $database->toArray();

                        // Insert into DB
                        $insert = new NotionDatabases;
                        $insert->userid = Auth::id();
                        $insert->token_id = $token->id;
                        $insert->database_id = $database_arr['id'];
                        $insert->database_name = $scaffolding['title']['name'];
                        if (! isset($database_arr['parent']['page_id'])) {
                            // Log::warning("Dashboard Controller missing element 844");
                            // Log::warning($database_arr);
                        } else {
                            $insert->database_parent_page = $database_arr['parent']['page_id'];
                        }
                        $insert->last_check_scaffolding_scan = Carbon::now()->subDays(10);
                        $insert->save();

                        // Set stauts
                        $status = 'OK';

                        // Check if we're attaching social accounts
                        if ($request->social_accounts) {

                            // Update the social accounts to point to new DB
                            $accounts = NotionSocialAccounts::where('userid', Auth::id())
                                ->whereIn('id', $request->social_accounts)
                                ->update(['database_id' => $insert->id]);

                            // Mark the other databases for updating
                            // $update = NotionDatabases::whereNot('id', $insert->id)
                            //     ->where('userid', Auth::id())
                            //     ->update([
                            //         'last_check_scaffolding_scan' => Carbon::now()->subDays(10)
                            //     ]);
                            $update = NotionDatabases::whereNot('id', $insert->id)
                                ->where('userid', Auth::id())
                                ->get();

                            foreach ($update as $db) {
                                CorrectNotionDatabaseScaffolding::dispatch($db);
                            }

                        } else {
                            CorrectNotionDatabaseScaffolding::dispatch($insert);
                        }
                    }

                } catch (\Exception $e) {
                    // Handle the error
                    $errors[] = NotionErrorManager::manageError(
                        Auth::id(),
                        $e,
                        $token,
                        'Dashboard controller 601'
                    );
                }
            }

        }

        // All set? Return
        return Response::default($status, $data, $errors);

    }

    public function reconnectDatabase(Request $request)
    {

        // Check to see if we have the items we need
        if (! $request->id) {
            return Response::failWithMessage('warning', 'No Database ID provided');
        }

        // Check to see if we have a DB associated with this user
        $database = NotionDatabases::with('token')
            ->where('id', $request->id)
            ->where('userid', Auth::id())
            ->first();

        if (! $database) {
            return Response::failWithMessage('warning', "We couldn't find the Database you're trying to restore.");
        }

        /**
         * NOTE - Guarding the page
         */
        $active_databases = Auth::user()->getActiveDatabaseCount();
        // $active_socials = Auth::user()->getTotalSocialAccountsConnectedToDatabases();
        $package = Auth::user()->getSubscriptionOptions();
        $max_databases = $package['databases'];
        // $max_socials = $package['social_accounts'];

        if ($active_databases >= $max_databases) {
            return Response::failWithMessage('warning', "Your current package allows you to manage a maximum of $max_databases Notion databases. In order to increase this limit, head over to the Subscription page.");
        }

        // Found the DB, lets try connecting to it
        try {

            // Connect to the DB
            $notion = Notion::create($database->token->token);
            $notion_database = $notion->databases()->find($database->database_id);

            // Log::info($notion_database->toArray());
            // return;

            // Get properties
            $props = $notion_database->properties();

            // Log::info($props->getAll());
            // return;

            // Reconnect the DB
            $database->is_valid = true;
            $database->save();

            // All set? Return
            return Response::default();

        } catch (\Throwable $e) {

            // Get the message
            $msg = $e->getMessage();

            // Check the case
            if (Str::of($msg)->contains('API token is invalid.')) {
                return Response::failWithMessage('warning', 'The Notion Token associated with this database is invalid. Try re-adding your your Notion workspace to your account.');
            } elseif (Str::of($msg)->contains('Could not find database with ID')) {
                return Response::failWithMessage('warning', "We couldn't find the Notion database you're looking for, it looks like it might have been permanently deleted.");
            } elseif (Str::of($msg)->contains('Public API service is temporarily unavailable')) {
                return Response::failWithMessage('warning', "Notion's API is currently experiencing issues, please try this request again later.");
            } else {
                Log::warning('DashboardController - 893 - UNHANDLED');
                Log::warning($e);
                Log::warning($msg);

                return Response::failWithMessage('warning', 'There was an issue processing your request, admins have been notified and will look into it ASAP.');
            }

        }

    }

    public function removeDatabase(Request $request)
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Check if we have a request element
        if (! $request->id) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'No Database ID was submitted.',
            ];
        } else {

            // Try and find the database
            $db = NotionDatabases::where('userid', Auth::id())
                ->where('id', $request->id)
                ->first();

            // Check
            if (! $db) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => "Couldn't find the Database you are trying to remove. Has it already been removed?",
                ];
            } else {

                // We found it, lets update it
                $db->update(
                    [
                        'is_active' => 0,
                    ]
                );

                // Update
                $status = 'OK';

            }
        }

        // All set? Return
        return Response::default($status, $data, $errors);
    }

    public function updateDatabaseSocials(Request $request)
    {

        // Check if we have anything
        if (! isset($request->social_accounts)) {
            return Response::failWithMessage('warning', 'Missing social accounts from query.');
        }
        if (! is_array($request->social_accounts)) {
            return Response::failWithMessage('warning', 'Missing social accounts from query.');
        }

        // Check if we have a db
        if (! $request->database_id) {
            return Response::failWithMessage('warning', 'No database provided.');
        }

        // Check if we ACTUALLY have a DB
        $database = NotionDatabases::where('id', $request->database_id)
            ->where('userid', Auth::id())
            ->where('is_active', 1)
            ->first();

        if (! $database) {
            return Response::failWithMessage('warning', "We couldn't find the database you're trying to edit.");
        }

        // NOTE - Get the existing socials
        $existing_socials = NotionSocialAccounts::where('database_id', $database->id)
            ->get();

        // CASE - Empty array, meaning we want to remove all entries from the DB
        if (count($request->social_accounts) < 1) {
            // Find all of them

            $update = NotionSocialAccounts::where('database_id', $database->id)
                ->update(['database_id' => null]);

            $social_accounts = $existing_socials;

            // CASE - Non-empty array
        } else {
            $social_accounts = NotionSocialAccounts::whereIn('id', $request->social_accounts)
                ->where('is_active', 1)
                ->where('userid', Auth::id())
                ->get();

            if ($social_accounts->count() < 1) {
                return Response::failWithMessage('warning', "We couldn't find the social accounts you're trying to edit.");
            }

            /**
             * NOTE - Guarding the page
             */
            // $active_databases = Auth::user()->getActiveDatabaseCount();
            // $active_socials = Auth::user()->getTotalSocialAccountsConnectedToDatabases();
            $package = Auth::user()->getSubscriptionOptions();
            // $max_databases = $package['databases'];
            $max_socials = $package['social_accounts'];

            $active_dbs = Auth::user()->getActiveDatabaseIDs();
            $active_socials_in_databases = NotionSocialAccounts::select('id', 'database_id', 'platform', 'name')
                ->whereIn('database_id', $active_dbs)
                ->where('is_active', 1)
                ->where('is_valid', 1)
                ->get();

            // Create an array containing all of the accounts that we'll have at the end
            $total_accounts = [];

            // Add ALL of our active socials that are currently in a DB
            foreach ($active_socials_in_databases as $a) {
                $total_accounts[] = $a->id;
            }
            // $social_accounts contains the accounts that are going to be added to this DB
            foreach ($social_accounts as $a) {
                $total_accounts[] = $a->id;
            }
            $total_accounts = array_unique($total_accounts);

            // Get all the social accounts that are going to be removed from this specific database
            $social_accounts_removed_from_this_database = NotionSocialAccounts::where('database_id', $database->id)
                ->whereNotIn('id', $request->social_accounts)
                ->get();

            $final_accounts = array_diff($total_accounts, $social_accounts_removed_from_this_database->pluck('id')->all());
            $final_accounts = count($final_accounts);

            if ($final_accounts > $max_socials) {
                return Response::failWithMessage('warning', "Your current package allows you to enable a maximum of $max_socials Social Media Accounts in your active Notion Databases. In order to increase this limit, head over to the Subscription page.");
            }

            /**!SECTION
             *
             * We have the current number of active socials in databases
             *
             * Maybe run a simulation of what it would look like, creating an array of all the active socials by the end of the transaction? Then array_unique and count?
             *
             */

            //
            /**
             * What we want to do here is check how many will be added by the end of it?
             *
             * To do that, we need to :
             * - check how many are being added to this new database
             * - check how many are being removed from the other databases
             *
             * The total amount of social accounts that are connected is equal to:
             * - The ones being added to a new DB
             * - The ones remaining in the other DBs (which is equal to the ones in those DBs minus the ones being moved to a new DB?)
             */

            // Make the changes to the social accounts
            $update = NotionSocialAccounts::whereIn('id', $social_accounts->pluck('id'))
                ->update([
                    'database_id' => $database->id,
                ]);

            // Check if there are socials to remove
            if ($existing_socials->count() > 0) {
                $update = NotionSocialAccounts::whereNotIn('id', $social_accounts->pluck('id'))
                    ->whereIn('id', $existing_socials->pluck('id'))
                    ->update(['database_id' => null]);
            }

        }

        // Create an array of all the databases to refresh
        $to_refresh = $social_accounts->pluck('database_id');
        $to_refresh = $to_refresh->push($database->id);
        $to_refresh = $to_refresh->unique()->values();

        // Mark specific databases as needing an update
        if ($to_refresh->count() > 0) {
            // $databases = NotionDatabases::whereIn('id', $to_refresh)
            //     ->update([
            //         'last_check_scaffolding_scan' =>  Carbon::now()->subDays(10)
            //     ]);
            $databases = NotionDatabases::whereIn('id', $to_refresh)
                ->get();
            foreach ($databases as $db) {
                CorrectNotionDatabaseScaffolding::dispatch($db);
            }
        }

        return Response::default();

    }

    public function getAllPosts(Request $request)
    {

        // Default to the authenticated user. Admins may view another user's posts
        // by passing ?user_id=xyz (read-only — it only scopes the query).
        $userid = Auth::id();
        if ($request->filled('user_id') && Auth::user()->isAdmin()) {
            $userid = $request->user_id;
        }

        // Latest 50 posts for this user. We deliberately DON'T eager-load 'account'
        // here — a shared account would otherwise be serialized once per post (100
        // posts on one account = 100 copies). Instead we return the referenced
        // accounts once below and let the frontend join on post.account_id.
        $posts = NotionPosts::with('latestMetrics')
            ->where('userid', $userid)
            ->whereNot('status', 'deleted')
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->latest()
            ->limit(200)
            ->get();

        // The distinct accounts those posts belong to, loaded once. Scoped to the
        // same user as a safety belt (matters in the admin-impersonation case).
        $accounts = NotionSocialAccounts::where('userid', $userid)
            ->whereIn('id', $posts->pluck('account_id')->unique()->filter())
            ->get();

        // Return
        return Response::default('OK', [
            'posts' => $posts,
            'accounts' => $accounts,
        ]);

    }

    public function deleteSocialAccount(Request $request)
    {

        // Check
        if (! $request->id) {
            return Response::failWithMessage('warning', 'No account specified');
        }

        // Check
        $account = NotionSocialAccounts::where('id', $request->id)
            ->where('userid', Auth::id())
            ->first();

        if (! $account) {
            return Response::failWithMessage('warning', "We couldn't find the account you're trying to remove. Has it already been deleted?");
        }

        // Good to go
        $account->is_active = 0;
        $account->save();

        // Remove all scheduled posts that have this user
        $update = NotionPosts::where('account_id', $request->id)
            ->where('userid', Auth::id())
            ->where('status', 'scheduled')
            ->update([
                'is_active' => 0,
                'is_valid' => 0,
                'status' => 'deleted',
            ]);

        return Response::default();

    }

    public function deletePost(Request $request)
    {

        // Check
        if (! $request->id) {
            return Response::failWithMessage('warning', 'No post specified');
        }

        // Check
        $post = NotionPosts::where('id', $request->id)
            ->where('userid', Auth::id())
            // ->where('status', 'scheduled')
            ->first();

        if (! $post) {
            return Response::failWithMessage('warning', "We couldn't find the post you're trying to remove. Has it already been removed from the schedule?");
        }

        // Good to go
        $post->is_active = 0;
        $post->status = 'deleted';
        $post->save();

        // Dispatch a job to edit it in the DB
        UpdateNotionDatabaseEntry::dispatch(
            $post,
            'unschedule'
        );

        // Return
        return Response::default();

    }

    public function reschedulePost(Request $request)
    {

        // Check
        if (! $request->id) {
            return Response::failWithMessage('warning', 'No post specified');
        }

        // Check
        $post = NotionPosts::where('id', $request->id)
            ->where('userid', Auth::id())
            ->where('status', 'scheduled')
            ->first();

        if (! $post) {
            return Response::failWithMessage('warning', "We couldn't find the post you're trying to re-schedule. Has it already been removed from the schedule?");
        }

        // Dispatch a job to edit it in the DB
        UpdateNotionDatabaseEntry::dispatch(
            $post,
            'reschedule'
        );

        // Return
        return Response::default();

    }

    /**
     * TODO -
     * - Build scaffolding on existing database
     * - Build brand new scaffolding on existing page
     * - Add social accounts to database
     *
     * NOTE - DONE
     * - Scan account for new databases to add to
     * - Scan account for new pages to add to
     * - Re-factor PAGE and DATABASE scaffolding
     */
    public function template(Request $request)
    {

        // INIT
        $status = 'FAIL';
        $data = [];
        $errors = [];

        // Do stuff

        // All set? Return
        return Response::default($status, $data, $errors);
    }
}
