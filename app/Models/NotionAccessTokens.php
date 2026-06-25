<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotionAccessTokens extends Model
{
    // Define the table name for this model
    protected $table = 'notion_access_tokens';
    protected $primaryKey = 'id';

    protected $hidden = [
        'userid',
        'token',
        'notion_user_id',
        'workspace_id',
        'expiry_date',
        'is_active',
        'is_valid'
    ];

    protected $fillable = [
        'userid',
        'notion_user_id',
        'workspace_id',
        'nickname',
        'token',
        'expiry_date',
        'is_valid',
        'is_active'
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
    
}
