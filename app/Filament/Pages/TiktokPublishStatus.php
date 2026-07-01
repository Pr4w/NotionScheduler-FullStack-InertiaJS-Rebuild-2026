<?php

namespace App\Filament\Pages;

use App\Models\NotionPosts;
use App\Models\NotionSocialAccounts;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use UnitEnum;

/**
 * Admin debug tool: given a TikTok publish_id (stored on a post as
 * posted_foreign_id), look up the owning account and query TikTok's
 * post/publish/status/fetch endpoint to see where a stuck publish is at.
 * Every failure mode is surfaced as a friendly message rather than a 500.
 */
class TiktokPublishStatus extends Page
{
    protected string $view = 'filament.pages.tiktok-publish-status';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static UnitEnum|string|null $navigationGroup = 'Backend';

    protected static ?string $title = 'TikTok publish status';

    protected static ?string $navigationLabel = 'TikTok status';

    public string $publishId = '';

    // --- Result state (rendered by the view) ---
    public bool $checked = false;

    public ?string $error = null;

    public ?array $context = null;

    public ?int $httpStatus = null;

    public ?array $response = null;

    public function check(): void
    {
        $this->reset(['error', 'context', 'httpStatus', 'response']);
        $this->checked = true;

        $publishId = trim($this->publishId);

        if ($publishId === '') {
            $this->error = 'Please enter a publish_id.';

            return;
        }

        try {
            $post = NotionPosts::where('posted_foreign_id', $publishId)
                ->latest()
                ->first();

            if (! $post) {
                $this->error = "No post found with posted_foreign_id = {$publishId}.";

                return;
            }

            $account = NotionSocialAccounts::with('access_token')->find($post->account_id);

            if (! $account) {
                $this->error = "Post #{$post->id} was found, but its account (#{$post->account_id}) no longer exists.";

                return;
            }

            if ($account->platform !== 'tiktok') {
                $this->error = "Post #{$post->id} belongs to a {$account->platform} account, not TikTok — this checker only talks to TikTok's API.";

                return;
            }

            $token = $account->access_token?->access_token;

            if (! $token) {
                $this->error = "Account #{$account->id} ({$account->name}) has no stored access token — it may need reconnecting.";

                return;
            }

            $this->context = [
                'Post' => '#'.$post->id.' — '.($post->post_name ?? 'Untitled'),
                'Account' => $account->name.' (#'.$account->id.')',
                'Local status' => $post->status ?? '—',
                'Posted date' => (string) ($post->posted_date ?? '—'),
            ];

            $resp = Http::tiktok()
                ->withToken($token)
                ->post('post/publish/status/fetch/', ['publish_id' => $publishId]);

            $this->httpStatus = $resp->status();
            $this->response = $resp->json() ?: ['raw_body' => $resp->body()];
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'The request to TikTok failed: '.$e->getMessage();
        }
    }
}
