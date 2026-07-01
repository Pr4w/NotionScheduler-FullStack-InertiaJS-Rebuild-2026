<?php

namespace App\Models;

use App\Support\Address;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Model;

use Notion\Common\Color;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\NotionSocialAccountsAccessTokens;

use Auth;

class NotionSocialAccounts extends Model
{
    // Define the table name for this model
    protected $table = 'notion_social_accounts';
    protected $primaryKey = 'id';

    protected $hidden = [
        'userid',
        'token_id',
    ];

    protected $fillable = [
        'userid',
        'account_id',
        'account_full_identifier',
        'token_id',
        'option_select_id',
        'platform',
        'name',
        'profile_picture',
        'is_active',
        'is_valid',
        'followers',
        'followings',
        'engagement',
        'post_count',
        'metrics_last_scraped_at',
    ];

    protected $casts = [
        'metrics_last_scraped_at' => 'datetime',
    ];

    static $slug_separator = "@";

    static $platforms = [
        'instagram' => [
            'slug' => 'Instagram',
            'color' => Color::Red,
        ],
        'facebook' => [
            'slug' => 'Facebook',
            'color' => Color::Blue,
        ],
        'twitter' => [
            'slug' => 'Twitter',
            'color' => Color::Blue,
        ],
        'linkedin' => [
            'slug' => 'LinkedIn',
            'color' => Color::Gray,
        ],
        'tiktok' => [
            'slug' => 'TikTok',
            'color' => Color::Gray
        ],
        'threads' => [
            'slug' => 'Threads',
            'color' => Color::Gray
        ],
        'youtube' => [
            'slug' => 'YouTube',
            'color' => Color::Red,
        ],
        // 'linkedin_page' => [
        //     'slug' => 'LinkedIn',
        //     'color' => Color::Gray,
        // ]
    ];


    public static function getColor($platform) {
        $cases = self::$platforms;
        if (isset($cases[$platform])) {
            return $cases[$platform]['color'];
        }
        return Color::Default;
    }

    public static function createSlug($platform, $username, $id) {
        $social = self::$platforms;

        $prefix = 'DEFAULT';
        if (isset($social[$platform])) {
            $prefix = $social[$platform]['slug'];
        }

        // Clean up the username
        $username = Str::of($username)->replace(',', '');

        $return = $prefix . self::$slug_separator . $username . ' #ID_' . $id;

        return $return;

    }

    public static function getColorFromSlug($slug) {

        $slug = explode(self::$slug_separator, $slug)[0];
        foreach (self::$platforms as $key => $platform) {
            if ($slug == $platform['slug']) {
                return $platform['color'];
            }
        }
        return Color::Gray;

    }

    public static function getAllSlugsFromUser($database_id, $userid) {

        $slugs = [];
        $social_accounts = NotionSocialAccounts::where('userid', $userid)
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->where('database_id', $database_id)
            ->get();

        // Check if we actually have results
        if ($social_accounts->count()) {
            
            // Get all the slugs
            foreach ($social_accounts as $social_account) {
                $slugs[
                    NotionSocialAccounts::createSlug(
                        $social_account->platform,
                        $social_account->name,
                        $social_account->id
                    )
                ] = [
                        'id' => $social_account->id,
                        'username' => $social_account->name,
                        'platform' => $social_account->platform
                    ];
            }

            return $slugs;

        }

        return [];

    }


    public static function getTwitterRefreshToken($refresh_token) {

        // Set twitter Auth token
        $auth = base64_encode(Config::get('services.twitter-oauth-2.client_id') . ':' .Config::get('services.twitter-oauth-2.client_secret'));

        // Perform query
        $req = Http::asForm()
            ->withHeaders(
                [
                    'Authorization' => "Basic $auth"
                ]
            )
            ->post('https://api.twitter.com/2/oauth2/token', [
                "grant_type" => "refresh_token",
                "refresh_token" => $refresh_token,
                "client_id" => Config::get('services.twitter-oauth-2.client_id')
        ]);

        // Check
        if ($req->ok()) {

            return $req->json();

        } else {

            return $req->ok();

        }

    }

    public static function getYoutubeRefreshToken($account) {

        

    }

    public function access_token(): HasOne {
        return $this->hasOne(NotionSocialAccountsAccessTokens::class, 'id', 'token_id');
    }

