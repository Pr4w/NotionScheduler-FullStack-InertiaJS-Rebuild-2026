<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Enums\SocialNetworks;
use \FFMpeg\FFProbe;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Models\User;

// NOTE: Guava\Calendar Eventable integration (toCalendarEvent) is deferred to
// Phase 6 (Filament admin), where guava/calendar gets reinstalled.
class NotionPosts extends Model
{
    // Define the table name for this model
    protected $table = 'notion_posts';
    protected $primaryKey = 'id';

    protected $hidden = [
        'userid',
        'token_id',
    ];

    protected $fillable = [
        'userid',
        'page_id',
        'account_id',
        'post_name',
        'platform',
        'status',
        'scheduled_date',
        'post_date',
        'posted_date',
        'is_valid',
        'is_active',
        'database_id',
        'post_page_id',
        'platform_is_story',
        'posted_foreign_id',
        'in_flight',
        'in_flight_start',
        'metrics_last_scraped_at'
    ];

    protected $appends = [
        // 'is_subscribed', 
        'permalink'
    ];

    /**
     * NOTE - Relationships
     */
     public function metrics(): HasMany
    {
        return $this->hasMany(NotionPostMetric::class, 'content_id');
    }

    public function latestMetrics(): HasOne
    {
        return $this->hasOne(NotionPostLatestMetric::class, 'content_id');
    }

