<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for the official Cloudinary PHP SDK. The CLOUDINARY_URL takes
    | the form cloudinary://<api_key>:<api_secret>@<cloud_name>.
    |
    */

    'cloud_url' => env('CLOUDINARY_URL'),

    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),

];
