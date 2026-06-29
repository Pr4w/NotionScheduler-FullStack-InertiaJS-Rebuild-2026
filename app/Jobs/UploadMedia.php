<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\NotionPosts;
use App\Models\User;
use App\Models\NotionSocialAccounts;

use App\Enums\SocialNetworks;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

use App\Jobs\UpdateNotionPostInDatabaseAfterUpload;
use App\Jobs\UploadMediaInstagramDelayedPublication;

use VStelmakh\UrlHighlight\UrlHighlight;

use Carbon\Carbon;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Throwable;

use OpenGraph;


/**
 * 
 * NOTE -
 * 
 * 
 * 
 * NOTE - This isn't the only job that runs checks against the post to validate that everything is correct, the FindNotionPostsInDB also runs those checks, so any new check in one should be made in the other
 * 
 * Example - If I enable posting videos to linkedin I'll need to enable it in both scripts, if not then posts won't get picked up...
 * 
 * 
 * 
 * NOTE - 
 */

class UploadMedia implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 50;
    public string $error_message;
    // public array $file_types;
    public $requirements;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotionPosts $post,
        public $social_account,
        public $content, // The caption
        public $media,
        public $thumbnail
    )
    {
        
        // $this->file_types = [
        //     'images' => NotionPosts::getImageFileTypes(),
        //     'videos' => NotionPosts::getVideoFileTypes()
        // ];
        
        // Set default
        $this->error_message = "";

        // Switch the platform
        $this->requirements = match($this->post->platform) {
            'facebook' => SocialNetworks::FACEBOOK->requirements(),
            'instagram' => SocialNetworks::INSTAGRAM->requirements(),
            'tiktok' => SocialNetworks::TIKTOK->requirements(),
            'linkedin' => SocialNetworks::LINKEDIN->requirements(),
            'twitter' => SocialNetworks::TWITTER->requirements(),
            'threads' => SocialNetworks::THREADS->requirements(),
            'youtube' => SocialNetworks::YOUTUBE->requirements(),
        };

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
        return $this->post->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // Set the context
        Log::withContext([
            'origin' => 'UploadMedia Job',
            'post_id' => $this->post->id,
            'post_platform' => $this->post->platform,
            'post_name' => $this->post->post_name,
            'post user id' => $this->post->userid,
            'status' => $this->post->status
        ]);
        
        // Show that we're logging
        Log::info("Performing job for post with ID " . $this->post->id . " for attempt #" . $this->attempts());

        /**
         * SECTION - FIX - For some reason some old posts were being re-submitted, wtf????
         */
        if ($this->post->status == 'posted') {
            Log::error(179);
            Log::info("Post is ALREADY posted, STOP THIS MADNESS");
            return;
        }

        /**
         * SECTION - Check the scheduled date to make sure it's less than a week old
         */
        try {

            $scheduled_date = Carbon::parse($this->post->scheduled_date);
            if ($scheduled_date->between(now()->subWeek(), now())) {

                // Do nothing, post is correctly scheduled

            } else {
                Log::error("Post iS NOT scheduled to be posted within the last 7 days");
                Log::error($this->post);
                Log::error("It's scheduled for " . $scheduled_date->format('Y-m-d H:i:s'));
                Log::error("Setting it's status to invalid and moving on");
                $this->post->is_valid = 0;
                $this->post->in_flight = 0;
                $this->post->save();
                return;
            }


        } catch (\Throwable $e) {
            Log::info($e);
        }


        /**
         * SECTION - Pre-flight checks
         */
        $checks = NotionPosts::checkPostIsValid(
            $this->post,
            $this->content,
            $this->media,
            $this->thumbnail
        );
        if (!$checks['success']) {
            $this->error_message = implode('-', $checks['errors']);
            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                false,
                $this->error_message,
                $this->post
            );
            return;
        }

        // Get the result of the probe
        $probe = $checks['probe'];

        // CASE - Check that user account is valid, in case it failed just before posting, or was removed by the user but the post is still scheduled somehow?
        if (!$this->social_account->is_active or !$this->social_account->is_valid) {
            $this->error_message = "The account you tried posting to is either not working or was removed from your account. You can resolve this by heading to your NotionScheduler Dashboard.";
            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                false, // Success level
                $this->error_message, // Message we want to share?
                $this->post, // The post object,
            );
            return;
        }





        /**
         * SECTION - Facebook
         */
        if ($this->post->platform == 'facebook') {

            // Post params
            $params = [
                'message' => trim($this->content),
                'access_token' => $this->social_account->access_token->access_token_page
            ];

            // CASE - No media
            if (count($this->media) < 1) {

                $urlHighlight = new UrlHighlight();
                $urls = $urlHighlight->getUrls($this->content);

                // Check if we have URLS
                if (count($urls) > 0) {
                    $params['link'] = $urls[0];
                }

                // Make the actual query
                $response = Http::facebook()->post($this->social_account->account_id . "/feed", $params);

                // Check the result
                if (!$response->ok()) {
                    $this->errorHandler(200, $response);
                    return;
                } else {

                    // Make pretty
                    $rep = $response->json();

                    // Get the foreign id
                    $foreign_id = $rep['id'];

                    // Now mark the post as posted
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        true, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post, // The post object,
                        $foreign_id
                    );
                    return;
                }

            // CASE - Has media
            } else {

                // Make pretty
                $media_file = $this->media[0];

                // CASE - Single video post
                if (in_array($media_file['extension'], $this->requirements->video->extensions)) {

                    $aspect_ratio = $probe['aspect_ratio'];

                    // Switch the case
                    if ($aspect_ratio == 16/9) {
                        $video_type = "classic";
                    } elseif ($aspect_ratio == 9/16) {
                        $video_type = "reel";
                    } else {
                        $video_type = "classic";
                    }

                    // CASE - Reel
                    if ($video_type == 'reel') {

                        // Switch is_story
                        $endpoint = '/video_reels';
                        if ($this->post->platform_is_story) {
                            $endpoint = '/video_stories';
                        }

                        // Initialize upload session
                        $response = Http::facebook()->post($this->social_account->account_id . $endpoint, [
                            'upload_phase' => 'start',
                            'access_token' => $this->social_account->access_token->access_token_page
                        ]);

                        // Check
                        if (!$response->successful()) {
                            $this->errorHandler(273, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();
                            $video_id = $rep['video_id'];
                            $upload_url = $rep['upload_url'];

                            // Upload the file
                            $response = Http::timeout(60)->withHeaders([
                                'Authorization' => 'OAuth ' . $this->social_account->access_token->access_token_page,
                                'file_url' => $media_file['url']]
                            )->post($upload_url);

                            // Check
                            if (!$response->successful()) {
                                $this->errorHandler(290, $response);
                            return;
                            } else {

                                // Start by sleeping 20
                                sleep(60);

                                // Params
                                $params = [
                                    'upload_phase' => 'finish',
                                    'video_id' => $video_id,
                                    'access_token' => $this->social_account->access_token->access_token_page
                                ];
                                if (!$this->post->platform_is_story) {
                                    $params['video_state'] = 'PUBLISHED';
                                    $params['description'] = trim($this->content);
                                }

                                // Publish the reel
                                $response = Http::facebook()->post($this->social_account->account_id . $endpoint, $params);

                                // Check
                                if (!$response->successful()) {
                                    $this->errorHandler(337, $response);
                                    return;
                                } else {

                                    // Make pretty
                                    $rep = $response->json();

                                    // Check
                                    if ($rep['success'] == true) {

                                        // Get the foreign id
                                        $foreign_id = $rep['post_id'];

                                        // Now mark the post as posted
                                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                            true, // Success level
                                            $this->error_message, // Message we want to share?
                                            $this->post, // The post object,
                                            $foreign_id
                                        );
                                        return;

                                    } else {

                                        Log::info("UploadMedia FB Reel - 287 - Error UNHANDLED");

                                    }



                                }
                            }
                        }

                    
                    // CASE - Regular video
                    } elseif ($video_type == 'classic') {

                        // Init params
                        $params = [
                            'access_token' => $this->social_account->access_token->access_token_page,
                            'file_url' => $media_file['url'],
                        ];

                        // Add content
                        if (!empty(trim($this->content))) {
                            $params['description'] = trim($this->content);
                        }

                        // Add thumbnail
                        if ($this->thumbnail) {
                            if (in_array($this->thumbnail['extension'], ['bmp', 'gif', 'jpeg', 'jpg', 'png', 'tiff'])) {
                                $params['thumb'] = $this->thumbnail['url'];
                            }
                        }


                        // Initialize the video upload
                        $response = Http::facebook()->asForm()->timeout(60)->post($this->social_account->account_id . '/videos', $params);

                        // Check
                        if (!$response->successful()) {
                            $this->errorHandler(399, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();

                            // Save
                            $foreign_id = $rep['id'];
                            
                            // Now mark the post as posted
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;

                            

                        }



                    }






                // CASE - Photo post
                } elseif (in_array($media_file['extension'], $this->requirements->image->extensions)) {

                    // CASE - Story post
                    if ($this->post->platform_is_story) {

                        // Upload the photo
                        $response = Http::facebook()->post($this->social_account->account_id . '/photos', [
                            'access_token' => $this->social_account->access_token->access_token_page,
                            'url' => $this->media[0]['url'],
                            'published' => false
                        ]);

                        // Check result
                        if (!$response->successful()) {
                            $this->errorHandler(446, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();
                            $photo_id = $rep['id'];

                            // Publish the story
                            $response = Http::facebook()->post($this->social_account->account_id . '/photo_stories', [
                                'access_token' => $this->social_account->access_token->access_token_page,
                                'photo_id' => $photo_id
                            ]);

                            // Check
                            if (!$response->successful()) {
                                $this->errorHandler(462, $response);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();

                                if ($rep['success'] == 'true') {

                                    // Get the foreign ID
                                    $foreign_id = $rep['post_id'];

                                    // Now mark the post as posted
                                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                        true, // Success level
                                        $this->error_message, // Message we want to share?
                                        $this->post, // The post object,
                                        $foreign_id
                                    );
                                    return;

                                } else {
                                    Log::info("UploadMedia 472 - Failed to post story?");
                                }
                            }
                        }


                    // CASE - Not a story
                    } else {

                        // Run all the queries
                        $responses = Http::pool(function(Pool $pool) {
                            $arr = [];
                            foreach ($this->media as $m) {
                                $arr[] = $pool->post('https://graph.facebook.com/v25.0/' . $this->social_account->account_id . "/photos", [
                                    'access_token' => $this->social_account->access_token->access_token_page,
                                    'url' => $m['url'],
                                    'published' => false
                                ]);
                            }
                            return $arr;
                        });

                        // Now add them all to an object if they're successful
                        $post_ids = [];
                        foreach ($responses as $response) {
                            if (!$response->ok()) {
                                $this->errorHandler(451, $response);
                                return;
                            } else {
                                $rep = $response->json();
                                $post_ids[] = ['media_fbid' => $rep['id']];
                            }
                        }

                        // Check if we actually have some post_ids
                        if (count($post_ids) < 1) {
                            $this->error_message = "None of your media could be attached to your Facebook post. Did you try uploading media files that weren't photos?";
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                false, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;
                        } else {
                            $params['attached_media'] = $post_ids;
                        }

                        // Make the actual query
                        $response = Http::facebook()->post($this->social_account->account_id . "/feed", $params);

                        // Check the result
                        if (!$response->ok()) {
                            $this->errorHandler(478, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();

                            // Get the foreign id
                            $foreign_id = $rep['id'];

                            // Now mark the post as posted
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;
                        }

                    }

                    

                }
            }
        }






        /**
         * SECTION - Instagram
         */
        if ($this->post->platform == "instagram") {

            // Log::info("We here" . __LINE__);

            // CASE - One media
            if (count($this->media) == 1) {

                // Make pretty
                $media_file = $this->media[0];


                // CASE - Single photo post
                if (in_array($media_file['extension'], $this->requirements->image->extensions)) {

                    // Params
                    $params = [
                        'image_url' => $media_file['url'],
                        'caption' => $this->content,
                        'access_token' => $this->social_account->access_token->access_token
                    ];
                    if ($this->post->platform_is_story) {
                        $params['media_type'] = 'STORIES';
                    }

                    // Perform the query
                    $response = Http::facebook()->post($this->social_account->account_id . '/media', $params);

                    // Check the result
                    if (!$response->ok()) {
                        $this->errorHandler(537, $response);
                        return;
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Get the foreign id
                        $media_container = $rep['id'];

                        // Publish the container
                        $response = Http::facebook()->post($this->social_account->account_id . '/media_publish', [
                            'creation_id' => $media_container,
                            'access_token' => $this->social_account->access_token->access_token
                        ]);

                        // Check the result
                        if (!$response->ok()) {
                            $this->errorHandler(555, $response, $media_container);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();

                            // Get the foreign id
                            $foreign_id = $rep['id'];

                            // Now mark the post as posted
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;

                        }


                        
                    }


                // CASE - One reel
                } elseif (in_array($media_file['extension'], $this->requirements->reel->extensions)) {

                    $params = [
                        'access_token' => $this->social_account->access_token->access_token,
                        'video_url' => NotionPosts::storeFileInLocalStorage($this->post->userid, $media_file),
                    ];
                    if ($this->post->platform_is_story) {
                        // NOTE - Story post
                        $params['media_type'] = 'STORIES';
                    } else {
                        // NOTE - Reel post
                        $params['media_type'] = 'REELS';
                        if ($this->thumbnail) {
                            $params['cover_url'] = $this->thumbnail['url'];
                        }
                        if (!empty(trim($this->content))) {
                            $params['caption'] = $this->content;
                        }
                    }
                    
                    $response = Http::facebook()->post($this->social_account->account_id . '/media', $params);

                    // Check
                    if (!$response->ok()) {
                        $this->errorHandler(606, $response);
                        return;
                    } else {

                        // Sleep for a bit
                        sleep(20);

                        // Make pretty
                        $rep = $response->json();
                        $container_id = $rep['id'];

                        // Loop until we're good to go?
                        $hasLoaded = false;
                        $maxtries = 15;
                        for ($i = 1; $i <= $maxtries; $i++) {
                        // while (true) {

                            // CASE - Circuit breaker
                            if ($i == $maxtries) {
                                $hasLoaded = true;
                                break;
                            }

                            $response = Http::facebook()->get($container_id, [
                                'access_token' => $this->social_account->access_token->access_token,
                                'fields' => 'status_code,status'
                            ]);

                            if (!$response->ok()) {
                                $this->errorHandler(628, $response);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();
                                $status_code = $rep['status_code'];

                                // Check status
                                if ($status_code == 'FINISHED') {
                                    $hasLoaded = true;
                                    break;
                                } elseif ($status_code == 'IN_PROGRESS') {
                                    // Do nothing
                                } elseif ($status_code == 'ERROR') {
                                    $this->handleFacebookVideoUploadErrorMessages($rep);
                                    break;
                                }

                                // Sleep
                                sleep(60);
                            }
                            

                        }

                        // Check if hasLoaded
                        if ($hasLoaded) {

                            // Publish the container
                            $response = Http::facebook()->post($this->social_account->account_id . '/media_publish', [
                                'access_token' => $this->social_account->access_token->access_token,
                                'creation_id' => $container_id
                            ]);

                            if (!$response->successful()) {

                                $this->errorHandler(664, $response, $container_id);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();
                                $foreign_id = $rep['id'];

                                // All is good in the world
                                UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                    true, // Success level
                                    $this->error_message, // Message we want to share?
                                    $this->post, // The post object,
                                    $foreign_id
                                );
                                return;

                            }

                        } else {
                            // TODO - Reel never got fully loaded, so fail the job and retry later?
                        }

                    }

                }
            }

            // CASE - Multiple media = Carousel
            if (count($this->media) > 1) {

                // Create an easily workable array of files
                $carousel_files = [];

                foreach ($this->media as $m) {

                    // Check extension
                    if (in_array($m['extension'], $this->requirements->image->extensions)) {
                        $file_type = "image";
                    } elseif (in_array($m['extension'], $this->requirements->reel->extensions)) {
                        $file_type = "video";
                    } else {
                        continue;
                    }

                    // Populate array
                    $carousel_files[] = [
                        'type' => $file_type,
                        'url' => $m['url']
                    ];

                }

                // Check if we actually have files? 
                if (count($carousel_files) < 1) {
                    $this->error_message = "It looks like none of the files for this carousel were of type image or video.";
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        false, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post // The post object,
                    );
                    return;
                }

                // Loop through all the content
                $responses = Http::pool(function(Pool $pool) use ($carousel_files) {
                    $arr = [];
                    foreach ($carousel_files as $key => $m) {

                        // Set the default params 
                        $params = [
                            'access_token' => $this->social_account->access_token->access_token,
                            'is_carousel_item' => true
                        ];

                        // CASE - Single photo post
                        if ($m['type'] == 'image') {
                            $params['image_url'] = $m['url'];
                        } else {
                            $params['video_url'] = $m['url'];
                            $params['media_type'] = "REELS";
                        }

                        $arr[] = $pool->as($key)->post('https://graph.facebook.com/v25.0/' . $this->social_account->account_id . "/media", $params);
                    }
                    return $arr;
                });

                // Now add them all to an object if they're successful
                $post_ids = [];
                $video_ids_to_check = [];
                foreach ($responses as $key => $response) {

                    // Check the result of the query
                    if (!$response->ok()) {
                        $this->errorHandler(757, $response);
                        return;
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Get the type from the previous array
                        $type = $carousel_files[$key]['type'];

                        // Switch
                        if ($type == 'image') {
                            $post_ids[] = $rep['id'];
                        } else {
                            $video_ids_to_check[] = $rep['id'];
                        }

                    }
                }

                // Check if we have some video files to check
                if (count($video_ids_to_check) > 0) {

                    // Initial sleep so we don't have to re-do the query multiple times...
                    sleep(60);

                    // Go through all of them
                    foreach ($video_ids_to_check as $video) {

                        // Loop until we're good to go?
                        $maxtries = 15;
                        for ($i = 1; $i <= $maxtries; $i++) {
                        // while (true) {

                            // CASE - Circuit breaker
                            if ($i == $maxtries) {
                                $post_ids[] = $video;
                                break;
                            }

                            $response = Http::facebook()->get($video, [
                                'access_token' => $this->social_account->access_token->access_token,
                                'fields' => 'status_code,status'
                            ]);

                            if (!$response->ok()) {
                                $this->errorHandler(796, $response);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();
                                $status_code = $rep['status_code'];

                                // Check status
                                if ($status_code == 'FINISHED') {
                                    $post_ids[] = $video;
                                    break;
                                } elseif ($status_code == 'IN_PROGRESS') {
                                    // Do nothing
                                } elseif ($status_code == 'ERROR') {
                                    $this->handleFacebookVideoUploadErrorMessages($rep);
                                    break;
                                }

                                // Sleep
                                sleep(60);
                            }
                        }
                    }
                }


                // Final check
                if (count($post_ids) < 2) {
                    $this->error_message = "It looks like none of the files for this carousel could be successfully uploaded. Are you sure they're of the right type? Videos shouldn't be larger than 1920x1080.";
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        false, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post // The post object,
                    );
                    return;
                }

                // Create the carousel container
                $params = [
                    'access_token' => $this->social_account->access_token->access_token,
                    'media_type' => 'CAROUSEL',
                    'caption' => $this->content,
                    'children' => implode(',', $post_ids)
                ];
                $response = Http::facebook()->post($this->social_account->account_id . '/media', $params);

                // Check the result
                if (!$response->ok()) {
                    $this->errorHandler(844, $response);
                    return;
                } else {

                    // Make pretty
                    $rep = $response->json();
                    $container_id = $rep['id'];

                    // Now we're going to loop on the carousel container until it's ready
                    $caarousel_is_publishable = false;
                    $maxtries = 15;
                    for ($i = 1; $i <= $maxtries; $i++) {

                        // CASE - Circuit breaker
                        if ($i == $maxtries) {
                            $caarousel_is_publishable = true;
                            break;
                        }

                        $response = Http::facebook()->get($container_id, [
                            'access_token' => $this->social_account->access_token->access_token,
                            'fields' => 'status_code,status'
                        ]);

                        if (!$response->ok()) {
                            $this->errorHandler(863, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();
                            $status_code = $rep['status_code'];

                            // Log::info("We here" . __LINE__);
                            // Log::info($rep);

                            // Check status
                            if ($status_code == 'FINISHED') {
                                // Carousel is ready to go, lets publish
                                $caarousel_is_publishable = true;
                                break;
                            } elseif ($status_code == 'IN_PROGRESS') {
                                // Do nothing
                            } elseif ($status_code == 'ERROR') {
                                $this->handleFacebookVideoUploadErrorMessages($rep);
                                break;
                            }

                            // Sleep
                            sleep(60);
                        }
                    }

                    // Check if the carousel is ready to publish
                    if (!$caarousel_is_publishable) {
                        $this->error_message = "Your Carousel took unusually long to upload. Try re-scheduling your post and submitting it again.";
                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                            false, // Success level
                            $this->error_message, // Message we want to share?
                            $this->post // The post object,
                        );
                        return;
                    } else {

                        // Publish
                        $params = [
                            'access_token' => $this->social_account->access_token->access_token,
                            'creation_id' => $rep['id']
                        ];
                        $response = Http::facebook()->post($this->social_account->account_id . '/media_publish', $params);

                        // Check the result
                        if (!$response->ok()) {
                            $this->errorHandler(908, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();
                            $foreign_id = $rep['id'];

                            // All is good in the world
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;
                        }

                    }
                }
            }
        }


        /**
         * SECTION - Threads
         */
        if ($this->post->platform == "threads") {

            // CASE - Text post or single media post
            if (count($this->media) < 2) {

                // Init the params
                $params = [
                    'access_token' => $this->social_account->access_token->access_token
                ];

                // CASE - Has media
                if (count($this->media) > 0) {
                    // Switch the type
                    $media = $this->media[0];

                    // Switch the type
                    if (in_array($media['extension'], $this->requirements->image->extensions)) {
                        $params['media_type'] = 'IMAGE';
                        $params['image_url'] = $media['url'];
                    } else {
                        $params['media_type'] = 'VIDEO';
                        $params['video_url'] = $media['url'];
                    }

                    // Is there a caption?
                    if (!empty(trim($this->content))) {
                        $params['text'] = $this->content;
                    }

                // CASE - Text post
                } else {
                    $params['media_type'] = 'TEXT';
                    $params['text'] = $this->content;
                }

                // Make the post
                $response = Http::threads()->post($this->social_account->account_id . '/threads', $params);
                $rep = $response->json();

                // Check
                if (!$response->successful()) {
                    $this->errorHandler(1068, $response);
                    return;
                } else {

                    // Wait 30 seconds before publishing
                    sleep(30);

                    // If it's a video - check the status
                    if ($params['media_type'] == 'VIDEO') {

                        // Loop until we're good to go?
                        $maxtries = 15;
                        for ($i = 1; $i <= $maxtries; $i++) {

                            // CASE - Circuit breaker
                            if ($i == $maxtries) {
                                break;
                            }

                            $response = Http::threads()->get($rep['id'], [
                                'access_token' => $this->social_account->access_token->access_token,
                                'fields' => 'id,status'
                            ]);

                            if (!$response->ok()) {
                                $this->errorHandler(1119, $response);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();
                                $status_code = $rep['status'];

                                // Check status
                                if ($status_code == 'FINISHED') {
                                    break;
                                } elseif ($status_code == 'IN_PROGRESS') {
                                    // Do nothing
                                } elseif ($status_code == 'ERROR') {
                                    $this->handleFacebookVideoUploadErrorMessages($rep);
                                    break;
                                }

                                // Sleep
                                sleep(60);
                            }
                        }

                    }

                    // Wait 30 seconds before publishing
                    sleep(30);

                    // Publish the container
                    $response = Http::threads()->post($this->social_account->account_id . '/threads_publish', [
                        'creation_id' => $rep['id'],
                        'access_token' => $this->social_account->access_token->access_token
                    ]);
                    $rep = $response->json();

                     // Check
                    if (!$response->successful()) {
                        $this->errorHandler(1085, $response);
                        return;
                    } else {

                        // Get the foreign ID
                        $foreign_id = $rep['id'];

                        // All is good in the world
                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                            true, // Success level
                            $this->error_message, // Message we want to share?
                            $this->post, // The post object,
                            $foreign_id
                        );
                        return;

                    }
                }

            // CASE - Carousel
            } else {

                // Create an easily workable array of files
                $carousel_files = [];

                foreach ($this->media as $m) {

                    // Check extension
                    if (in_array($m['extension'], $this->requirements->image->extensions)) {
                        $file_type = "IMAGE";
                    } elseif (in_array($m['extension'], $this->requirements->video->extensions)) {
                        $file_type = "VIDEO";
                    } else {
                        continue;
                    }

                    // Populate array
                    $carousel_files[] = [
                        'type' => $file_type,
                        'url' => $m['url']
                    ];

                }

                // Loop through all the content
                $responses = Http::pool(function(Pool $pool) use ($carousel_files) {
                    $arr = [];
                    foreach ($carousel_files as $key => $m) {

                        // Set the default params 
                        $params = [
                            'access_token' => $this->social_account->access_token->access_token,
                            'is_carousel_item' => true,
                            'media_type' => $m['type']
                        ];

                        // CASE - Single photo post
                        if ($m['type'] == 'IMAGE') {
                            $params['image_url'] = $m['url'];
                        } else {
                            $params['video_url'] = $m['url'];
                        }

                        $arr[] = $pool->as($key)->post('https://graph.threads.net/v1.0/' . $this->social_account->account_id . "/threads", $params);
                    }
                    return $arr;
                });

                // Now add them all to an object if they're successful
                $post_ids = [];
                $video_ids_to_check = [];
                foreach ($responses as $key => $response) {

                    // Check the result of the query
                    if (!$response->ok()) {
                        $this->errorHandler(1184, $response);
                        return;
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Get the type from the previous array
                        $type = $carousel_files[$key]['type'];

                        // Switch
                        if ($type == 'IMAGE') {
                            $post_ids[] = $rep['id'];
                        } else {
                            $video_ids_to_check[] = $rep['id'];
                        }

                    }
                }

                // Check all video files to see if they're done processing
                if (count($video_ids_to_check) > 0) {

                    // Initial sleep so we don't have to re-do the query multiple times...
                    sleep(60);

                    // Go through all of them
                    foreach ($video_ids_to_check as $video) {

                        // Loop until we're good to go?
                        $maxtries = 15;
                        for ($i = 1; $i <= $maxtries; $i++) {
                        // while (true) {

                            // CASE - Circuit breaker
                            if ($i == $maxtries) {
                                $post_ids[] = $video;
                                break;
                            }

                            $response = Http::threads()->get($video, [
                                'access_token' => $this->social_account->access_token->access_token,
                                'fields' => 'id,status'
                            ]);

                            if (!$response->ok()) {
                                $this->errorHandler(1231, $response);
                                return;
                            } else {

                                // Make pretty
                                $rep = $response->json();
                                $status_code = $rep['status'];

                                // Check status
                                if ($status_code == 'FINISHED') {
                                    $post_ids[] = $video;
                                    break;
                                } elseif ($status_code == 'IN_PROGRESS') {
                                    // Do nothing
                                } elseif ($status_code == 'ERROR') {
                                    $this->handleFacebookVideoUploadErrorMessages($rep);
                                    break;
                                }

                                // Sleep
                                sleep(60);
                            }
                        }
                    }

                }

                // Create a carousel container
                $params = [
                    'media_type' => 'CAROUSEL',
                    'access_token' => $this->social_account->access_token->access_token,
                    'children' => implode(',', $post_ids)
                ];
                if (!empty(trim($this->content))) {
                    $params['text'] = $this->content;
                }

                // Sleep 60 before joining the carousel to give it time to process
                sleep(60);

                // Perform query
                $response = Http::threads()->post($this->social_account->account_id . '/threads', $params);
                $rep = $response->json();

                // Check
                if (!$response->ok()) {
                    $this->errorHandler(1223, $response);
                    return;
                } else {

                    // Publish the container
                    $response = Http::threads()->post($this->social_account->account_id . '/threads_publish', [
                        'creation_id' => $rep['id'],
                        'access_token' => $this->social_account->access_token->access_token
                    ]);
                    $rep = $response->json();

                     // Check
                    if (!$response->successful()) {
                        $this->errorHandler(1240, $response);
                        return;
                    } else {

                        // Get the foreign ID
                        $foreign_id = $rep['id'];

                        // All is good in the world
                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                            true, // Success level
                            $this->error_message, // Message we want to share?
                            $this->post, // The post object,
                            $foreign_id
                        );
                        return;

                    }

                }

            }


        }




        /**
         * SECTION - Twitter
         */
        if ($this->post->platform == 'twitter') {

            // Check if token is within 10 minutes of expiring
            $time_to_expire = Carbon::now()->diffInMinutes(Carbon::parse($this->social_account->access_token->expiry_date));
            $token_validity_in_minutes = 10;
            
            // Check if the token expires in less than 10 minutes, if so, refresh
            if ($time_to_expire < $token_validity_in_minutes) {

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
                        "refresh_token" => $this->social_account->access_token->refresh_token,
                        "client_id" => Config::get('services.twitter-oauth-2.client_id')
                ]);

                // Check result
                if (!$response->ok()) {
                    $this->error_message = "There was an issue refreshing your Twitter access token. Have you removed NotionScheduler from your Twitter account?";
                    Log::info($response->json());
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        false, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post // The post object,
                    );
                    $this->release(30);
                    $this->fail();
                    return;
                } else {

                    // Make pretty
                    $rep = $response->json();
                    
                    // Update our model
                    $this->social_account->access_token->access_token = $rep['access_token'];
                    $this->social_account->access_token->refresh_token = $rep['refresh_token'];
                    $this->social_account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                    $this->social_account->access_token->save();

                }

            }

            // Set the media array
            $media_ids = [];

            // CASE - Post HAS media
            if (count($this->media) > 0) {

                // Looop through all the media
                foreach ($this->media as $media) {

                    // Grab the extension
                    $ext = $media['extension'];

                    // Check if it's a single GIF
                    if (in_array($ext, $this->requirements->gif->extensions) && count($this->media) < 2) {

                        // Define the media category
                        $media_category = 'tweet_gif';

                    // Check the extension - Image
                    } elseif (in_array($ext, $this->requirements->image->extensions)) {

                        // Define the media category
                        $media_category = 'tweet_image';


                    // Check the extension - Video
                    } elseif (in_array($ext, $this->requirements->video->extensions)) {

                        // Define the media category
                        $media_category = 'tweet_video';

                    }

                    // Get the file size etc
                    $headers = get_headers($media['url'], 1);
                    $content_length = $headers['Content-Length'];
                    $content_type = $headers['Content-Type'];

                    // Perform the request
                    $response = Http::twitterX()->withHeaders(
                        [
                            'Authorization' => "Bearer " . $this->social_account->access_token->access_token
                        ]
                    )
                    ->asMultipart()
                    ->attach(
                        'media',
                        // fopen($media['url'], 'r'),
                        \GuzzleHttp\Psr7\Utils::streamFor(fopen($media['url'], 'r')),
                        basename($media['url']),
                    )
                    ->post('media/upload', [
                        'media_category' => $media_category,
                        'media_type'     => $content_type,
                    ]);

                    if (!$response->successful()) {
                        $this->errorHandler(1556, $response);
                        return;
                    }

                    // Get the JSON
                    $rep = $response->json();

                    // Add to the array
                    $media_ids[] = $rep['data']['id'];

                }

            } 


            // Prepare the payload
            $payload = [
                'text' => $this->content
            ];

            // Add media if there is media
            if (count($media_ids) > 0) {
                $payload['media'] = [
                    'media_ids' => $media_ids
                ];
            }



            // Peform the tweet
            $response = Http::twitter()
            ->withHeaders(
                [
                    'Authorization' => "Bearer " . $this->social_account->access_token->access_token
                ]
            )->post('tweets', $payload);

            // Check
            if (!$response->successful()) {
                $this->errorHandler(1008, $response);
                return;
            } else {

                // Make pretty  
                $rep = $response->json();

                // Get the id
                $foreign_id = $rep['data']['id'];

                // All is good in the world
                UpdateNotionPostInDatabaseAfterUpload::dispatch(
                    true, // Success level
                    $this->error_message, // Message we want to share?
                    $this->post, // The post object,
                    $foreign_id
                );
                return;

            }

            
            

        }

        /**
         * SECTION - Linkedin
         * 
         * 
         * Useful resources - 
         *  https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/images-api?view=li-lms-2024-03&tabs=http
         * https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/multiimage-post-api?view=li-lms-2024-03&tabs=http 
         * https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/vector-asset-api?view=li-lms-2024-03&tabs=http#upload-the-image 
         * 
         * Finding a post - https://www.linkedin.com/feed/update/urn:li:share:<share-id>
         * 
         * 
         * Maybe I need to migrate to UGCPosts? 
         *  https://learn.microsoft.com/en-us/linkedin/compliance/integrations/shares/ugc-post-api?tabs=http#create-ugc-posts 
         * 
         * A lot of example code here:  https://github.com/Light-it-labs/laravel-linkedin-share/blob/master/src/LinkedinShare.php 
         */
        
        if ($this->post->platform == 'linkedin' /* or $this->post->platform == 'linkedin_page' */) {

            $author = $this->social_account->account_full_identifier;

            // NOTE - Sanitize some of the inputs that LinkedIn doesn't like
            $sanicontent = preg_replace_callback('/([\(\)\{\}\[\]])|([@*<>\\\\\_~])/m', function ($matches) {
                return '\\'.$matches[0];
            }, $this->content);
            $this->content = $sanicontent;

            try {
                $urlHighlight = new UrlHighlight();
                $urls = $urlHighlight->getUrls($this->content);

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
                $token = NotionSocialAccounts::where('id', '17')
                    ->with('access_token')
                    ->first();

                foreach ($new_urls as $k => $url) {

                    // CASE - Person
                    if ($url['type'] == 'person') {
                        $response = Http::linkedinwithheaders($token->access_token->access_token)
                        ->get('vanityUrl', [
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

                                $qurl = 'people/(id:' . Str::of($urn)->afterLast(':') . ')';

                                $response = Http::linkedinwithheaders($token->access_token->access_token)
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
                        $response = Http::linkedinwithheaders($token->access_token->access_token)
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

                $this->content = Str::of($this->content);
                foreach ($new_urls as $nurl) {
                    if (isset($nurl['displayName'])) {
                        $format = '@[' . $nurl['displayName'] . '](' . $nurl['urn'] . ')';
                    $this->content = $this->content->replaceFirst($nurl['raw'], $format);
                    } else {
                        Log::info("1686");
                        Log::info("Missing displayName");
                        Log::info($nurl);
                    }
                    
                }
            } catch (Throwable $e) {
                Log::info("1698");
                Log::info($e);
            }

            // CASE - Has media
            if (count($this->media) > 0) {

                /**
                 * SECTION - Image uploads
                 */
                if (in_array($this->media[0]['extension'], $this->requirements->image->extensions)) {

                    // Create an array of empty images
                    $images = [];

                    // Loop through all the items
                    foreach ($this->media as $media) {

                        // Create an image upload container
                        $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                        // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                            ->post('images?action=initializeUpload', [
                                'initializeUploadRequest' => [
                                    'owner' => $author
                                ]
                            ]);
                        
                        // Check
                        if (!$response->successful()) {
                            $this->errorHandler(1094, $response);
                            return;
                        } else {

                            // Make pretty
                            $rep = $response->json();

                            // Get the container
                            $uploadUrl = $rep['value']['uploadUrl'];
                            $uploadId = $rep['value']['image']; 

                            $client = new \GuzzleHttp\Client();
                            $response = $client->request('PUT', $uploadUrl, [
                                    'headers' => [
                                        'Authorization' => 'Bearer '.$this->social_account->access_token->access_token,
                                    ],
                                    'body' => \GuzzleHttp\Psr7\Utils::streamFor(fopen($media['url'], 'r'))

                                ]
                            );

                            // Check
                            $status = $response->getReasonPhrase();

                            // Check the response
                            if ($status != "Created") {

                                // TODO - Not uploaded

                            } else {

                                // The content is uploaded, we can add it to the array?
                                $images[] = $uploadId;

                            }

                        }

                    }

                    // Send the post
                    if (count($images) < 1) {
                        $this->error_message = "None of the images you scheduled were able to be uploaded. Are you sure they're in the correct format?";
                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                            false, // Success level
                            $this->error_message, // Message we want to share?
                            $this->post // The post object,
                        );
                    }

                    if (count($images) > 0) {

                        // CASE - Multi image
                        if (count($images) > 1) {

                            $content = [
                                'multiImage' => [
                                    'images' => []
                                ]
                            ];
                            foreach ($images as $image) {
                                $content['multiImage']['images'][] = [
                                    'id' => $image
                                ];
                            }

                        // CASE - Single images
                        } else {

                            $content = [
                                'media' => [
                                    'id' => $images[0]
                                ]
                            ];

                        }

                        $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                        // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                            ->post('posts' , [
                                "author" => $author,
                                "commentary" => $this->content,
                                "visibility" => "PUBLIC",
                                "distribution" => [
                                    "feedDistribution" => "MAIN_FEED",
                                    "targetEntities" => [],
                                    "thirdPartyDistributionChannels" => []
                                ],
                                "content" => $content,
                                "lifecycleState" => "PUBLISHED",
                                "isReshareDisabledByAuthor" => false
                        ]);

                        // Check
                        if (!$response->successful()) {
                            $this->errorHandler(1194, $response);
                            return;
                        } else {

                            // Resolve the created post URN (prefers x-restli-id).
                            $foreign_id = $this->linkedinPostId($response);

                            // All is good in the world
                            UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                true, // Success level
                                $this->error_message, // Message we want to share?
                                $this->post, // The post object,
                                $foreign_id
                            );
                            return;

                        }

                    }

                /**
                 * SECTION - Video uploads
                 */
                } elseif (in_array($this->media[0]['extension'], $this->requirements->video->extensions)) {

                    // Make pretty
                    $media = $this->media[0];

                    // Get the file size
                    $headers = get_headers($media['url'], 1);
                    $content_length = $headers['Content-Length'];

                    // Initialize the upload
                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                    // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                        ->post('videos?action=initializeUpload', [
                            'initializeUploadRequest' => [
                                'owner' => $this->social_account->account_full_identifier,
                                "uploadCaptions" => false,
                                "uploadThumbnail" => false,
                                'fileSizeBytes' => (int) $content_length
                            ]
                    ]);

                    if (!$response->successful()) {
                        // TODO
                        Log::info("Uploadmedia 123 - FAIL - UNHANDLED");
                        Log::info($response);
                    } else {

                        // Get the data
                        $rep = $response->json();
                        $video_object = $rep['value']['video'];

                        // Get the file
                        $file = file_get_contents($media['url']);

                        // Init some variables
                        $has_full_upload = true;
                        $upload_part_ids = [];
                        $instructions = $rep['value']['uploadInstructions'];

                        /**
                         * NOTE - Upload chunks one by one sequentially
                         */

                        // NOTE - Pool the uploads together
                        $responses = Http::pool(function (Pool $pool) use ($instructions, $file) {

                            $arr = [];
                            foreach ($instructions as $instruction) {
                                // Get the byte sizes
                                $firstByte = $instruction['firstByte'];
                                $lastByte = $instruction['lastByte'];
                                $size = $lastByte - $firstByte + 1;
                                // Add to the pool
                                $arr[] = $pool->withToken($this->social_account->access_token->access_token)
                                    ->withBody(
                                        // $file, 
                                        substr($file, $firstByte, $size),
                                        "application/octet-stream"
                                    )
                                    ->put($instruction['uploadUrl']);
                            }
                            return $arr;
                        });

                        // Check the responses
                        foreach ($responses as $response) {
                            if (!$response->successful()) {
                                $this->error_message = "There was an error uploading one of the sections of your video to LinkedIn. The admins have been notified and will look into it ASAP.";
                                Log::info("UploadMedia - 1308 - Issue uploading video...");
                                Log::info("Post has id " . $this->post->id);
                                Log::info($response);
                                Log::info($response->json());
                                UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                    false, // Success level
                                    $this->error_message, // Message we want to share?
                                    $this->post // The post object,
                                );
                                // $this->handleLinkedinErrorMessages($response);
                                $has_full_upload = false;
                                break;
                            } else {
                                $upload_part_ids[] = $response->header('ETag');
                            }
                        }

                        // Check if everything is ready
                        if (!$has_full_upload) {
                            // Do nothing, since a message was already dispatched above?
                            return;
                        } else {

                            // Finalize the video upload
                            $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                            // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                                ->post('videos?action=finalizeUpload', [
                                    'finalizeUploadRequest' => [
                                        'video' => $video_object,
                                        'uploadToken' => '',
                                        'uploadedPartIds' => $upload_part_ids
                                    ]
                            ]);

                            if (!$response->successful()) {
                                // $this->handleLinkedinErrorMessages($response);
                                $this->errorHandler(1343, $response);
                                return;
                            } else {

                                // Lets check the result of our upload
                                $vid = explode(':', $video_object);
                                $vid = end($vid);

                                // Lets loop and check the upload status
                                $is_uploading = true;
                                while ($is_uploading) {

                                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                                    // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                                        ->get('assets/' . $vid);
                                    $rep = $response->json();
                                    $upload_status = $rep['recipes'][0]['status'];

                                    if ($upload_status == 'PROCESSING') {
                                        sleep(10);
                                    } else {
                                        $is_uploading = false;
                                    }
                                }

                                // Check if there is an error
                                if ($upload_status == "CLIENT_ERROR") {
                                    $this->error_message = "There was an unknown issue uploading your video to LinkedIn. Are you sure this video is in the correct format?";
                                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                        false, // Success level
                                        $this->error_message, // Message we want to share?
                                        $this->post // The post object,
                                    );
                                } else {

                                    // Submit the actual post to linkedin
                                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                                    // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                                        ->post('posts' , [
                                            "author" => $this->social_account->account_full_identifier,
                                            "commentary" => $this->content,
                                            "visibility" => "PUBLIC",
                                            "distribution" => [
                                                "feedDistribution" => "MAIN_FEED",
                                                "targetEntities" => [],
                                                "thirdPartyDistributionChannels" => []
                                            ],
                                            "content" => [
                                                'media' => [
                                                    'id' => $video_object
                                                ]
                                            ],
                                            "lifecycleState" => "PUBLISHED",
                                            "isReshareDisabledByAuthor" => false
                                    ]);
            
                                    // Check
                                    if (!$response->successful()) {
                                        // $this->handleLinkedinErrorMessages($response);
                                        $this->errorHandler(1399, $response);
                                        return;
                                    } else {

                                        // Resolve the created post URN (prefers x-restli-id).
                                        $foreign_id = $this->linkedinPostId($response);

                                        // All is good in the world
                                        UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                            true, // Success level
                                            $this->error_message, // Message we want to share?
                                            $this->post, // The post object,
                                            $foreign_id
                                        );
                                        return;

                                    }

                                }

                            }

                            



                        }



                    }

                    
                /**
                 * SECTION - Document upload
                 * https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/documents-api?view=li-lms-2024-04&tabs=http 
                 */
                } elseif (in_array($this->media[0]['extension'], $this->requirements->document->extensions)) {

                    // Initialize the document upload
                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                        ->post('documents?action=initializeUpload', [
                            'initializeUploadRequest' => [
                                'owner' => $this->social_account->account_full_identifier,
                            ]
                    ]);

                    // Check response
                    if (!$response->successful()) {
                        $this->errorHandler(1460, $response);
                        return;
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Get the container
                        $uploadUrl = $rep['value']['uploadUrl'];
                        $uploadId = $rep['value']['document']; 

                        $response = Http::withToken($this->social_account->access_token->access_token)
                            ->withHeaders([
                                'Content-Type' => 'application/octet-stream'
                            ])
                            ->withBody(file_get_contents($this->media[0]['url']), 'application/octet-stream')
                            ->put($uploadUrl);

                        // Check the response
                        if (!$response->successful()) {

                            // TODO - Not uploaded
                            Log::info("UploadMedia Fail 1467 - UNHANDLED");
                            Log::info($response);

                        } else {

                            // Give it some time
                            sleep(20);

                            // Check the document status
                            $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                                ->get('documents/' . urlencode($uploadId));

                            if (!$response->successful()) {
                                Log::info("UploadMedia 1484 - UNHANDLED");
                                Log::info($response);
                            } else {

                                // Make pretty
                                $rep = $response->json();

                                // Check the upload status
                                if ($rep['status'] == 'PROCESSING_FAILED' or $rep['status'] == 'WAITING_UPLOAD') {
                                    Log::info("UploadMedia UNHANDLED 1493");
                                } else {

                                    // We're either processing or good to go, so lets post
                                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                                    ->post('posts' , [
                                        "author" => $this->social_account->account_full_identifier,
                                        "commentary" => $this->content,
                                        "visibility" => "PUBLIC",
                                        "distribution" => [
                                            "feedDistribution" => "MAIN_FEED",
                                            "targetEntities" => [],
                                            "thirdPartyDistributionChannels" => []
                                        ],
                                        "content" => [
                                            'media' => [
                                                'title' => $this->post->post_name,
                                                'id' => $uploadId
                                            ]
                                        ],
                                        "lifecycleState" => "PUBLISHED",
                                        "isReshareDisabledByAuthor" => false
                                ]);

                                // Check
                                if (!$response->successful()) {
                                    // $this->handleLinkedinErrorMessages($response);
                                    $this->errorHandler(1539, $response);
                                    return;
                                } else {

                                    // Resolve the created post URN (prefers x-restli-id).
                                    $foreign_id = $this->linkedinPostId($response);

                                    // All is good in the world
                                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                                        true, // Success level
                                        $this->error_message, // Message we want to share?
                                        $this->post, // The post object,
                                        $foreign_id
                                    );
                                    return;

                                }


                                }

                            }


                        }


                    }


                }


            // CASE - No media
            } else {

                // Create the post array
                $post_array = [
                    'author' => $author,
                    'commentary' => $this->content,
                    'visibility' => 'PUBLIC',
                    'lifecycleState' => 'PUBLISHED',
                    'distribution' => [
                        'feedDistribution' => 'MAIN_FEED',
                        'targetEntities' => [],
                        'thirdPartyDistributionChannels' => []
                    ]
                ];

                // Check if we have some links we want to add
                try {

                    if (count($urls) > 0) {

                        foreach ($urls as $url) {
                            if (!Str::of($url)->contains([
                                'linkedin.com/in/',
                                'linkedin.com/company/',
                                'linkedin.com/school/'
                            ])) {

                                // Get the Meta Tags
                                $meta = OpenGraph::fetch($url);

                                // Create a meta array
                                $meta_array = [];

                                // Populate
                                if (isset($meta['title'])) {
                                    $meta_array['title'] = $meta['title'];
                                }
                                if (isset($meta['description'])) {
                                    $meta_array['description'] = $meta['description'];
                                }
                                if (isset($meta['url'])) {
                                    $meta_array['source'] = $meta['url'];
                                }
                                if (isset($meta['image'])) {
                                    $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                                        ->post('images?action=initializeUpload', [
                                            'initializeUploadRequest' => [
                                                'owner' => $author
                                            ]
                                        ]);
                                    
                                    // Check
                                    if (!$response->successful()) {
                                        $this->errorHandler(2258, $response);
                                        return;
                                    } else {

                                        // Make pretty
                                        $rep = $response->json();

                                        // Get the container
                                        $uploadUrl = $rep['value']['uploadUrl'];
                                        $uploadId = $rep['value']['image']; 

                                        $client = new \GuzzleHttp\Client();
                                        $response = $client->request('PUT', $uploadUrl, [
                                                'headers' => [
                                                    'Authorization' => 'Bearer '.$this->social_account->access_token->access_token,
                                                ],
                                                'body' => \GuzzleHttp\Psr7\Utils::streamFor(fopen($meta['image'], 'r'))

                                            ]
                                        );

                                        // Check
                                        $status = $response->getReasonPhrase();

                                        // Check the response
                                        if ($status != "Created") {

                                            // TODO - Not uploaded

                                        } else {

                                            // The content is uploaded, we can add it to the array?
                                            $meta_array['thumbnail'] = $uploadId;

                                        }
                                    }
                                }

                                // Add
                                if (count($meta_array) > 0) {
                                    $post_array['content'] = [
                                        'article' => $meta_array
                                    ];
                                }

                            }
                        }

                    }

                } catch (\Exception $e) {
                    Log::info("UploadMedia 2208");
                    Log::info($e);
                }
                

                // Perform the post
                $response = Http::linkedinwithheaders($this->social_account->access_token->access_token)
                // $response = Http::linkedin()->withToken($this->social_account->access_token->access_token)
                    ->post('posts', $post_array);

                // Check
                if (!$response->successful()) {
                    $this->errorHandler(1601, $response);
                    return;
                } else {

                    // Resolve the created post URN (prefers x-restli-id).
                    $foreign_id = $this->linkedinPostId($response);

                    // All is good in the world
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        true, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post, // The post object,
                        $foreign_id
                    );
                    return;
                }

            }


        }

        /**
         * SECTION - YouTube upload
         */
        if ($this->post->platform == 'youtube') {

            /**
             * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
             * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
             * FIXME - This code needs to be refactored, it's both in UploadMedia and in CheckSocialTokens
             */

            // Check if token is within 10 minutes of expiring
            $time_to_expire = Carbon::now()->diffInMinutes(Carbon::parse($this->social_account->access_token->expiry_date));
            $token_validity_in_minutes = 10;

            // Check if the token expires in less than 10 minutes, if so, refresh
            if ($time_to_expire < $token_validity_in_minutes) {

                Log::info("Doing a live refresh of a YT Token just before posting...");

                // Perform query
                $response = Http::post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->social_account->access_token->refresh_token,
                    'client_id' => Config::get('services.youtube.client_id'),
                    'client_secret' => Config::get('services.youtube.client_secret')
                ]);
                $rep = $response->json();

                // Check response
                if (!$response->successful()) {
                    Log::info("Upload Media - CheckSocialToken - Failed to refresh YouTube token - UNHANDLED");
                    Log::info($response);
                    Log::info($rep);
                } else {

                    // Update token
                    $this->social_account->access_token->access_token = $rep['access_token'];
                    $this->social_account->access_token->expiry_date = Carbon::now()->addSeconds($rep['expires_in']);
                    $this->social_account->access_token->save();

                    // Update last scan
                    $updates = NotionSocialAccounts::where('token_id', $this->social_account->access_token->id)
                        ->update([
                            'last_token_check_scan' => Carbon::now()
                        ]);

                }

            }



            // Clean up media
            $media = $this->media[0];

            // Get some file details
            $headers = get_headers($media['url'], 1);
            $content_length = $headers['Content-Length'];
            $content_type = $headers['Content-Type'];

            // Create the body
            $body = [
                'snippet' => [
                    'title' => $this->content,
                    // "description" => "This is a description of my video",
                ],
                'status' => [
                    'privacyStatus' => 'public',
                    'embeddable' => true,
                    'license' => 'YouTube'
                ]
            ];

            // Initiate the upload
            $response = Http::withToken($this->social_account->access_token->access_token)
                ->withHeaders(
                    [
                        'Content-Length' => mb_strlen(json_encode($body)),
                        'X-Upload-Content-Length' => $content_length,
                        'X-Upload-Content-Type' => 'application/octet-stream'
                    ]
                )
                ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status,contentDetails', $body);

            // Make pretty
            $rep = $response->json();
            $headers = $response->headers();

            // Check if success
            if (!$response->successful()) {
                Log::info("YT Upload failed 2146");
                Log::error($rep);
                Log::error($headers);
                $this->errorHandler(2147, $response);
                return;
            } else {

                // Get the upload url
                $uploadUrl = $headers['Location'][0];

                // Perform the upload
                $response = Http::withToken($this->social_account->access_token->access_token)
                    ->withHeaders(
                        [
                            'Content-Length' =>  $content_length,
                            'Content-Type' => 'application/octet-stream'
                        ]
                    )
                    ->withBody(
                        fopen($media['url'], 'r')
                    )
                    ->put($uploadUrl);
                
                // Make pretty
                $rep = $response->json();
                $headers = $response->headers();

                // Check if success
                if (!$response->successful()) {
                    Log::info("YT Upload failed 2170");
                    Log::error($rep);
                    Log::error($headers);
                    $this->errorHandler(2170, $response);
                    return;
                } else {

                    // Post was successful
                    $foreign_id = $rep['id'];

                    // All is good in the world
                    UpdateNotionPostInDatabaseAfterUpload::dispatch(
                        true, // Success level
                        $this->error_message, // Message we want to share?
                        $this->post, // The post object,
                        $foreign_id
                    );
                    return;


                }

            }


        }



        /**
         * SECTION - TikTok Direct Upload
         * TODO -
         * TODO - This section is a WIP
         * TODO -
         */
        if ($this->post->platform == 'tiktok') {

            // Clean up media
            $media = $this->media[0];



            // Query creator info
            $response = Http::tiktok()
                ->withToken($this->social_account->access_token->access_token)
                ->post('post/publish/creator_info/query/', null);
            $rep = $response->json();

            // Check status
            if (!$response->successful()) {
                Log::info("Failed TikTok 2418 - Creator Status");
                Log::info($rep);
                Log::info($response);
                $this->errorHandler(2421, $response);
                return;
            } else {

                // TODO - Check the privacy levels from the response?
                $privacy_level = "PUBLIC_TO_EVERYONE";
                if (isset($rep['data']['privacy_level_options'])) {
                    $privacy_level = $rep['data']['privacy_level_options'][0];
                } else {
                    Log::info(2131);
                    Log::info($rep);
                }

                // Create the post info array
                $post_info = [
                    'privacy_level' => $privacy_level,

                    // Update based on this change to their API :
                    // https://developers.tiktok.com/bulletin/notice-for-update-to-content-posting-api 
                    'brand_content_toggle' => false,
                    'brand_organic_toggle' => false
                ];

                // Check if we have content
                if (!empty(trim($this->content))) {
                    $post_info['title'] = $this->content;
                }

                /**
                 * SECTION - Image uploads
                 */
                if (in_array($media['extension'], $this->requirements->image->extensions)) {

                    // Create an array of stored media
                    $stored_media = [];

                    // Check if we have a thumbnail
                    if ($this->thumbnail) {
                        $stored_media[] = NotionPosts::storeFileInLocalStorage($this->post->userid, $this->thumbnail);
                    }

                    // Loop through the media
                    foreach ($this->media as $media) {
                        $stored_media[] = NotionPosts::storeFileInLocalStorage($this->post->userid, $media);
                    }

                    // Make query
                    $response = Http::tiktok()->withToken($this->social_account->access_token->access_token)
                        ->post('post/publish/content/init/', [
                            'post_info' => $post_info,
                            'source_info' => [
                                'source' => 'PULL_FROM_URL',
                                'photo_cover_index' => 0,
                                'photo_images' => $stored_media
                            ],
                            "post_mode" => 'DIRECT_POST',
                            'media_type' => 'PHOTO'
                        ]);

                    if (!$response->successful()) {
                        $this->errorHandler(2173, $response);
                        return;
                    } else {

                        // Make pretty
                        $rep = $response->json();

                        // Process
                        $foreign_id = $rep["data"]['publish_id'];

                        $this->post->status = 'processing';
                        $this->post->posted_foreign_id =  $foreign_id;
                        $this->post->in_flight = 0;
                        $this->post->save();

                    }


                /**
                 * SECTION - Video uploads
                 */
                } elseif (in_array($media['extension'], $this->requirements->video->extensions)) {

                    // Store the media locally
                    $stored_media = NotionPosts::storeFileInLocalStorage($this->post->userid, $media);

                    // Make the post
                    $response = Http::tiktok()->withToken($this->social_account->access_token->access_token)
                        ->post('post/publish/video/init/', [
                            'post_info' => $post_info,
                            'source_info' => [
                                'source' => 'PULL_FROM_URL',
                                'video_url' => $stored_media
                            ]
                    ]);
                    $rep = $response->json();
                    if (!$response->successful()) {
                        $this->errorHandler(2172, $response);
                        return;
                    } else {
    
                        // Make pretty
                        
    
                        // Process
                        $foreign_id = $rep["data"]['publish_id'];
    
                        $this->post->status = 'processing';
                        $this->post->posted_foreign_id =  $foreign_id;
                        $this->post->in_flight = 0;
                        $this->post->save();
    
                    }

                }



            }

        }

    }

    /**
     * Extract the created post's URN from a LinkedIn create-post response.
     *
     * The REST.li posts API (LinkedIn-Version 202604) returns the new entity's
     * URN in the `x-restli-id` header; some responses use `x-linkedin-id`
     * instead. We prefer x-restli-id and fall back to x-linkedin-id. Returns
     * null when neither is present, so a missing header never gets persisted as
     * an empty posted_foreign_id (Response::header() yields '' for absent
     * headers).
     */
    private function linkedinPostId($response): ?string {

        $id = $response->header('x-restli-id');
        if (blank($id)) {
            $id = $response->header('x-linkedin-id');
        }

        return blank($id) ? null : $id;

    }

    public function handleFacebookVideoUploadErrorMessages($query_response) {

        Log::withContext([
            'origin' => 'UploadMedia Job - handleFacebookVideoUploadErrorMessages',
            'post_id' => $this->post->id,
            'post_platform' => $this->post->platform,
            'post_name' => $this->post->post_name
        ]);

        // Get the error code
        $status = $query_response['status'];
        
        // Set a default
        $this->error_message = "There was an issue uploading your media, trying again now.";

        // Do we release?
        $release = true;

        // Switch the case depending on the result
        if (Str::contains($status, "Media upload has failed with error code")) {

            // Get the error code
            $error_code = explode(" ", $status);
            $error_code = end($error_code);

            // Switch the error code
            if ($error_code == "2207026") {
                $release = false;
                $this->error_message = "The video format is not supported. Have you made sure you're uploading a MP4 file that is no bigger than 1920x1080 and 90 seconds long?";
            } elseif ($error_code == "2207001") {
                // "Instagram server error, please try again"
                $this->error_message = "The Facebook servers are currently having difficulties. This upload will be re-tried in a few seconds.";
            } else {
                Log::warning("handleFacebookVideoUploadErrorMessages - Unhandled error code - $error_code");
                Log::warning($status);
            }
        }

        // Set the message
        Log::info("DISPLAYED to user: Reel upload error - " . $this->error_message);

        // Update the Notion object
        UpdateNotionPostInDatabaseAfterUpload::dispatch(
            false, // Success level
            $this->error_message, // Message we want to share?
            $this->post // The post object,
        );



        // Mark the job as failed
        if ($release) {
            $this->release(30);
        }
        
        // $this->fail($this->error_message);
        return;


    }


    public function errorHandler($line, $response, $container_id = null) {

        $release_time = 30;
        $should_retry = true;

        Log::withContext([
            'origin' => 'UploadMedia Job - errorHandler',
            'post_id' => $this->post->id,
            'post_platform' => $this->post->platform,
            'post_name' => $this->post->post_name
        ]);

        Log::info("UploadMedia encountered an error on line $line for attempt #" . $this->attempts() . " on post with ID " . $this->post->id);

        /**
         * TODO - 
         * 
         * We need to manage the errors coming in, and decide if it's worth alerting the user or marking the job as failed and trying again
         * 
         * TODO - Test if fail()'ing the job has it re-run?
         * From the docs it seems like fail()'ing the job will result in it being tried again until we've reached the max tries
         * 
         * So in theory we should update the user's Notion every time the job fails. This way if the job then succeeds, we could update it afterwards and mark it as actually succeeded next time around
         */

        // Make pretty
        $rep = $response->json();

        // Create a generic error message?
        $generic = "There was an unhandled error submitting your post, admins have been notified and will look into it ASAP.";
        $this->error_message = $generic;
        $release_job = true;

        /**
         * SECTION - Facebook
         */
        if ($this->post->platform == 'facebook') {

            if (isset($rep['error']['message'])) {

                 // Define
                $umsg = $rep['error']['message'];
                $subcode = $rep['error']['error_subcode'] ?? null;

                // Switch 
                if ($subcode == 2207003) {

                    $this->error_message = "Facebook took too long to download the file, trying again shortly.";

                // CASE - Changed password
                } elseif ($subcode == 460) {

                    $this->error_message = "Your Facebook / Instagram password was changed recently, you'll need to reconnect your account to NotionScheduler";
                    $should_retry = false;


                // CASE - Other stuff
                } else {

                    Log::warning("UploadMedia Job - HandleFacebookErrorMessages - UNHANDLED - One of our users encountered an error when uploading - " . $this->error_message);
                    Log::warning($rep);
                }
                

            } else {

                Log::warning("UploadMedia Job - HandleFacebookErrorMessages - Unhandled error - ");
                Log::warning($rep);

            }

        }



        /**
         * SECTION - Facebook
         */
        if ($this->post->platform == 'instagram') {

            if (isset($rep['error']['message'])) {
                
                if (Str::of($rep['error']['message'])->contains("An unexpected error has occurred. Please retry your request later")) {

                    $this->error_message = "Facebook's API is encountering downtime issues. Retrying your upload shortly";

                } elseif (Str::of($rep['error']['message'])->contains("The user is not an Instagram Business")) {

                    $this->error_message = "It looks like the account you're posting to is no longer an Instagram Business Account. Scheduling tools are only compatible with Instagram Business / Creator accounts.";
                    $should_retry = false;
                }
            }

            if (isset($rep['error']['error_subcode'])) {

                $subcode = $rep['error']['error_subcode'];

                // CASE - Container isn't ready, usually happens if things take too long to upload
                if ($subcode == 2207027) {
                    Log::info("Instagram post with id " . $this->post->id . " has taken too long to process, moving this job over to the UploadMediaInstagramDelayedPublication job...");
                    UploadMediaInstagramDelayedPublication::dispatch(
                        $this->post,
                        $this->social_account,
                        $container_id
                    );
                    return;

                // CASE - Took too long to download media, try again
                } elseif ($subcode == 2207003) {

                    $this->error_message = "Facebook couldn't download your media, it took too long to download, trying again shortly.";

                // CASE - Failed to create media container, let's try again
                } elseif ($subcode == 2207032) {

                    $this->error_message = "There was an issue with Facebook's API while uploading your content, trying again shortly.";

                // CASE - Performing too many actions, lets slow down a bit
                } elseif ($subcode == 2207069) {

                    $this->error_message = "Upload is taking slightly longer than usual, your post is still being submitted...";
                    $release_time = 90;

                // CASE - Application request limit reached, action is blocked - This could be due to an outage or an issue somewhere else, lets slow down the pace a bit...
                } elseif ($subcode == 2207051) {

                    $this->error_message = "There seems to be an outage with Facebook's API, trying again shortly...";
                    $release_time = 60 * $this->attempts();

                // CASE - User has changed their password and needs to authenticate again
                } elseif ($subcode == 460) {

                    $this->error_message = "Your Facebook / Instagram password was changed recently, you'll need to reconnect your account to NotionScheduler";
                    $should_retry = false;

                // CASE - "User is restricted" / "This Instagram account is restricted"
                }  elseif ($subcode == 2207050) {

                    $this->error_message = "Meta has temporarily restricted this Instagram account, so the post could not be published. Please check the account status directly in Instagram or Meta Business Manager.";
                    $should_retry = false;

                }

                
            }
        }

        /**
         * SECTION - Threads
         */
        if ($this->post->platform == 'threads') {

            if (isset($rep['error']['error_subcode'])) {
                $subcode = $rep['error']['error_subcode'];

                if ($subcode == 2207003) {
                    $this->error_message = "Facebook couldn't download your media, it took too long to download, trying again shortly.";
                    Log::info("Took too long to upload post with ID #" . $this->post->id . ", trying again...");
                }

                // This error occurs (or has occurred at least once in testing) when a Carousel is being posted and one of the media files seemingly isn't ready yet? Maybe? And so it just crashes with this unknown and completely unhelpful error
                if ($subcode == 4279004) {
                    $this->error_message = "The Threads API is currently running a bit slow. We'll try submitting your post again shortly";
                }

            } elseif (isset($rep['error']['message'])) {

                $emsg = Str::of($rep['error']['message']);

                if ($emsg->contains("An unexpected error has occurred. Please retry your request later.")) {
                    $this->error_message = "Upload is taking slightly longer than usual, your post is still being submitted...";
                    $release_time = 90;
                }

            }


        }

        /**
         * SECTION - TikTok
         */
        if ($this->post->platform == 'tiktok') {

            if (isset($rep['error']['code'])) {

                $code = $rep['error']['code'];
    
                if ($code == 'spam_risk_too_many_pending_share') {

                    $this->error_message = "To reduce spamming, TikTok limits the number of videos that can be uploaded via API that are not pending approval and posting by the creator. There may be at most 5 pending shares within any 24-hour period.";
                    $release_time = 3600;
    
                } else if ($code == 'spam_risk_user_banned_from_posting') {

                    $this->error_message =  "The user is banned from making new posts.";
                    $should_retry = false;
    
                } else if ($code == 'access_token_invalid') {

                    $this->error_message =  "Your TikTok access token is either invalid or expired. Try adding your TikTok account to NotionScheduler again to sort this issue.";
                    $should_retry = false;
    
                } else if ($code == 'scope_not_authorized') {
                    // TODO - Handle it?
                    $this->error_message = "It looks like you don't have the necessary authorizations to post to TikTok using NotionScheduler. Try adding your TikTok account to NotionScheduler again to sort this issue.";
                    $should_retry = false;
    
                } else if ($code == 'spam_risk_too_many_posts') {

                    $this->error_message = "You've reached TiKTok's limit on daily posts, please try again tomorrow.";
                    $should_retry = false;
    
                } else if ($code == 'app_version_check_failed') {

                    $this->error_message = "You're using an outdated version of TikTok, you'll need to update it for this to work.";
                    $should_retry = false;
    
                } else {
                    Log::info("UploadMedia 1256 - UNHANDLED");
                    Log::info($rep);
                    $this->error_message = "An unknown error occurred uploading your video to TikTok. Admins have been notified and will look into it shortly.";
                }
            } else {
                $this->error_message = "An unknown error occurred uploading your video to TikTok. Admins have been notified and will look into it shortly.";
                Log::info("UploadMedia 1260 - No error code in Tiktok, what do?");
                Log::info($rep);
            }
            
        }


        /**
         * SECTION - LinkedIn
         */
        if ($this->post->platform == 'linkedin') {

            if (isset($rep['errorDetails']['inputErrors'])) {
                $this->error_message = "";
                foreach ($rep['errorDetails']['inputErrors'] as $r) {
                    $this->error_message .= $r['description'];
                }
            } else {
                if (isset($rep['message'])) {
                    if (str_contains($rep['message'], "Resource level throttle")) {
                        $this->error_message = "It looks like you've tried this action too many times, LinkedIn throttles the number of times you can access this resource. Please try again in 24 hours.";
                    } else {
                        Log::info("UploadMediaJob - HandleLinkedInErrorMessages - Unhandled error");
                        Log::info($rep);
                    }
                } else {
                    Log::info("UploadMediaJob - HandleLinkedInErrorMessages - Unhandled error");
                    Log::info($rep);
                }
                
            }

            
        }

        /**
         * SECTION - Twitter
         */
        if ($this->post->platform == 'twitter') {
            Log::info("Twitter errors are still unhandled in UploadMedia...");
            Log::info($rep);
            $this->error_message = $rep['detail'];

        }

        // Check to see if the error message is still displayed as generic
        if ($this->error_message == $generic) {
            Log::warning("Error was UNHANDLED, here is the raw response");
            Log::info($rep);
        }

        // Log the encountered error
        Log::info("The error handler ended up with the following error message: " . $this->error_message);

        // Dispatch a Notion failure
        UpdateNotionPostInDatabaseAfterUpload::dispatch(
            false, // Success level
            $this->error_message, // Message we want to share?
            $this->post // The post object,
        );

        if ($should_retry) {
            Log::info("Re-attempting job for post ID " . $this->post->id . " in $release_time seconds. Attempt #" . $this->attempts() . "failed.");
            
            // Only release if we haven't hit max attempts defined in the Job class
            $this->release($release_time);

        } else {
            Log::error("Hard Failure for post ID " . $this->post->id . ". Not retrying. Error: " . $this->error_message);
            
            // Mark as failed explicitly so it moves to 'failed_jobs' table immediately
            // You can pass an Exception or a string
            $this->fail(new \Exception($this->error_message));
        }

    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Send user notification of failure, etc...
        Log::info("Failed job handler");
        Log::info($exception);

        // Make post as error
        $this->post->status = 'error';
        $this->post->in_flight = 0;
        $this->post->save();
        
    }
}