    public function latestMetricFor(string $platform): ?NotionPostLatestMetric
    {
        return $this->latestMetrics->firstWhere('platform', $platform);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(NotionSocialAccounts::class, 'account_id', 'id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'userid', 'id');
    }


    protected function permalink(): Attribute {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if ($attributes['status'] == 'posted') {
                    if ($attributes['platform'] == 'facebook') {
                        return 'https://facebook.com/' . $attributes['posted_foreign_id'];
                    }
                    if ($attributes['platform'] == 'instagram') {
                        if (!$attributes['platform_is_story']) {
                            return self::instagram_id_to_url($attributes['posted_foreign_id']);
                        }
                    }
                    return "https://google.com";
                }
                return null;
            }
        );
    }

    public static function instagram_id_to_url ($instagram_id){

        $url_prefix = "https://www.instagram.com/p/";
    
        if(!empty(strpos($instagram_id, '_'))){
    
            $parts = explode('_', $instagram_id);
    
            $instagram_id = $parts[0];
    
            $userid = $parts[1];
    
        }
    
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $url_suffix = '';
    
        while($instagram_id > 0){
    
            $remainder = $instagram_id % 64;
            $instagram_id = ($instagram_id-$remainder) / 64;
            $url_suffix = $alphabet[$remainder] . $url_suffix;
    
        };
    
        return $url_prefix.$url_suffix;
    
    }

    // const BASE64URL_CHARMAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    // const BASE10_MOD2 = ['0', '1', '0', '1', '0', '1', '0', '1', '0', '1'];
    // public static $bitValueTable = null;
    // public static function buildBinaryLookupTable($maxBitCount) {
    //     $table = [];
    //     for ($bitPosition = 0; $bitPosition < $maxBitCount; ++$bitPosition) {
    //         $bitValue = bcpow('2', (string) $bitPosition, 0);
    //         $table[] = $bitValue;
    //     }

    //     return $table;
    // }
    // public static function base10to2($base10, $padLeft = true) {
    //     $base10 = (string) $base10;
    //     if ($base10 === '' || preg_match('/[^0-9]/', $base10)) {
    //         throw new \InvalidArgumentException('Input must be a positive integer.');
    //     }

    //     // Convert the arbitrary-length base10 input to a base2 binary string.
    //     // We process as strings to support unlimited input number sizes!
    //     $base2 = '';
    //     do {
    //         // Get the last digit.
    //         $lastDigit = $base10[(strlen($base10) - 1)];

    //         // If the last digit is uneven, put a one (1) in the base2 string,
    //         // otherwise use zero (0) instead. Array is 10x faster than bcmod.
    //         $base2 .= self::BASE10_MOD2[$lastDigit];

    //         // Now divide the whole base10 string by two, discarding decimals.
    //         // NOTE: Division is unavoidable when converting decimal to binary,
    //         // but at least it's implemented in pure C thanks to the BC library.
    //         // An implementation of arbitrary-length division by 2 in just PHP
    //         // was ~4x slower. Anyway, my old laptop can do ~1.6 million bcdiv()
    //         // per second so this is no problem.
    //         $base10 = bcdiv($base10, '2', 0);
    //     } while ($base10 !== '0');

    //     // We built the base2 string backwards, so now we must reverse it.
    //     $base2 = strrev($base2);

    //     // Add or remove proper left-padding with zeroes as needed.
    //     if ($padLeft) {
    //         $padAmount = (8 - (strlen($base2) % 8));
    //         if ($padAmount != 8 || strlen($base2) === 0) {
    //             $base2 = str_repeat('0', $padAmount).$base2;
    //         }
    //     } else {
    //         $base2 = ltrim($base2, '0');
    //     }

    //     return $base2;
    // }
    // public static function base2to10($base2) {
    //     if (!is_string($base2) || preg_match('/[^01]/', $base2)) {
    //         throw new \InvalidArgumentException('Input must be a binary string.');
    //     }

    //     // Pre-build a ~80kb RAM table with all values for bits 1-512. Any
    //     // higher bits than that will be generated and cached live instead.
    //     if (self::$bitValueTable === null) {
    //         self::$bitValueTable = self::buildBinaryLookupTable(512);
    //     }

    //     // Reverse the bit-sequence so that the least significant bit is first,
    //     // which is necessary when converting binary via its bit offset powers.
    //     $base2rev = strrev($base2);

    //     // Process each bit individually and reconstruct the base10 number.
    //     $base10 = '0';
    //     $bits = str_split($base2rev, 1);
    //     for ($bitPosition = 0, $len = count($bits); $bitPosition < $len; ++$bitPosition) {
    //         if ($bits[$bitPosition] == '1') {
    //             // Look up the bit value in the table or generate if missing.
    //             if (isset(self::$bitValueTable[$bitPosition])) {
    //                 $bitValue = self::$bitValueTable[$bitPosition];
    //             } else {
    //                 $bitValue = bcpow('2', (string) $bitPosition, 0);
    //                 self::$bitValueTable[$bitPosition] = $bitValue;
    //             }

    //             // Now just add the bit's value to the current total.
    //             $base10 = bcadd($base10, $bitValue, 0);
    //         }
    //     }

    //     return $base10;
    // }

    // public static function convertInstagramPostIDToShortcode($id) {
    //     // First we must convert the ID number to a binary string.
    //     // NOTE: Conversion speed depends on number size. With the most common
    //     // number size used for Instagram's IDs, my old laptop can do ~18k/s.
    //     $base2 = self::base10to2($id, false); // No left-padding. Throws if bad.
    //     if ($base2 === '') {
    //         return ''; // Nothing to convert.
    //     }

    //     // Left-pad with leading zeroes to make length a multiple of 6 bits.
    //     $padAmount = (6 - (strlen($base2) % 6));
    //     if ($padAmount != 6 || strlen($base2) === 0) {
    //         $base2 = str_repeat('0', $padAmount).$base2;
    //     }

    //     // Now chunk it in segments of 6 bits at a time. Every 6 "digits" in a
    //     // binary number is just 1 "digit" in a base64 number, because base64
    //     // can represent the values 0-63, and 63 is "111111" (6 bits) in base2.
    //     // Example: 9999 base10 = 10 011100 001111 base2 = (2, 28, 15) base64.
    //     $chunks = str_split($base2, 6);

    //     // Process and encode all chunks as base64 using Instagram's alphabet.
    //     $encoded = '';
    //     foreach ($chunks as $chunk) {
    //         // Interpret the chunk bitstring as an unsigned integer (0-63).
    //         $base64 = bindec($chunk);

    //         // Look up that base64 character in Instagram's alphabet.
    //         $encoded .= self::BASE64URL_CHARMAP[$base64];
    //     }

    //     return $encoded;
    // }


    public static function removeDashesFromPageId($id) {

        return str_replace('-', '', $id);

    }

    // public function account(): HasOne
    // {
    //     return $this->hasOne(NotionSocialAccounts::class, 'id', 'account_id');
    // }
    

    public static function getAllContentFromChildren($contents) {

        // Loop through the content
        $content_final = [];
        foreach ($contents as $content) {
            if (isset($content->text)) {
                if (is_array($content->text)) {
                    $new_block = "";
                    foreach ($content->text as $ctextunit) {
                        $new_block .= $ctextunit->toString();
                    }
                    $content_final[] = $new_block;
                } else {
                    // NOTE - We really never should arrive here, but you never know...
                    // No text, means we should break line
                    $content_final[] = "";
                }
            }
        }

        // Okay so we got all the content we need now, lets make it pretty
        $content_final = implode("\r\n", $content_final);

        return $content_final;

    }

    public static function getTypeFromMediaProp($media_file) {
        if ($media_file->type instanceof \Notion\Common\FileType) {
            $type = $media_file->type->value;
        } else {
            $type = $media_file->type;
        }
        return $type;
    }

    public static function getAllMediaFromProps2($media_base) {

        // Init
        $media = [];

        foreach ($media_base as $media_file) {

            $media[] = [
                'url' => $media_file->url,
                'filename' => $media_file->name,
                'extension' => strtolower(pathinfo(parse_url($media_file->url, PHP_URL_PATH), PATHINFO_EXTENSION)),
                'type' => self::getTypeFromMediaProp($media_file),
            ];
        }

        return $media;

    }

    public static function getThumbnailFromProps2($thumbnail_base) {

        // Init
        $media = [];

        foreach ($thumbnail_base as $media_file) {
            return [
                'url' => $media_file->url,
                'filename' => $media_file->name,
                'extension' => strtolower(pathinfo(parse_url($media_file->url, PHP_URL_PATH), PATHINFO_EXTENSION)),
                'type' => self::getTypeFromMediaProp($media_file),
            ];
        }

        return $media;

    }

    // public static function getThumbnailFromProps($thumbnail_base) {

    //     if (isset($thumbnail_base['files'])) {
    //         if (is_array($thumbnail_base['files'])) {
    //             if (!empty($thumbnail_base['files'])) {
    //                 // Get the first one only
    //                 $url = $thumbnail_base['files'][0]['file']['url'];
    //                 $parse_url = parse_url($url, PHP_URL_PATH);
    //                 return [
    //                     'url' => $url,
    //                     'filename' => pathinfo($parse_url, PATHINFO_FILENAME),
    //                     'extension' => strtolower(pathinfo($parse_url, PATHINFO_EXTENSION))
    //                 ];
    //             }
    //         }
    //     }

    //     return false;

    // }

    public static function getImageFileTypes() {
        return ["jpg","jpeg","png","gif","tiff","heif"];
    }
    public static function getVideoFileTypes() {
        return ["mov","avi","mp4"];
    }
    public static function getExtensionFromMedia($media_file) {
        return strtolower(pathinfo(parse_url($media_file, PHP_URL_PATH), PATHINFO_EXTENSION));
    }
    public static function getFilenameFromMedia($media_file) {
        return pathinfo(parse_url($media_file, PHP_URL_PATH), PATHINFO_FILENAME);
    }

    public static function joinRequirements(...$arrays) {
        $arr = [];
        foreach ($arrays as $array) {
            $arr = array_merge($arr, $array);
        }
        $arr = array_unique($arr);
        return (implode(', ', $arr));
    }

    public static function checkVideoFile($url) {
        
        $probe = FFProbe::create();
        $properties = $probe
            ->streams( $url )   // extracts streams informations
            ->videos()                      // filters video streams
            ->first()
            ->all();

        return [
            'height' => $properties['height'],
            'width' => $properties['width'],
            'aspect_ratio' => $properties['width'] / $properties['height'],
            'duration' => $properties['duration'],
            'frame_rate' => $properties['avg_frame_rate'],
            'size' => self::getContentLength($url)
        ];

    }

    public static function getContentLength($url) {
        $headers = get_headers($url, 1);
        if (!isset($headers['Content-Length'])) {
            Log::error("NotionPosts - 385  - Error - No content length defined");
            Log::error($headers);
            Log::info('Returning 0 as a default');
            return 0;
        }
        return $headers['Content-Length'];
    }

    public static function storeFileInLocalStorage($userid, $media) {

        $folder = 'public/uploadable_media/';
        $filename = 'user' . $userid . '_rand' . bin2hex(random_bytes(10)) . '-' . Carbon::now()->timestamp . '.' . $media['extension'];
        $store = Storage::put(
            $folder . $filename,
            fopen($media['url'], 'r')
        );
        return 'https://api.notionscheduler.app/storage/uploadable_media/' . $filename;

    }


    public static function checkPostIsValid($post, $content = null, $media = null, $thumbnail = null) {

        // Init
        $success = true;
        $errors = [];
        $probe = [];

        // Perform all the checks
        $is_content_empty = empty(trim($content));

        // SECTION - General checks
        try {

            // CASE - Check the media type to make sure it's INTERNAL only
            // if ($post->id > 321) {
            try {
                if (is_array($media)) {
                    foreach ($media as $m) {
                        if ($m['type'] != 'file') {
                            // Log::warning("CheckPostIsValid 441 - Found a media that isn't a file type, you might want to debug it - ID is " . $post->id . " and the media array is...");
                            // Log::info($m['type']);
                            $errors[] = "Media files should be directly uploaded to Notion, not added as external URLs.";

                            return [
                                'success' => false,
                                'errors' => $errors,
                                'probe' => $probe 
                            ];

                            break;
                        }
                        // if ($m['type'] != 'file') {
                        //     Log::warning("CheckPostIsValid 439 - Found a media that isn't a file type, you might want to debug it - ID is " . $post->id . " and the media array is...");
                        //     Log::warning($media);
                        //     Log::info($m['type']->value);
                        //     // $errors[] = "Media files should be directly uploaded to Notion, not added as external URLs.";
                        //     break;
                        // } else {
                        //     Log::info($m['type']->value);
                        // }
                    }
                }
            } catch (\Exception $e) {
                Log::warning(464);
                Log::info($e);
            }

            try {
                if (is_array($thumbnail)) {
                    if (isset($thumbnail['type'])) {
                        if ($thumbnail['type'] != 'file') {
                            Log::warning("CheckPostIsValid 463 - Found a thumbnail that isn't a file type, you might want to debug it - ID is " . $post->id . " and the media array is...");
                            $errors[] = "Thumbnail files should be directly uploaded to Notion, not added as external URLs.";

                            return [
                                'success' => false,
                                'errors' => $errors,
                                'probe' => $probe 
                            ];
                            
                        }
                    }
                    
                }
            } catch (\Exception $e) {
                Log::warning(473);
                Log::info($e);
            }
            
            // }

            // Get the user submitting the post
            $user = User::find($post->userid);
            $active_socials = $user->getTotalSocialAccountsConnectedToDatabases();
            $package = $user->getSubscriptionOptions();
            $max_socials = $package['social_accounts'];

            // CASE - Check to see if he has too many social accounts connected
            if ($active_socials > $max_socials) {
                // Log::warning("CheckPostIsValid - TODO / UNHANDLED 471");
                // Log::info($package);
                // Log::info($active_socials);
                $errors[] = "Your NotionScheduler tier doesn't allow you to handle this many social media accounts. You currently have $active_socials out of a maximum of $max_socials accounts connected to your NotionScheduler databases. Please remove some accounts from this database via your Dashboard and try again.";
            }

            // CASE - Check to see if he is over his post limit
            if ($package['post_limit']) {
                $post_count = NotionPosts::where('userid', $user->id)
                    ->where('status', 'posted')
                    ->where('posted_date', '>', Carbon::now()->subMonth())
                    ->get()
                    ->count();

                if ($post_count > $package['post_limit_count']) {
                    $errors[] = "Your subscription tier grants you a maximum of " . $package['post_limit_count'] . " social media publications per month. Head over to the subscribe page to remove this limitation.";
                }
            }

        } catch (\Throwable $e) {
            Log::info(455);
            Log::info($e);
        }



        // SECTION - Facebook
        if ($post->platform == 'facebook') {

            // Set the social
            $requirements = SocialNetworks::FACEBOOK->requirements();

            // CASE - Simple text post
            if (empty($media)) {
                if ($is_content_empty) {
                    $errors[] = "Facebook posts can't have an empty caption.";
                }
            }

            // CASE - Is story?
            if ($post->platform_is_story) {

                if (count($media) == 0) {
                    $errors[] = "You don't have any media attached for this Facebook story.";
                } elseif (count($media) > 1) {
                    $errors[] = "You can only post ony Facebook story at a time. Create multiple posts if you would like to post multiple stories.";
                } else {
                    // CASE - Story post is a video
                    if (in_array($media[0]['extension'], $requirements->story->video->extensions)) {

                        // Check the video file
                        $probe = self::checkVideoFIle($media[0]['url']);

                        // Check duration
                        if (
                            $probe['duration'] < $requirements->story->video->min_duration or 
                            $probe['duration'] > $requirements->story->video->max_duration
                        ) {
                            $errors[] = "Facebook video stories have to last between " . $requirements->story->video->min_duration . ' and ' . $requirements->story->video->max_duration . ' seconds';
                        }

                        // Check size
                        // if ($probe['size'] > $requirements->story->video->max_size) {
                        //     $errors[] = "Facebook video stories can't weigh more than " . round($requirements->story->video->max_size / 1024 / 1024) . "MB";
                        // }

                        // Check dimensions
                        if (
                            $probe['height'] < $requirements->story->video->min_height or 
                            $probe['width'] < $requirements->story->video->min_width or
                            $probe['height'] > $requirements->story->video->max_height or 
                            $probe['width'] > $requirements->story->video->max_width
                        ) {
                            $errors[] = "Facebook video stories dimensions should be between " .
                            $requirements->story->video->min_width.'x'.$requirements->story->video->min_height
                            . ' and ' . 
                            $requirements->story->video->max_width.'x'.$requirements->story->video->max_height;
                        }


                    // CASE - Story post is photo
                    } elseif (in_array($media[0]['extension'], $requirements->story->photo->extensions)) {

                        // Run checks?
                        $file_size = self::getContentLength($media[0]['url']);

                        // Check
                        if ($file_size > $requirements->story->photo->max_size) {
                            $errors[] = "Facebook photo stories can't weigh more than " . round($requirements->story->photo->max_size / 1024 / 1024) . "MB";
                        }


                    // CASE - Post is neither a valid photo or video format
                    } else {
                        $errors[] = "Facebook stories only accept the following file types: " . self::joinRequirements(
                            $requirements->story->video->extensions,
                            $requirements->story->photo->extensions
                        );
                    }
                }

            // CASE - Not a story
            } else {

                // CASE - Single post
                if (count($media) == 1) {

                    // CASE - Photo post
                    if (in_array($media[0]['extension'], $requirements->image->extensions)) {

                    // CASE - Video or reel
                    } elseif (
                        in_array($media[0]['extension'], $requirements->video->extensions) or 
                        in_array($media[0]['extension'], $requirements->reel->extensions)
                    ) {

                        // Probe
                        $probe = self::checkVideoFIle($media[0]['url']);

                        // CASE - Reel
                        if ($probe['aspect_ratio'] == 9/16) {

                            // Check extension
                            if (!in_array($media[0]['extension'], $requirements->reel->extensions)) {
                                $errors[] = "Facebook reels only accept the following file types: " . self::joinRequirements(
                                    $requirements->reel->extensions
                                );
                            }

                            // Check duration
                            if (
                                $probe['duration'] < $requirements->reel->min_duration or 
                                $probe['duration'] > $requirements->reel->max_duration
                            ) {
                                $errors[] = "Facebook reels have to last between " . $requirements->reel->min_duration . ' and ' . $requirements->reel->max_duration . ' seconds';
                            }

                            // Check size
                            // if ($probe['size'] > $requirements->story->video->max_size) {
                            //     $errors[] = "Facebook video stories can't weigh more than " . round($requirements->story->video->max_size / 1024 / 1024) . "MB";
                            // }

                            // Check dimensions
                            if (
                                $probe['height'] < $requirements->reel->min_height or 
                                $probe['width'] < $requirements->reel->min_width or 
                                $probe['height'] > $requirements->reel->max_height or 
                                $probe['width'] > $requirements->reel->max_width
                            ) {
                                $errors[] = "Facebook reel dimenseions should be between " .
                                $requirements->reel->min_width.'x'.$requirements->reel->min_height
                                . ' and ' . 
                                $requirements->reel->max_width.'x'.$requirements->reel->max_height;
                            }

                        // CASE - Regular video
                        } else {

                            // Check extension
                            if (!in_array($media[0]['extension'], $requirements->video->extensions)) {
                                $errors[] = "Facebook videos only accept the following file types: " . self::joinRequirements(
                                    $requirements->video->extensions
                                );
                            }

                            // Check duration
                            if (
                                $probe['duration'] < $requirements->video->min_duration or 
                                $probe['duration'] > $requirements->video->max_duration
                            ) {
                                $errors[] = "Facebook videos have to last between " . $requirements->video->min_duration . ' and ' . $requirements->video->max_duration . ' seconds';
                            }

                            // Check size
                            if ($probe['size'] > $requirements->video->max_size) {
                                $errors[] = "Facebook video stories can't weigh more than " . round($requirements->story->video->max_size / 1024 / 1024 / 1024) . "GB";
                            }

                            // Check dimensions
                            // if (
                            //     $probe['height'] < $requirements->reel->min_height or 
                            //     $probe['width'] < $requirements->reel->min_width or 
                            //     $probe['height'] > $requirements->reel->max_height or 
                            //     $probe['width'] > $requirements->reel->max_width
                            // ) {
                            //     $errors[] = "Facebook reel dimenseions should be between " .
                            //     $requirements->reel->min_width.'x'.$requirements->reel->min_height
                            //     . ' and ' . 
                            //     $requirements->reel->max_width.'x'.$requirements->reel->max_height;
                            // }


                        }

                    // CASE - Other
                    } else {
                        $errors[] = "Facebook stories only accept the following file types: " . self::joinRequirements(
                            $requirements->video->extensions,
                            $requirements->reel->extensions,
                            $requirements->image->extensions
                        );
                    }

                    

                }

                // CASE - Carousel
                if (count($media) > 1) {

                    // Loop through all the media
                    foreach ($media as $m) {

                        // Check extension
                        if (!in_array($m['extension'], $requirements->image->extensions)) {
                            $errors[] = "Facebook carousels can only contain content with the following file types: " . self::joinRequirements(
                                $requirements->image->extensions
                            );
                        }

                    }

                }

            }

            // CASE - Check the thumbnail
            if ($thumbnail) {
                if (!in_array($thumbnail['extension'], $requirements->thumbnail->extensions)) {
                    $errors[] = "Facebook video thumbnails can only have the following extensions: " . implode(", ",  $requirements->thumbnail->extensions);
                }
            }

        }

        // SECTION - Instagram
        if ($post->platform == 'instagram') {

            // Set the social
            $requirements = SocialNetworks::INSTAGRAM->requirements();

            $character_count = mb_strlen($content, "UTF-8");
            if ($character_count > $requirements->caption->max_characters) {
                $errors[] = "Instagram captions can't be over ".$requirements->caption->max_characters." characters";
            }

            // Check media count
            if (count($media) < 1) {
                $errors[] = "You have to have at least one media file in ordrer to upload to Instagram.";
            } else {

                // CASE - Story
                if ($post->platform_is_story) {

                    // Check count
                    if (count($media) > 1) {
                        $errors[] = "You can only post one Instagram story at a time. Create multiple posts if you would like to post multiple stories.";
                    } else {

                         // CASE - Story post is a video
                        if (in_array($media[0]['extension'], $requirements->story->video->extensions)) {

                            // Check the video file
                            $probe = self::checkVideoFIle($media[0]['url']);

                            // Check duration
                            if (
                                $probe['duration'] < $requirements->story->video->min_duration or 
                                $probe['duration'] > $requirements->story->video->max_duration
                            ) {
                                $errors[] = "Instagram video stories have to last between " . $requirements->story->video->min_duration . ' and ' . $requirements->story->video->max_duration . ' seconds';
                            }

                            // Check size
                            if ($probe['size'] > $requirements->story->video->max_size) {
                                $errors[] = "Instagram video stories can't weigh more than " . round($requirements->story->video->max_size / 1024 / 1024) . "MB";
                            }

                            // Check dimensions
                            if (
                                // $probe['height'] < $requirements->story->video->min_height or 
                                // $probe['width'] < $requirements->story->video->min_width or
                                $probe['height'] > $requirements->story->video->max_height // or 
                                // $probe['width'] > $requirements->story->video->max_width
                            ) {
                                $errors[] = "Instagram video stories can't be taller than " . $requirements->story->video->max_height . "px";
                            }


                        // CASE - Story post is photo
                        } elseif (in_array($media[0]['extension'], $requirements->story->photo->extensions)) {

                            // Run checks?
                            $file_size = self::getContentLength($media[0]['url']);

                            // Check
                            if ($file_size > $requirements->story->photo->max_size) {
                                $errors[] = "Instagram photo stories can't weigh more than " . round($requirements->story->photo->max_size / 1024 / 1024) . "MB";
                            }


                        // CASE - Post is neither a valid photo or video format
                        } else {
                            $errors[] = "Instagram stories only accept the following file types: " . self::joinRequirements(
                                $requirements->story->video->extensions,
                                $requirements->story->photo->extensions
                            );
                        }

                    }


                // CASE - Not story
                } else {

                    // Check media count
                    if (count($media) > $requirements->carousel->max_media) {
                        $errors[] = "Instagram Carousels can't contain more than 10 elements";
                    } else {
                        // Loop through all the media
                        foreach ($media as $m) {

                            // CASE - Photo
                            if (in_array($m['extension'], $requirements->image->extensions)) {

                                // Check
                                $file_size = self::getContentLength($m['url']);

                                // Check 
                                if ($file_size > $requirements->image->max_size) {
                                    $errors[] = "Instagram photos can't weigh more than " . round($requirements->image->max_size / 1024 / 1024) . "MB";
                                }

                            // CASE - Video
                            } elseif (in_array($m['extension'], $requirements->reel->extensions)) {

                                // Check the video file
                                $probe = self::checkVideoFIle($m['url']);

                                // Check duration
                                if (
                                    $probe['duration'] < $requirements->reel->min_duration or 
                                    $probe['duration'] > $requirements->reel->max_duration
                                ) {
                                    $errors[] = "Instagram video posts have to last between " . $requirements->reel->min_duration . ' and ' . $requirements->reel->max_duration . ' seconds';
                                }

                                // Check size
                                if ($probe['size'] > $requirements->reel->max_size) {
                                    $errors[] = "Instagram video posts can't weigh more than " . round($requirements->reel->max_size / 1024 / 1024 / 1024) . "GB";
                                }

                                // Check dimensions
                                if (
                                    // $probe['height'] < $requirements->reel->min_height or 
                                    // $probe['width'] < $requirements->reel->min_width or
                                    $probe['height'] > $requirements->reel->max_height // or 
                                    // $probe['width'] > $requirements->reel->max_width
                                ) {
                                    $errors[] = "Instagram video posts can't be taller than " . $requirements->reel->max_height . "px";
                                }


                            // CASE - Other
                            } else {
                                $errors[] = "Instagram posts only accept the following file types: " . self::joinRequirements(
                                    $requirements->image->extensions,
                                    $requirements->reel->extensions
                                );
                            }

                        }
                    }

                    


                }

                

            }

        }

        // SECTION - TikTok
        if ($post->platform == "tiktok") {

            // Set requiremenbts
            $requirements = SocialNetworks::TIKTOK->requirements();

            if (!$is_content_empty) {
                
                // $character_count = mb_strlen($content, "UTF-8");
                $character_count = strlen(mb_convert_encoding($content, 'UTF-16')) / 2;
                
                if ($character_count > $requirements->caption->max_characters) {
                    Log::info(878);
                    Log::info($content);
                    Log::info($character_count);
                    $errors[] = "TikTok posts can't have a caption that is over ".$requirements->caption->max_characters / 2 ." characters";
                }
            }


            // CASE - No media
            if (count($media) < 1) {
                $errors[] = "You have to have at least one media file in ordrer to upload to TikTok.";

            } elseif (count($media) > 35) {
                $errors[] = "You can't upload more than 35 images at once to a TikTok Carousel";

            // CASE - Has more than 1 media
            } elseif (count($media) > 1 && count($media) <= 35) {

                // Check if ALL of the files are images
                foreach ($media as $m) {
                    if (!in_array($m['extension'], $requirements->image->extensions)) {
                        $errors[] = "You can't upload a mix of images & videos on TikTok as a carousel. Images must have one of the following formats: " . self::joinRequirements($requirements->image->extensions);
                    }
                    break;
                }
            
            // CASE - Has exactly 1 media
            } else {

                // CASE - Photo
                if (in_array($media[0]['extension'], $requirements->image->extensions)) {

                    // Check
                    $file_size = self::getContentLength($media[0]['url']);

                    // Check 
                    if ($file_size > $requirements->image->max_size) {
                        $errors[] = "TikTok photo posts can't weigh more than " . round($requirements->image->max_size / 1024 / 1024) . "MB";
                    }


                // CASE - Video
                } elseif (in_array($media[0]['extension'], $requirements->video->extensions)) {

                    // Check the video file
                    $probe = self::checkVideoFIle($media[0]['url']);

                    // Check duration
                    if (
                        $probe['duration'] < $requirements->video->min_duration or 
                        $probe['duration'] > $requirements->video->max_duration
                    ) {
                        $errors[] = "TikTok video posts have to last between " . $requirements->video->min_duration . ' and ' . $requirements->video->max_duration . ' seconds';
                    }

                    // Check size
                    if ($probe['size'] > $requirements->video->max_size) {
                        $errors[] = "TikTok video posts can't weigh more than " . round($requirements->video->max_size / 1024 / 1024 / 1024) . "GB";
                    }

                    // Check dimensions
                    if (
                        $probe['height'] < $requirements->video->min_height or 
                        $probe['width'] < $requirements->video->min_width or
                        $probe['height'] > $requirements->video->max_height or 
                        $probe['width'] > $requirements->video->max_width
                    ) {
                        $errors[] = "TikTok video posts dimenseions should be between " .
                        $requirements->video->min_width.'x'.$requirements->video->min_height
                        . ' and ' . 
                        $requirements->video->max_width.'x'.$requirements->video->max_height;
                    }

                // CASE - Neither
                } else {
                    $errors[] = "Your file " . $media[0]['filename'] . " isn't a valid photo or video format, we won't be able to upload it. Only the following filetypes are authorized: " . 
                    self::joinRequirements($requirements->image->extensions, $requirements->video->extensions);
                }
            }

        }

        // SECTION - LinkedIn 
        if ($post->platform == 'linkedin') {

            // Set requiremenbts
            $requirements = SocialNetworks::LINKEDIN->requirements();

            // CASE - Empty content
            if ($is_content_empty) {
                $errors[] = "LinkedIn posts can't have an empty caption.";
            } else {

                // We have a caption, lets check the character count
                $character_count = mb_strlen($content, "UTF-8");
                if ($character_count > $requirements->caption->max_characters) {
                    $errors[] = "LinkedIn posts can't be over ".$requirements->caption->max_characters." characters";
                }

            }



            // CASE - Check media
            if (count($media) > 0) {

                // CASE - More than 1 media
                if (count($media) > 1 && count($media) <= 20) {

                    // Loop
                    foreach ($media as $m) {
                        if (!in_array($m['extension'], $requirements->image->extensions)) {
                            $errors[] = "LinkedIn Carousel posts can only contain images with the following filetypes: " . self::joinRequirements($requirements->image->extensions);
                            break;
                        }
                    }

                } elseif (count($media) > 20) {
                    $errors[] = "LinkedIn Carousel posts can't contain more than 20 images";

                // CASE - Has exactly 1 media
                } else {

                    // CASE - Photo
                    if (in_array($media[0]['extension'], $requirements->image->extensions)) {
                        // Do no checks?

                    // CASE - Video
                    } elseif (in_array($media[0]['extension'], $requirements->video->extensions)) {

                        // Check the video file
                        $probe = self::checkVideoFIle($media[0]['url']);

                        // Check duration
                        if (
                            $probe['duration'] < $requirements->video->min_duration or 
                            $probe['duration'] > $requirements->video->max_duration
                        ) {
                            $errors[] = "LinkedIn video posts have to last between " . $requirements->video->min_duration . ' and ' . $requirements->video->max_duration . ' seconds';
                        }

                        // Check size
                        if ($probe['size'] > $requirements->video->max_size) {
                            $errors[] = "LinkedIn video posts can't weigh more than " . round($requirements->video->max_size / 1024 / 1024) . "MB";
                        }

                    // CASE - Documents
                    } elseif (in_array($media[0]['extension'], $requirements->document->extensions)) {

                        // Do no checks? Maybe check the max file size?


                    // CASE - Other?
                    } else {

                        $errors[] = "You tried to upload a ." . $media[0]['extension'] . " file. Currenty LinkedIn uploads only support media with the following filetypes: " . self::joinRequirements(
                            $requirements->image->extensions, $requirements->video->extensions, $requirements->document->extensions
                        );

                    }
                }
            }
        }

        // SECTION - Twitter
        if ($post->platform == 'twitter') {

            // Get requirements
            $requirements = SocialNetworks::TWITTER->requirements();

            // CASE - Check if content is empty
            if ($is_content_empty) {
                $errors[] = "Twitter posts can't have an empty caption.";
            }

            // CASE - Has media
            if (count($media) > 0) {

                /**
                 * FIXME - REPLACE THIS
                 */
                if ($post->userid != 1) {
                    $errors[] = "Unfortunately, Twitter's API doesn't enable posting tweets with media at this time. As soon as this feature becomes availables we'll enable it.";
                } else {

                    // CASE - More than 4 files
                    if (count($media) > $requirements->image->max_count) {
                        $errors[] = "You can't attach more than " . $requirements->image->max_count . " to a single tweet.";
                    } else {

                        // CASE - Single media
                        if (count($media) < 2) {

                            // Run checks?
                            $file_size = self::getContentLength($media[0]['url']);

                            // CASE - Single gif
                            if (in_array($media[0]['extension'], $requirements->gif->extensions)) {

                                if ($file_size > $requirements->gif->max_size) {
                                    $errors[] = "GIFs uploaded to Twitter can't weigh more than " . round($requirements->gif->max_size / 1024 / 1024) . "MB";
                                }


                            // CASE - Single image
                            } elseif (in_array($media[0]['extension'], $requirements->image->extensions)) {

                                if ($file_size > $requirements->gif->max_size) {
                                    $errors[] = "Images uploaded to Twitter can't weigh more than " . round($requirements->image->max_size / 1024 / 1024) . "MB";
                                }


                            // CASE - Single video
                            } elseif (in_array($media[0]['extension'], $requirements->video->extensions)) {


                                 // Check the video file
                                $probe = self::checkVideoFIle($media[0]['url']);

                                 // Check duration
                                if (
                                    $probe['duration'] < $requirements->video->min_duration or 
                                    $probe['duration'] > $requirements->video->max_duration
                                ) {
                                    $errors[] = "Twitter videos have to last between " . $requirements->video->min_duration . ' and ' . $requirements->video->max_duration . ' seconds';
                                }

                                // Check size
                                if ($probe['size'] > $requirements->video->max_size) {
                                    $errors[] = "Twitter videos can't weigh more than " . round($requirements->video->max_size / 1024 / 1024) . "MB";
                                }

                                // Check dimensions
                                if (
                                    $probe['height'] > $requirements->video->max_height or
                                    $probe['width'] > $requirements->video->max_width
                                ) {
                                    $errors[] = "Twitter only accepts videos with the following resolutions: 1280x720, 720x1280, 720x720";
                                }
                            

                            // CASE - Isn't the right extension
                            } else {

                                $errors[] = "Twitter only allows you to attach media with the following file types: " . self::joinRequirements(
                                    $requirements->image->extensions,
                                    $requirements->video->extensions
                                );

                            }



                        // CASE - Carousel - 4 media files exactly
                        } else {

                            // Loop through the media
                            foreach ($media as $m) {

                                // Check the file type
                                if (!in_array($m['extension'], $requirements->image->extensions)) {

                                    $errors[] = "When attaching multiple media to a single tweet, Twitter only accepts the following file types: " . self::joinRequirements(
                                        $requirements->image->extensions
                                    );

                                } else {

                                    // Check
                                    $file_size = self::getContentLength($m['url']);

                                    // Check 
                                    if ($file_size > $requirements->image->max_size) {
                                        $errors[] = "Twitter Media can't weigh more than " . round($requirements->image->max_size / 1024 / 1024) . "MB";
                                    }

                                }

                            }



                        }


                    }

                    



                }
                
            }

            // CASE - Check tweet length
            $character_count = mb_strlen($content, "UTF-8");
            if ($character_count > $requirements->caption->max_characters) {
                $errors[] = "Twitter posts can't be over ".$requirements->caption->max_characters." characters";
            }

        }

        // SECTION - YouTube
        if ($post->platform == 'youtube') {

            // Get requirements
            $requirements = SocialNetworks::YOUTUBE->requirements();

            // CASE - Check if content is empty
            if ($is_content_empty) {
                $errors[] = "YouTube posts can't have an empty title.";
            } else {
                $character_count = mb_strlen($content, "UTF-8");
                if ($character_count > $requirements->title->max_characters) {
                    $errors[] = "YouTube video titles can't be over ".$requirements->title->max_characters." characters";
                }
            }

            // CASE - Has media
            if (count($media) < 0) {
                $errors[] = "YouTube uploads must have a video file attached.";
            } elseif (count($media) > 1) {
                $errors[] = "YouTube uploads can't have more than one video file";
            } else {

                // Make media pretty again
                $m = $media[0];

                // Check file type
                if (!in_array($m['extension'], $requirements->video->extensions)) {
                    $errors[] =  "YouTube uploads only accept the following file types: " . self::joinRequirements(
                        $requirements->video->extensions
                    );
                } else {

                    // Good to go?
                    // Do a checkVideoFile probe of sorts?

                }

            }

        }

        // SECTION - Threads
        if ($post->platform == 'threads') {

            // Get requirements
            $requirements = SocialNetworks::THREADS->requirements();

            // CASE - Check if content is empty
            if ($is_content_empty) {
                if (count($media) < 1) {
                    $errors[] = "Threads posts without images or videos should at least contain text.";
                }
            } else {
                $character_count = mb_strlen($content, "UTF-8");
                if ($character_count > $requirements->caption->max_characters) {
                    $errors[] = "Threads can't be longer than ".$requirements->caption->max_characters." characters";
                }
            }

            

            // Check the media count
            if (count($media) > $requirements->carousel->max_media) {
                $errors[] = "Threads Carousels can't have more than " . $requirements->carousel->max_media . ' items';
            } else {

                // Loop through all the media
                foreach ($media as $m) {

                    // CASE - Photo
                    if (in_array($m['extension'], $requirements->image->extensions)) {

                        // Check
                        $file_size = self::getContentLength($m['url']);

                        // Check 
                        if ($file_size > $requirements->image->max_size) {
                            $errors[] = "Threads photos can't weigh more than " . round($requirements->image->max_size / 1024 / 1024) . "MB";
                        }

                    // CASE - Video
                    } elseif (in_array($m['extension'], $requirements->video->extensions)) {

                        // Check the video file
                        $probe = self::checkVideoFIle($m['url']);

                        // Check duration
                        if (
                            $probe['duration'] < $requirements->video->min_duration or 
                            $probe['duration'] > $requirements->video->max_duration
                        ) {
                            $errors[] = "Threads video posts have to last between " . $requirements->video->min_duration . ' and ' . $requirements->video->max_duration . ' seconds';
                        }

                        // Check size
                        if ($probe['size'] > $requirements->video->max_size) {
                            $errors[] = "Threads video posts can't weigh more than " . round($requirements->video->max_size / 1024 / 1024 / 1024) . "GB";
                        }

                        // Check dimensions
                        if (
                            // $probe['height'] < $requirements->reel->min_height or 
                            // $probe['width'] < $requirements->reel->min_width or
                            $probe['height'] > $requirements->video->max_height // or 
                            // $probe['width'] > $requirements->reel->max_width
                        ) {
                            $errors[] = "Threads video posts can't be taller than " . $requirements->video->max_height . "px";
                        }


                    // CASE - Other
                    } else {
                        $errors[] = "Threads video posts only accept the following file types: " . self::joinRequirements(
                            $requirements->video->extensions,
                            $requirements->video->extensions
                        );
                    }

                }

            }

        }


        // Check
        if (count($errors) > 0) {
            $success = false;
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'probe' => $probe 
        ];

    }


    public static function checkPostIsValidBackup($platform, $content = null, $media = null, $thumbnail = null) {

        // Init
        $success = true;
        $errors = [];

        // Perform all the checks
        $is_content_empty = empty(trim($content));

        // NOTE - Facebook
        if ($platform == 'facebook') {
            if ($is_content_empty) {
                $errors[] = "Facebook posts can't have an empty caption.";
            }
        }

        // NOTE - Instagram
        if ($platform == 'instagram') {
            if (count($media) < 1) {
                $errors[] = "You have to have at least one media file in ordrer to upload to Instagram.";
            } else {
                foreach ($media as $m) {
                    if (!in_array($m['extension'], self::getImageFileTypes()) && !in_array($m['extension'], self::getVideoFileTypes())) {
                        $errors[] = "Your file " . $m['filename'] . " isn't a valid photo or video format, we won't be able to upload it.";
                    }
                }
            }
        }

        // NOTE - LinkedIn
        if ($platform == 'linkedin' /* or $platform == 'linkedin_page' */) {
            if ($is_content_empty) {
                $errors[] = "LinkedIn posts can't have an empty caption.";
            }
            if (count($media) > 0) {
                foreach ($media as $m) {
                    if (!in_array($m['extension'], self::getImageFileTypes()) && !in_array($m['extension'], self::getVideoFileTypes()) && !in_array($m['extension'], ['pdf'])) {
                        $errors[] = "Currently only image & video uploads are enabled on LinkedIn.";
                    }
                }
            }
            if (count($media) > 1) {
                foreach ($media as $m) {
                    if (in_array($m['extension'], self::getVideoFileTypes())) {
                        $errors[] = "Carousel / multi-image posts can only contain images. You can't upload a mix of images & videos on LinkedIn";
                    }
                }
            }
        }

        // NOTE - Twitter
        if ($platform == 'twitter') {
            if ($is_content_empty) {
                $errors[] = "Twitter posts can't have an empty caption.";
            }
            if (count($media) > 0) {
                $errors[] = "Unfortunately, Twitter's API doesn't enable posting tweets with media at this time. As soon as this feature becomes availables we'll enable it.";
            }
        }

        // NOTE - TikTok
        if ($platform == 'tiktok') {
            if (count($media) < 1) {
                $errors[] = "You have to have at least one media file in ordrer to upload to TikTok.";
            } elseif (count($media) > 1) {
                // Check if ALL of the files are images
                foreach ($media as $m) {
                    if (in_array($m['extension'], self::getVideoFileTypes())) {
                        $errors[] = "Multi-image posts can only contain images. You can't upload a mix of images & videos on TikTok";
                    }
                    break;
                }
            } else {
                if (!in_array($media[0]['extension'], self::getImageFileTypes()) && !in_array($media[0]['extension'], self::getVideoFileTypes())) {
                    $errors[] = "Your file " . $media[0]['filename'] . " isn't a valid photo or video format, we won't be able to upload it.";
                } else {
                    $headers = get_headers($media[0]['url'], 1);
                    $content_type = $headers['Content-Type'];
                    if (!in_array($content_type, ['video/mp4','video/quicktime','video/quicktime'])) {
                        $errors[] = "Your file " . $media[0]['filename'] . " isn't a valid photo or video format, we won't be able to upload it.";
                    }
                }
            }

            // Check the thumbnail
            if ($thumbnail) {
                if (!in_array($thumbnail['extension'], self::getImageFileTypes())) {
                    $errors[] = "Your TikTok thumbnail has to be an image.";
                }
            }
        }

        // Check
        if (count($errors) > 0) {
            $success = false;
        }


        return [
            'success' => $success,
            'errors' => $errors,
            'probe' => []
        ];



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