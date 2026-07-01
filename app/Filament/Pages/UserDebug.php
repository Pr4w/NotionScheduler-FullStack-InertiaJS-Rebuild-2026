<?php

namespace App\Filament\Pages;

use App\Models\NotionDatabases;
use App\Models\NotionPosts;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Notion\Notion;

/**
 * Admin drill-down debugger: User → Databases → Posts → Post detail (with a
 * live Notion content fetch). Consolidates the old debugUser / debugDatabase /
 * debugPost / debugExternalPost endpoints into one tool. Not in the nav — it's
 * opened from the "Inspect" action on the Users table.
 */
class UserDebug extends Page
{
    protected string $view = 'filament.pages.user-debug';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $title = 'User debugger';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    #[Url]
    public ?int $user = null;

    public ?int $databaseId = null;

    public ?int $postId = null;

    // Live Notion fetch (populated on demand by fetchNotionContent()).
    public ?array $notion = null;

    public ?string $notionError = null;

    public bool $notionLoaded = false;

    // --- Data (computed, cached per request) ---

    #[Computed]
    public function userRecord(): ?User
    {
        return $this->user ? User::find($this->user) : null;
    }

    #[Computed]
    public function databases()
    {
        if (! $this->user) {
            return collect();
        }

        return NotionDatabases::withCount('socials')
            ->where('userid', $this->user)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function database(): ?NotionDatabases
    {
        return $this->databaseId ? NotionDatabases::find($this->databaseId) : null;
    }

    #[Computed]
    public function posts()
    {
        if (! $this->databaseId) {
            return collect();
        }

        return NotionPosts::where('database_id', $this->databaseId)
            ->latest()
            ->limit(300)
            ->get();
    }

    #[Computed]
    public function post(): ?NotionPosts
    {
        return $this->postId ? NotionPosts::find($this->postId) : null;
    }

    // --- Drill-down navigation ---

    public function selectDatabase(int $id): void
    {
        $this->databaseId = $id;
        $this->postId = null;
        $this->resetNotion();
    }

    public function selectPost(int $id): void
    {
        $this->postId = $id;
        $this->resetNotion();
    }

    public function backToDatabases(): void
    {
        $this->databaseId = null;
        $this->postId = null;
        $this->resetNotion();
    }

    public function backToPosts(): void
    {
        $this->postId = null;
        $this->resetNotion();
    }

    private function resetNotion(): void
    {
        $this->notion = null;
        $this->notionError = null;
        $this->notionLoaded = false;
    }

    /**
     * Live-fetch the selected post's Notion page content/media/thumbnail/date
     * (the old debugPost / debugExternalPost behaviour). Fully guarded.
     */
    public function fetchNotionContent(): void
    {
        $this->resetNotion();
        $this->notionLoaded = true;

        try {
            $post = $this->post;
            if (! $post) {
                $this->notionError = 'Post not found.';

                return;
            }

            $db = NotionDatabases::with('token')->find($post->database_id);
            if (! $db || ! $db->token || ! $db->token->token) {
                $this->notionError = 'The database or its Notion token is missing — the account may need reconnecting.';

                return;
            }

            if (blank($post->post_page_id)) {
                $this->notionError = 'This post has no Notion page id, so there is nothing to fetch.';

                return;
            }

            $notion = Notion::create($db->token->token);

            $contents = $notion->blocks()->findChildrenRecursive($post->post_page_id);
            $content = NotionPosts::getAllContentFromChildren($contents);

            $page = $notion->pages()->find($post->post_page_id);

            $media = $db->column_media
                ? NotionPosts::getAllMediaFromProps2($page->properties()->getById($db->column_media)->files)
                : [];

            $thumbnail = $db->column_media_thumbnail
                ? NotionPosts::getThumbnailFromProps2($page->properties()->getById($db->column_media_thumbnail)->files)
                : null;

            $date = null;
            if ($db->column_post_date) {
                $raw = $page->properties()->getById($db->column_post_date)->start();
                $date = $raw
                    ? Carbon::parse($raw)->setTimezone(date_default_timezone_get())->toDateTimeString()
                    : null;
            }

            $this->notion = [
                'content' => $content,
                'media' => $media,
                'thumbnail' => $thumbnail,
                'date' => $date,
            ];
        } catch (\Throwable $e) {
            report($e);
            $this->notionError = 'Failed to fetch from Notion: '.$e->getMessage();
        }
    }
}