    public static function getPersonalProfilePictureFromLinkedIn($rep) {

        // Get the profile picture
        $profile_picture = null;

        // Look through the array
        if (isset($rep['profilePicture']['displayImage~']['elements'])) {
            if (count($rep['profilePicture']['displayImage~']['elements']) > 0) {
                $size = 0;
                $ppurl = null;
                foreach ($rep['profilePicture']['displayImage~']['elements'] as $profilePictureElements) {
                    if ($profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'] > $size) {
                        if (isset($profilePictureElements['identifiers'][0]['identifier']) && isset($profilePictureElements['identifiers'][0]['identifierType'])) {
                            if ($profilePictureElements['identifiers'][0]['identifierType'] == 'EXTERNAL_URL') {
                                $size = $profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'];
                                $ppurl = $profilePictureElements['identifiers'][0]['identifier'];
                            }
                        }
                    }
                }
                if ($ppurl) {
                    $profile_picture = $ppurl;
                }

            }
        }

        // Return
        return $profile_picture;

    }

    public static function getOrgProfilePictureFromLinkedIn($org) {

        // Set
        $org_picture = null;

        // Lookup
        if (isset($org['organization~']['logoV2']['original~']['elements'])) {
            $size = 0;
            $ppurl = null;
            foreach ($org['organization~']['logoV2']['original~']['elements'] as $logoV2) {
                if ($logoV2['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'] > $size) {
                    if (isset($logoV2['identifiers'][0]['identifier']) && isset($logoV2['identifiers'][0]['identifierType'])) {
                        if ($logoV2['identifiers'][0]['identifierType'] == 'EXTERNAL_URL') {
                            $size = $logoV2['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'];
                            $ppurl = $logoV2['identifiers'][0]['identifier'];
                        }
                    }
                }

            }
            if ($ppurl) {
                $org_picture = $ppurl;
            }
        }

        // Return
        return $org_picture;

    }

