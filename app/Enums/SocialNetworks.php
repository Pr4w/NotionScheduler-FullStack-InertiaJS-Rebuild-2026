<?php

namespace App\Enums;

enum SocialNetworks
{

    // CASES
    case FACEBOOK;
    case INSTAGRAM;
    case TIKTOK;
    case LINKEDIN;
    case TWITTER;
    case THREADS;
    case YOUTUBE;

    // Function
    public function requirements(): object {

        return match($this) {
            // CASE - Facebook
            self::FACEBOOK => (object) [
                'video' => (object) [
                    // https://developers.facebook.com/docs/video-api/guides/publishing/#limitations-2 
                    'extensions' => ['mp4', 'mov'],
                    'min_duration' => 3,
                    'max_duration' => '1200', // 20 mins
                    'max_size' => '1073741824', // 1GB
                ],
                'reel' => (object) [
                    // https://developers.facebook.com/docs/video-api/guides/reels-publishing/#requirements 
                    'extensions' => ['mp4', 'mov'],
                    'min_height' => 960,
                    'min_width' => 540,
                    'max_height' => 1920, // FIXME - Added this but need to confirm it
                    'max_width' => 1080,
                    'min_duration' => 3,
                    'max_duration' => 90,
                    // Looks like it isn't possible to have a thumbnail for a FB Reel yet
                    // 'thumbnail' => (object) [
                    //     'extensions' => ['bmp','gif','jpeg','jpg','png','tiff'],
                    //     'max_size' => 10485760
                    // ]
                ],
                // https://developers.facebook.com/docs/video-api/guides/publishing/#thumbnail-image-requirements 
                'thumbnail' => (object) [
                    'extensions' => ['bmp','gif','jpeg','jpg','png','tiff'],
                    'max_size' => 10485760
                ],
                'story' => (object) [
                    // https://developers.facebook.com/docs/page-stories-api/#media-requirements 
                    'photo' => (object) [
                        'extensions' => ['jpeg', 'jpg', 'png', 'gif', 'tiff'],
                        'max_size' => 4194304 // TODO - Check
                    ],
                    'video' => (object) [
                        // https://developers.facebook.com/docs/video-api/guides/reels-publishing/#requirements 
                        'extensions' => ['mp4', 'mov'],
                        'min_height' => 960,
                        'min_width' => 540, // TODO - Try uploading a 4K story and see what happens? Set a max height and width
                        'max_height' => 1920, // FIXME - Added this but need to confirm it
                        'max_width' => 1080,
                        'min_duration' => 3,
                        'max_duration' => 60, 
                    ]
                ],
                'image' => (object) [
                    'extensions' => ['jpeg', 'jpg', 'png', 'gif', 'tiff'],
                    'max_size' => 4194304
                ]
            ],

            // CASE - Instagram
            // https://developers.facebook.com/docs/instagram-api/reference/ig-user/media#image-specifications
            self::INSTAGRAM => (object) [
                'image' => (object) [
                    'extensions' => ['jpg', 'jpeg', 'png'],
                    'max_size' => 8388608 // TODO - Test this?
                ],
                'reel' => (object) [
                    'extensions' => ['mp4', 'mov'],
                    'max_size' => '1073741824',
                    'max_height' => 1920,
                    'min_duration' => 3,
                    'max_duration' => 900, // 15 minutes
                ],
                'story' => (object) [
                    'photo' => (object) [
                        'extensions' => ['jpg', 'jpeg', 'png'],
                        'max_size' => 8388608 // TODO - Test this?
                    ],
                    'video' => (object) [
                        'extensions' => ['mp4', 'mov'],
                        'max_size' => '104857600',
                        'max_height' => 1920,
                        'min_duration' => 3,
                        'max_duration' => 60,
                    ]
                ],
                'caption' => (object) [
                    'max_characters' => 2200
                ],
                'carousel' => (object) [
                    'max_media' => 10
                ]
            ],

            // CASE - TikTok
            // https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide 
            self::TIKTOK => (object) [
                'image' => (object) [
                    'extensions' => ['jpeg', 'jpg', 'webp'], // TODO - Can we upload a .png?
                    'max_width' => 1080, // TODO - Test this
                    'max_size' => '20971520' // 20MB
                ],
                'video' => (object) [
                    'extensions' => ['mp4', 'webm', 'mov'],
                    'max_width' => 4096,
                    'max_height' => 4096,
                    'min_height' => 360,
                    'min_width' => 360,
                    'min_duration' => 3, // I made this up
                    'max_duration' => 180,
                    'max_size' => '4294967296' // 4GB
                ],
                // https://developers.tiktok.com/doc/content-posting-api-reference-direct-post 
                'caption' => (object) [
                    'max_characters' => 2000
                ]
            ],

            // CASE - LinkedIn
            self::LINKEDIN => (object) [
                // https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/images-api?view=li-lms-2024-04&tabs=http#schema 
                'image' => (object) [
                    'extensions' => ['jpg', 'jpeg', 'gif', 'png'],
                    'max_pixels' => '36152320', // Equivalent to a 6000x6000 image, chances of reaching this are slim
                ],

                // https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api?view=li-lms-2024-04&tabs=http#video-file-size-specifications
                'video' => (object) [
                    'extensions' => ['mp4'], // TODO - Test .mov for example?
                    'max_size' => '209715200', // 200MB
                    'min_duration' => 3,
                    'max_duration' => 1800 // 30 minutes
                ],

                // https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/documents-api?view=li-lms-2024-04&tabs=http#schema 
                'document' => (object) [
                    'extensions' => ['pdf','doc','docx','ppt','pptx'],
                    'max_size' => '104857600' // 100MB
                ],

                'caption' => (object) [
                    'max_characters' => 3000
                ]
            ],

            // CASE - Twitter
            // https://docs.x.com/x-api/media/quickstart/best-practices 
            self::TWITTER => (object) [
                'caption' => (object) [
                    'max_characters' => 280
                ],
                'image' => (object) [
                    'max_count' => 4,
                    'extensions' => ['jpg', 'png', 'gif', 'webp'],
                    'max_size' => '5242880', // 5MB 
                ],
                'gif' => (object) [
                    'extensions' => ['gif'],
                    'max_size' => '15728640', // 15 MB
                ],
                'video' => (object) [
                    'extensions' => ['mp4'],
                    'max_size' => '536870912', // 512 MB
                    'min_duration' => 1,
                    'max_duration' => 140,
                    'max_height' => 1280,
                    'max_width' => 1280
                ],
            ],

            // CASE - YouTube
            self::YOUTUBE => (object) [
                'video' => (object) [
                    'extensions' => ['mov', 'mp4', 'avi']
                ],
                'title' => (object) [
                    'max_characters' => 100
                ]
            ],

            // CASE - Threads
            // https://developers.facebook.com/docs/threads/overview 
            self::THREADS => (object) [
                'caption' => (object) [
                    'max_characters' => 500
                ],
                'carousel' => (object) [
                    'max_media' => 20
                ],
                'image' => (object) [
                    'extensions' => ['jpg', 'jpeg', 'png'],
                    'max_size' => 8388608 // 8MB, 31457280, // 8388608 // 8MB
                ],
                'video' => (object) [
                    'extensions' => ['mp4', 'mov'],
                    'max_size' => '1073741824', // 1 GB
                    'max_height' => 1920,
                    'min_duration' => 1,
                    'max_duration' => 300,
                ]
            ]

        };

    }



}

?>