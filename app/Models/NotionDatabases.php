<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Notion\Databases\Database;
use Notion\Databases\DatabaseParent;

use App\Models\NotionSocialAccounts;


class NotionDatabases extends Model
{
    // Define the table name for this model
    protected $table = 'notion_databases';
    protected $primaryKey = 'id';

    protected $hidden = [
        'userid',
        'token_id',
        'last_check_scan',
        'last_check_scaffolding_scan',
        'last_check_for_new_posts',
    ];

    protected $fillable = [
        'is_active',
        'is_valid'
    ];

    public function token(): HasOne {
        return $this->hasOne(NotionAccessTokens::class, 'id', 'token_id');
    }

    public function socials(): HasMany {
        return $this->hasMany(NotionSocialAccounts::class, 'database_id', 'id');
    }




    /** 
     * NOTE - Don't forget to update your JS scripts if you update any of this!
     */
    static $scaffolding = [
        'title' => [
            'name' => "Notion Scheduler Database",
            'type' => null,
        ],
        'options' => [
            'type' => "\\Notion\\Databases\Properties\\SelectOption",
            'colors' => "\\Notion\\Common\Color"
        ],
        'properties' => [
            'schedule_post_date' => [
                'name' => '.Scheduled Post Date',
                'type' => "date",
                'notion_type' => "\\Notion\\Databases\\Properties\\Date",
                'column' => 'column_post_date'
            ],
            'files' => [
                'name' => '.Media & Files',
                'type' => 'files',
                'notion_type' => "\\Notion\\Databases\\Properties\\Files",
                'column' => 'column_media'
            ],
            'files_thumbnail' => [
                'name' => '.Media Thumbnail (optional)',
                'type' => 'files',
                'notion_type' => "\\Notion\\Databases\\Properties\\Files",
                'column' => 'column_media_thumbnail'
            ],
            'notion_status' => [
                'name' => 'NotionScheduler Status',
                'type' => 'select',
                'notion_type' => "\\Notion\\Databases\\Properties\\Select",
                'sub_options' => [
                    'scheduled' => [
                        'name' => 'Scheduled',
                        'color' => '\\Notion\\Common\\Color::Yellow'
                    ],
                    'posted' => [
                        'name' => 'Posted',
                        'color' => '\\Notion\\Common\\Color::Green'
                    ],
                    'error' => [
                        'name' => 'Error',
                        'color' => '\\Notion\\Common\\Color::Red'
                    ]
                    ],
                    'column' => 'column_ns_status'
            ],
            'social_accounts' => [
                'name' => '.Social media account',
                'type' => 'select',
                'notion_type' => "\\Notion\\Databases\\Properties\\Select",
                'column' => 'column_social_account'
            ],
            'is_ready' => [
                'name' => '.Ready to post?',
                'type' => 'checkbox',
                'notion_type' => "\\Notion\\Databases\\Properties\\Checkbox",
                'column' => 'column_is_ready'
            ],
            'is_story' => [
                'name' => '.Post this as story?',
                'type' => 'checkbox',
                'notion_type' => "\\Notion\\Databases\\Properties\\Checkbox",
                'column' => 'column_post_as_story'
            ],
            'comments' => [
                'name' => 'NotionScheduler Comments',
                'type' => 'rich_text',
                'notion_type' => "\\Notion\\Databases\\Properties\\RichTextProperty",
                'column' => 'column_ns_comments'
            ],
        ]

        // '' => [
        //     'name' => '',
        //     'type' => ''
        // ],


    ];

    /**
     * BETA scaffolding — properties still being trialled before a wider rollout.
     * These are only merged into the live scaffolding for the beta user (id 1), so
     * column names/types can be tweaked here without touching everyone's databases.
     *
     * Analytics columns: we scrape post metrics and push them back into these.
     * Branded names avoid colliding with a user's own columns (the scaffolding
     * matches by name). "Comment Count" is distinct from "NotionScheduler Comments".
     */
    static $betaScaffolding = [
        'metric_views' => [
            'name' => '.👀 Views',
            'type' => 'number',
            'notion_type' => "\\Notion\\Databases\\Properties\\Number",
            'column' => 'column_metric_views'
        ],
        'metric_likes' => [
            'name' => '.❤️ Likes',
            'type' => 'number',
            'notion_type' => "\\Notion\\Databases\\Properties\\Number",
            'column' => 'column_metric_likes'
        ],
        'metric_comments' => [
            'name' => '.💬 Comments',
            'type' => 'number',
            'notion_type' => "\\Notion\\Databases\\Properties\\Number",
            'column' => 'column_metric_comments'
        ],
        'metric_shares' => [
            'name' => '.🔁 Shares',
            'type' => 'number',
            'notion_type' => "\\Notion\\Databases\\Properties\\Number",
            'column' => 'column_metric_shares'
        ],
        'metric_saves' => [
            'name' => '.💾 Saves',
            'type' => 'number',
            'notion_type' => "\\Notion\\Databases\\Properties\\Number",
            'column' => 'column_metric_saves'
        ],
    ];

