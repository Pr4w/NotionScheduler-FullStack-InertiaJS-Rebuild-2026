<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class NotionSocialAccountsAccessTokens extends Model
{
    // Define the table name for this model
    protected $table = 'notion_social_accounts_tokens';
    protected $primaryKey = 'id';

    protected $hidden = [
        'userid',
    ];

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

    public static function getAppAccessToken() {

        $response = Http::facebook()->get('oauth/access_token', [
            'client_id' => Config::get('services.facebook.client_id'), 
            'client_secret' => Config::get('services.facebook.client_secret'),
            'grant_type' => 'client_credentials'
        ]);

        return $response->json()['access_token'];

    }
    
}