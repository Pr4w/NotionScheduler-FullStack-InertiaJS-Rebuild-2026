<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

use App\Models\NotionAccessTokens;
use App\Models\NotionDatabases;
use App\Models\NotionSocialAccounts;
use App\Models\NotionPosts;
use App\Models\AccessTokens;
use App\Models\NotionSocialAccountsAccessTokens;

use App\Models\ErrorManager;
use App\Models\NotionScaffolding;
use App\Models\User;

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

use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{

   public function returnStatsForFrontend()
    {
        return Cache::remember('frontend_stats', now()->addDay(), function () {
            return [
                'users' => User::count(),
                'published_posts' => NotionPosts::where('status', 'posted')->count(),
            ];
        });
    }

    public function getUserCount() {
        return User::count();
    }


    public function notifySupervisorRestart() {

        Log::error("AdminController - It looks like your root Crontab script has restarted the Supervisorctl script at " . Carbon::now()->toDateTimeString());

    }


    public function facebookDebugToken(string $token = null) {

        if (!$token) {
            die("No token passed");
        }

        // $do = NotionSocialAccounts::facebookGetAllScopesAndAccounts($token);
        // dump($do);

        // Get an app token
        $app_token = NotionSocialAccountsAccessTokens::getAppAccessToken();

        // Lets debug our token
        $response = Http::facebook()->get('debug_token', [
            'input_token' => $token,
            'access_token' => $app_token
        ]);

        dump($response->json());


    }

    public function debugDatabase(string $databaseid = null) {

        // Check if we have a post ID
        if (!$databaseid) {
            return Response::failWithMessage(
                'warning', 
                'No ID provided'
            );
        }

        // Get the Notion token and associated stuff
        $notion_database = NotionDatabases::with('token')->where('id', $databaseid)->first();

        // Check
        if (!$notion_database) {
            return Response::failWithMessage(
                'warning', 
                "Couldn't get DB and token"
            );
        }

        // Create Notion object
        $notion = Notion::create($notion_database->token->token);

        // Get the database
        $database = $notion->databases()->find($notion_database->database_id);

        dump($database);

        $pages = $notion->databases()->queryAllPages($database);

        $app_url = url('/app');

        foreach ($pages as $page) {

            echo "<a href='$app_url/admin/debugExternalPost/".$notion_database->userid."/".$notion_database->id."/".$page->id."'>" . $page->title()->toString() . "</a><br />";

        }

        return [
            'properties' => $database->properties()->getAll()
        ];



    }


    public function debugPost(string $postid = null) {

        // Check if we have a post ID
        if (!$postid) {
            return Response::failWithMessage(
                'warning', 
                'No ID provided'
            );
        }

        // Grab the post
        $post = NotionPosts::where('id', $postid)
            ->first();

        // Check if we actually found it 
        if (!$post) {
            return Response::failWithMessage(
                'warning', 
                "Couldn't find the post you're looking for"
            );
        }

        // Get the Notion token and associated stuff
        $notion_database = NotionDatabases::with('token')->where('id', $post->database_id)->first();

        // Check
        if (!$notion_database) {
            return Response::failWithMessage(
                'warning', 
                "Couldn't get DB and token"
            );
        }

        // Create Notion object
        $notion = Notion::create($notion_database->token->token);

        // Get the page content
        $contents = $notion->blocks()->findChildrenRecursive($post->post_page_id);
        $content_final = NotionPosts::getAllContentFromChildren($contents);

        // Load the scaffolding
        $scaffolding = NotionDatabases::getDefaultScaffolding();

        // Load the page so we can get the media and whatnot
        $page = $notion->pages()->find($post->post_page_id);

        $media_base = $page->properties()->getById($notion_database->column_media)->files;
        $media = NotionPosts::getAllMediaFromProps2($media_base);

        $thumbnail_base = $page->properties()->getById($notion_database->column_media_thumbnail)->files;
        $thumbnail = NotionPosts::getThumbnailFromProps2($thumbnail_base);

        $date = $page->properties()->getById($notion_database->column_post_date)->start();
        
        // Get the date
        // $date = $props[
        //     $scaffolding['properties']['schedule_post_date']['name']
        // ]['date']['start'];
        $date = Carbon::parse($date)->setTimezone(
            date_default_timezone_get()
        );

        return Response::default(
            'OK',
            [
                'notion' => [
                    'content' => $content_final,
                    'media' => $media,
                    'thumbnail' => $thumbnail,
                    'date' => $date
                ],
                'identifiers' => $post
            ],
            []
        );

    }

    public function debugUser(string $userId = null) {

        // Check if we have a post ID
        if (!$userId) {
            return Response::failWithMessage(
                'warning', 
                'No ID provided'
            );
        }

        // Check user
        $user = User::find($userId);
        if (!$user) {
            return Response::failWithMessage(
                'warning', 
                'User not found'
            );
        }

        // Get databases
        $databases = $user->databases()->where('is_active', 1)->where('is_valid', 1)->get();
        if (!$databases->count()) {
            return Response::failWithMessage(
                'warning', 
                'User has no active or valid databases'
            );
        }

        // Print
        echo "<h1>User report</h1>";
        dump($user);

        $app_url = url('/app');

        foreach ($databases as $db) {
            echo "<a href='$app_url/admin/debugDatabase/" . $db->id . "'>Database with ID ".$db->id."</a><br />";
        }




    }

    public function debugExternalPost(string $userId, string $dbId, string $postId) {

        // Get user
        $user = User::find($userId);
        if (!$user) {
            return Response::failWithMessage(
                'warning', 
                'User not found'
            );
        }

        // Get the Notion token and associated stuff
        $notion_database = NotionDatabases::with('token')->where('id', $dbId)->where('userid', $userId)->first();

        // Check
        if (!$notion_database) {
            return Response::failWithMessage(
                'warning', 
                "Couldn't get DB and token"
            );
        }

        // Create Notion object
        $notion = Notion::create($notion_database->token->token);

        // Get the page content
        $contents = $notion->blocks()->findChildrenRecursive($postId);
        $content_final = NotionPosts::getAllContentFromChildren($contents);

        // Load the scaffolding
        $scaffolding = NotionDatabases::getDefaultScaffolding();

        // Load the page so we can get the media and whatnot
        $page = $notion->pages()->find($postId);

        $media_base = $page->properties()->getById($notion_database->column_media)->files;
        $media = NotionPosts::getAllMediaFromProps2($media_base);

        $thumbnail_base = $page->properties()->getById($notion_database->column_media_thumbnail)->files;
        $thumbnail = NotionPosts::getThumbnailFromProps2($thumbnail_base);

        $date = $page->properties()->getById($notion_database->column_post_date)->start();

        
        dump($page);
        dump($contents);
        dump($content_final);

        // NOTE - Sanitize some of the inputs that LinkedIn doesn't like
        $sanicontent = preg_replace_callback('/([\(\)\{\}\[\]])|([@*<>\\\\\_~])/m', function ($matches) {
            return '\\'.$matches[0];
        }, $content_final);
        $content_final = $sanicontent;

        try {
            $urlHighlight = new UrlHighlight();
            $urls = $urlHighlight->getUrls($content_final);

            $new_urls = [];

            // Loop through them
            foreach ($urls as $url) {

                // Create unit
                $unit = Str::of($url);

                // Check
                if ($unit->contains('linkedin.com/in/')) {
                    $type = 'person';
                } elseif ($unit->contains('linkedin.com/company/')) {
                    $type = 'org';
                } elseif ($unit->contains('linkedin.com/school/')) {
                    $type = 'org';
                } else {
                    if ($unit->contains('linkedin.com/')) {
                        Log::error("MISSING LINKEDIN CONTAINER URL");
                        Log::error($unit);
                    }

                    continue;
                    
                }

                $clean_url = $unit->after('linkedin.com/in/')
                    ->after('linkedin.com/company/')
                    ->after('linkedin.com/school/')
                    ->before('?')
                    ->before('/')
                    ->before('#');

                $new_urls[] = [
                    'raw' => $url,
                    'clean' => $clean_url,
                    'type' => $type
                ];

            }
            // INIT
            $token = NotionSocialAccounts::where('id', '17') // FIXME
                ->with('access_token')
                ->first();

            foreach ($new_urls as $k => $url) {

                // CASE - Person
                if ($url['type'] == 'person') {
                    $response = Http::linkedin()->withToken($token->access_token->access_token)
                    ->withHeaders([
                        'LinkedIn-Version' => '202404',
                        'X-RestLi-Protocol-Version' => '2.0.0'
                    ])
                    ->get('https://api.linkedin.com/rest/vanityUrl', [
                        'q' => 'vanityUrlAsOrganization',
                        'vanityUrl' => 'https://www.linkedin.com/in/' . $url['clean'],
                        'organization' => $token->account_full_identifier
                    ]);
                    $rep = $response->json();
                    if (!$response->successful()) {
                        Log::error(125);
                        Log::error($rep);
                        Log::error($response);
                    } else {
                        if (isset($rep['elements'][0])) {
                            $urn = $rep['elements'][0]['member'];

                            $qurl = 'https://api.linkedin.com/rest/people/(id:' . Str::of($urn)->afterLast(':') . ')';
                            $response = Http::linkedin()->withToken($token->access_token->access_token)
                            ->withHeaders([
                                'LinkedIn-Version' => '202404',
                                'X-RestLi-Protocol-Version' => '2.0.0'
                            ])
                            ->get($qurl);
                            $rep = $response->json();
                            if (!$response->successful()) {
                                Log::error(149);
                                Log::error($rep);
                                Log::error($response);
                            } else {
                                
                                $displayName = $rep['localizedFirstName'] . ' ' . $rep['localizedLastName'];

                                $new_urls[$k]['urn'] = $urn;
                                $new_urls[$k]['displayName'] = $displayName;
                            }

                        }
                    }
                } 

                // CASE - Org
                if ($url['type'] == 'org') {
                    $response = Http::linkedin()->withToken($token->access_token->access_token)
                    ->withHeaders([
                        'LinkedIn-Version' => '202404',
                        'X-RestLi-Protocol-Version' => '2.0.0'
                    ])
                    ->get('https://api.linkedin.com/v2/organizations', [
                        'q' => 'vanityName',
                        'vanityName' => $url['clean'],
                    ]);
                    $rep = $response->json();

                    if (!$response->successful()) {
                        Log::error(150);
                        Log::error($rep);
                        Log::error($response);
                    } else {

                        if (isset($rep['elements'])) {
                            if (count($rep['elements']) > 0) {
                                $urn = 'urn:li:organization:' . $rep['elements'][0]['id'];
                                $displayName = $rep['elements'][0]['localizedName'];
                                $new_urls[$k]['urn'] = $urn;
                                $new_urls[$k]['displayName'] = $displayName;

                            }
                        }
                    }
                }
            }

            $content_final = Str::of($content_final);
            foreach ($new_urls as $nurl) {
                if (isset($nurl['displayName'])) {
                    $format = '@[' . $nurl['displayName'] . '](' . $nurl['urn'] . ')';
                    $content_final = $content_final->replaceFirst($nurl['raw'], $format);
                } else {
                    Log::info("1686");
                    Log::info("Missing displayName");
                    Log::info($nurl);
                }
                
            }
        } catch (Throwable $e) {
            Log::info($e);
        }

        dump($content_final);



    }
    




}