    /**
     * The user ids that receive beta scaffolding. Keep this tiny until we're happy
     * with the column names/behaviour, then fold $betaScaffolding into $scaffolding
     * for everyone.
     */
    const BETA_USER_IDS = [1];

    public static function isBetaUser($userId): bool
    {
        return $userId !== null && in_array((int) $userId, self::BETA_USER_IDS, true);
    }

    /**
     * @param  int|null  $userId  When this is a beta user, the beta properties are
     *                            merged in. Omit it (or pass a non-beta id) to get
     *                            the stable scaffolding everyone else sees.
     */
    public static function getDefaultScaffolding($userId = null) {
        $scaffolding = self::$scaffolding;

        if (self::isBetaUser($userId)) {
            $scaffolding['properties'] = array_merge(
                $scaffolding['properties'],
                self::$betaScaffolding,
            );
        }

        return $scaffolding;
    }

    public static function getDefaultScaffoldingForScheduledOptions() {
        return [
            self::getDefaultScaffoldingForScheduleOptionsByKey("scheduled"),
            self::getDefaultScaffoldingForScheduleOptionsByKey("posted"),
            self::getDefaultScaffoldingForScheduleOptionsByKey("error"),
        ];
    }

    public static function getDefaultScaffoldingForScheduleOptionsByKey($key) {
        if ($key == "scheduled") {
            return \Notion\Databases\Properties\SelectOption::fromName("Scheduled")->changeColor(\Notion\Common\Color::Yellow);
        }
        if ($key == "posted") {
            return \Notion\Databases\Properties\SelectOption::fromName("Posted")->changeColor(\Notion\Common\Color::Green);
        }
        if ($key == "error") {
            return \Notion\Databases\Properties\SelectOption::fromName("Error")->changeColor(\Notion\Common\Color::Red);
        }
    }

    /** 
     * SECTION - Create generic scaffolding for a Database
     */
    public static function createScaffolding(
        $notion, // The Notion connector
        $scaffolding,
        $accounts,
        $page_id,
     ) {

        // Lets loop through the accounts
        $social_properties = [];
        foreach ($accounts as $account) {
            $color = NotionSocialAccounts::getColor($account->platform);
            $slug = NotionSocialAccounts::createSlug($account->platform, $account->name, $account->id);
            $social_properties[] = \Notion\Databases\Properties\SelectOption::fromName($slug)
                ->changeColor($color);
        }

        // Create template
        $database = Database::create(DatabaseParent::page($page_id))
            ->changeTitle($scaffolding['title']['name'])
            ->enableInline();

        // Build the scaff
        foreach ($scaffolding['properties'] as $key => $property) {
            if ($key == "social_accounts") {
                $database = $database->addProperty($property['notion_type']::create($property['name'], $social_properties));
                continue;
            }
            if ($key == "notion_status") {
                $database = $database->addProperty($property['notion_type']::create($property['name'],
                NotionDatabases::getDefaultScaffoldingForScheduledOptions()
            ));
                continue;
            }
            $database = $database->addProperty($property['notion_type']::create($property['name']));
        } 
            
        // Create the database
        $database = $notion->databases()->create($database);

        // Return the newly created DB object
        return $database;

    }

    // public static function updateDatabaseSocials(
    //     $notion,
    //     $database
    // ) {

    //     // Get the DB
    //     $notion_database = $notion->databases()->find($database->database_id);

    //     // Handler
    //     $option_type = self::$scaffolding['options']['type'];

    //     $correct_slug_options = [];
    //     foreach ($slug_keys as $slug_key) {
    //         $correct_slug_options[] = $option_type::fromName($slug_key)
    //             ->changeColor(NotionSocialAccounts::getColorFromSlug($slug_key));
    //     }
    //     $toChange = $toChange->changeOptions(...$correct_slug_options);
    //     $notion_database = $notion_database->changeProperty($toChange);

    //     $notion_database = $notion->databases()->update($notion_database);

    //     return true;

    // }
    
}