    public static function facebookGetAllScopesAndAccounts(
        $access_token
    ) {

        // Obtain all the accounts we can get from that access token
        $response = Http::facebook()->get('me/accounts', [
            'access_token' => $access_token,
            // 'fields' => 'access_token,name,id,picture,instagram_business_account{id,username,profile_picture_url}'
            'fields' => 'access_token,name,id,picture,fan_count,followers_count,instagram_business_account{id,username,profile_picture_url,followers_count,follows_count,media_count,media{id,like_count,comments_count}}'
        ]);

        // Check
        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => "failed_to_get_accounts",
                'error' => $response->json()
            ];
        } else {

            // Make pretty
            $rep = $response->json();

            // Check
            if (count($rep['data']) < 1) {
                Log::info("Accounts_data_empty - NotionSocialAccounts");
                Log::info($rep);
                return [
                    'success' => false,
                    'message' => "accounts_data_empty"
                ];
            } else {
                
                // Create an array of accounts to add
                $accounts = [];
                $ig_ids = [];
                $fb_ids = [];
                foreach ($rep['data'] as $account) {


                    if (isset($account['instagram_business_account'])) {
                        // Shorthand
                        $ig = $account['instagram_business_account'];

                        // Calculate
                        $followers = $ig['followers_count'] ?? 0;
                        $followings = $ig['follows_count'] ?? 0;
                        $post_count = $ig['media_count'] ?? 0;

                        $engagement = 0;
                        if (isset($ig['media']['data'])) {
                            if (count($ig['media']['data']) > 0) {
                                $likes = [];
                                foreach ($ig['media']['data'] as $igm) {
                                    if (isset($igm['like_count'])) {
                                        if ($igm['like_count'] > 0) {
                                            $likes[] = $igm['like_count'];
                                        }
                                    }
                                }

                                if (count($likes) > 0) {
                                    $likes_average = array_sum($likes) / count ($likes);
                                    if ($likes_average > 0 && $followers > 0) {
                                        $engagement = round($likes_average / $followers * 100, 2);
                                    }
                                }
                            }
                        }


                        $accounts[$ig['id']] = [
                            'platform' => 'instagram',
                            'profile_picture' => $ig['profile_picture_url'] ?? null,
                            'account_id' => $ig['id'],
                            'name' => $ig['username'],

                            'followers' => $followers,
                            'followings' => $followings,
                            'engagement' => $engagement,
                            'post_count' => $post_count,
                        ];
                        $ig_ids[] = $ig['id'];
                    }

                    // Calculate
                    $followers = $account['followers_count'] ?? 0;
                    $followings = 0;
                    $engagement = 0; // FIXME
                    $post_count = 0; // FIXME

                    $accounts[$account['id']] = [
                        'platform' => 'facebook',
                        'profile_picture' => $account['picture']['data']['url'],
                        'account_id' => $account['id'],
                        'name' => $account['name'],
                        'page_access_token' => $account['access_token'], // Get the page access token

                        'followers' => $followers,
                        'followings' => $followings,
                        'engagement' => $engagement,
                        'post_count' => $post_count
                    ];
                    $fb_page_ids[] = $account['id'];

                }

                // Now lets do an OAUTH Debug on our token
                $app_token = NotionSocialAccountsAccessTokens::getAppAccessToken();

                // Perform OAuthDebug query to check what scopes we have on the token
                $response = Http::facebook()->get('debug_token', [
                    'input_token' => $access_token,
                    'access_token' => $app_token
                ]);

                // Check
                if (!$response->ok()) {
                    return [
                        'status' => 'FAIL',
                        'message' => "fail_debug_token"
                    ];
                    Log::info("NotionSocialAccounts 310 - Fail");
                    Log::info($response->json());
                } else {

                    // Make pretty
                    $oauth_debug = $response->json()['data'];

                    // Lets run through our scopes
                    $required_scopes_base = Config::get('services.facebook.scopes');

                    // Make a pretty array
                    $required_scopes_detailed = [];

                    // Make a pretty array of scpês
                    $ig_scopes = Config::get('services.facebook.ig_scopes');
                    $fb_scopes = Config::get('services.facebook.fb_scopes');
                    foreach ($required_scopes_base as $req_scope) {
                        $plat = 'facebook';
                        if (in_array($req_scope, $ig_scopes)) {
                            $plat = 'instagram';
                        }
                        $required_scopes_detailed[$req_scope] = $plat;
                    }

                    // Loop through all the granular scopes
                    $scopes_received = [];

                    // Check if the business management scope is there
                    $add_business_management = false;
                    if (in_array('business_management', $oauth_debug['scopes'])) {
                        $add_business_management = true;
                    }

                    foreach ($oauth_debug['granular_scopes'] as $granular_scope) {

                        // What kind of scope is it?
                        $scope_name = $granular_scope['scope'];

                        // Ignore the business_management scope
                        if ($scope_name == 'business_management') {
                            continue;
                        }

                        // Check if it's in the array of required scopes
                        if (isset($required_scopes_detailed[$scope_name])) {

                            // What platform is it for?
                            $scope_platform = $required_scopes_detailed[$scope_name];

                            // CASE 1 - We have TARGET IDs for the scopes
                            if (isset($granular_scope['target_ids'])) {

                                foreach ($granular_scope['target_ids'] as $target_id) {
                                    $scopes_received[$target_id]['scopes'][] = $scope_name;
                                    $scopes_received[$target_id]['platform'] = $scope_platform;

                                    if ($add_business_management
                                        && $scope_platform === 'facebook'
                                        && !in_array('business_management', $scopes_received[$target_id]['scopes'], true)) {
                                        $scopes_received[$target_id]['scopes'][] = 'business_management';
                                    }
                                }


                            // CASE 2 - We don't have target IDs for the scope
                            // This means that these scopes apply to all accounts for that token
                            } else {

                                if ($scope_platform == 'facebook') {
                                    foreach ($fb_page_ids as $fb_page_id) {
                                        $scopes_received[$fb_page_id]['scopes'][] = $scope_name;
                                        $scopes_received[$fb_page_id]['platform'] = $scope_platform;
                                        // Add the business_management scope if required
                                        if ($add_business_management && $scope_platform == 'facebook' && !in_array('business_management', $scopes_received[$fb_page_id]['scopes'])) {
                                            $scopes_received[$fb_page_id]['scopes'][] = 'business_management';
                                        }
                                    }
                                } else {
                                    foreach ($ig_ids as $ig_id) {
                                        $scopes_received[$ig_id]['scopes'][] = $scope_name;
                                        $scopes_received[$ig_id]['platform'] = $scope_platform;
                                    }
                                }
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'data' => [
                            'scopes_received' => $scopes_received,
                            'accounts' => $accounts
                        ]
                    ];
                }
            }
        }

    }


    // protected $fillable = [
    //     'ig_postid',

    //     'ig_userid',
    //     'shortcode',
    //     'fb_product_type',
    //     'type',
    //     'hashtags',
    //     'content',
    //     'post_caption',
    //     'post_timestamp',
    //     'post_date',
    //     'thumbnail',
    // ];
    
}