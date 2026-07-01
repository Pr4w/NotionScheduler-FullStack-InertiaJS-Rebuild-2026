<?php

// Base for same-origin OAuth callbacks. Old app used https://api.notionscheduler.app/social/{provider}/callback;
// unified app serves these under /app/connect/{provider}/callback on APP_URL.
$oauthBase = rtrim((string) env('APP_URL', 'http://localhost'), '/').'/app/connect';

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'api_base_url' => 'https://graph.facebook.com/v25.0/',
        'graph_version' => 'v25.0',
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/facebook/callback',
        'scopes' => [
            'instagram_content_publish',
            'instagram_basic',
            'instagram_manage_insights',
            'pages_show_list',
            'business_management',
            'pages_manage_posts',
            'pages_read_engagement',
        ],
        // Scopes are split: only add an IG account if it has all IG scopes, same for Facebook pages.
        'ig_scopes' => ['instagram_content_publish', 'instagram_basic', 'instagram_manage_insights'],
        'fb_scopes' => ['pages_show_list', 'business_management', 'pages_manage_posts', 'pages_read_engagement'],
    ],

    // FIXME - This whole section is unused (kept for parity with the old API).
    'instagram' => [
        'api_base_url' => 'https://graph.facebook.com/v25.0/',
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/facebook/callback',
        'scopes' => [
            'instagram_content_publish',
            'instagram_basic',
            'instagram_manage_insights',
            'pages_show_list',
            'business_management',
            'pages_manage_posts',
        ],
    ],

    // NOTE - For personal page posting
    'linkedin-openid' => [
        'client_id' => env('LINKEDIN_OPENID_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_OPENID_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/linkedin/callback',
        'scopes' => [
            'openid',
            'profile',
            'w_member_social',
        ],
    ],

    // NOTE - For PAGE posting
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/linkedin-pro/callback',
        'scopes' => [
            'r_basicprofile',
            'rw_organization_admin',
            'w_member_social',
            'w_organization_social',
            'r_organization_social',
            'r_organization_followers',
            'r_member_postAnalytics',
            'r_member_profileAnalytics',
            'r_organization_social_feed',
            'r_1st_connections_size',
        ],
        'access_roles_to_post' => [
            'ADMINISTRATOR',
            'RECRUITING_POSTER',
            'CONTENT_ADMINISTRATOR',
        ],
    ],

    // NOTE - Threads
    'threads' => [
        'client_id' => env('THREADS_CLIENT_ID'),
        'client_secret' => env('THREADS_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/threads/callback',
        'scopes' => [
            'threads_basic',
            'threads_content_publish',
            'threads_manage_insights',
        ],
    ],

    'twitter-oauth-2' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/twitter/callback',
        'scopes' => [
            'tweet.read',
            'tweet.write',
            'users.read',
            'offline.access',
            'media.write',
        ],
        'api_key' => env('TWITTER_API_KEY'),
        'api_key_secret' => env('TWITTER_API_KEY_SECRET'),
        'bearer' => env('TWITTER_BEARER_TOKEN'),
        'access_token' => env('TWITTER_ACCESS_TOKEN'),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
    ],

    // NOTE - This is the official NOTION INTEGRATION
    'notion' => [
        'client_id' => env('NOTION_CLIENT_ID'),
        'client_secret' => env('NOTION_CLIENT_SECRET'),
        'verification_secret' => env('NOTION_VERIFICATION_SECRET'),
        'redirect' => $oauthBase.'/notion/callback',
    ],

    // NOTE - This is the TEST INTEGRATION
    'notion_test' => [
        'client_id' => env('NOTION_TEST_CLIENT_ID'),
        'client_secret' => env('NOTION_TEST_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/notion/callback',
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/tiktok/callback',
        'scopes' => [
            'user.info.basic',
            'user.info.profile',
            'user.info.stats',
            'video.publish',
            'video.upload',
            'video.list',
        ],
        'video_types' => ['video/mp4', 'video/quicktime', 'video/quicktime'],
    ],

    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect' => $oauthBase.'/youtube/callback',
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/youtube.upload',
        ],
    ],

    // Same-origin now: OAuth callbacks redirect back into the app (/app/...).
    // Old code used services.frontend.url as the redirect base; keep it pointing
    // at this app's own URL.
    'frontend' => [
        'url' => env('FRONTEND_URL', env('APP_URL')),
    ],

];
