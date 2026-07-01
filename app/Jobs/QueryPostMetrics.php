<?php

namespace App\Jobs;

use App\Jobs\CorrectNotionDatabaseScaffolding;
use App\Models\NotionDatabases;
use App\Models\NotionErrorManager;
use App\Models\NotionHttp;
use App\Models\NotionPostLatestMetric;
use App\Models\NotionPostMetric;
use App\Models\NotionPosts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pr4w\SocialMetrics\Enums\ErrorCategory;
use Pr4w\SocialMetrics\Enums\ErrorReason;
use Pr4w\SocialMetrics\Facades\SocialMetrics;
use Pr4w\SocialMetrics\Support\PostRef;

class QueryPostMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public NotionPosts $post) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("metrics:{$this->post->id}"))->expireAfter(300)];
    }

    public function handle(): void
    {
        $post = $this->post->loadMissing('account.access_token');
        $account = $post->account;
        $tokenRow = $account?->access_token;

        if (! $account || ! $account->is_active || ! $account->is_valid || ! $tokenRow || blank($post->posted_foreign_id)) {
            Log::warning('Metrics scrape skipped: inactive/invalid account, missing token, or empty foreign id', ['post_id' => $post->id]);

            return;
        }

        $platform = $account->platform; // adjust if platform lives on the post, or is named `provider`

        $token = $platform === 'facebook'
            ? $tokenRow->access_token_page
            : $tokenRow->access_token;

        if (! $token) {
            Log::warning('Metrics scrape skipped: empty token', ['post_id' => $post->id, 'platform' => $platform]);

            return;
        }

        $result = SocialMetrics::fetchPosts([
            PostRef::make($platform, $post->posted_foreign_id, accountId: $account->id, accessToken: $token),
        ]);

        // Partial failures don't throw, they land in errors as data.
        if ($error = $result->errors->first()) {

            // Temporary (throttling, transport blip, 5xx): back off and retry on the configured schedule.
            if ($error->retryable()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? end($this->backoff));

                return;
            }

            // The post is gone (deleted or no longer accessible): stop scraping it.
            if ($error->reason === ErrorReason::NotFound) {
                $post->is_valid = 0;
                $post->save();

                Log::info('Metrics: post no longer exists, marked invalid', [
                    'post_id' => $post->id, 'platform' => $platform, 'message' => $error->message,
                ]);

                return;
            }

            // Token revoked or expired: the account needs re-auth. Flagging it here means
            // the guard above skips this account until it is reconnected. Remove this block
            // if you handle reconnects elsewhere.
            if ($error->category() === ErrorCategory::Reconnect) {
                // $account->is_valid = 0;
                // $account->save();

                Log::warning('Metrics: account needs reconnect, we could make it as invalid?', [
                    'post_id' => $post->id, 'account_id' => $account->id, 'platform' => $platform,
                ]);

                return;
            }

            // Unsupported, configuration, or an unmapped error (category unknown): log for review.
            Log::warning('Metrics fetch error', [
                'post_id' => $post->id,
                'platform' => $platform,
                'reason' => $error->reason->value,
                'category' => $error->category()->value,
                'message' => $error->message,
            ]);

            return;
        }

        $metrics = $result->postFor($platform, $post->posted_foreign_id);
        if (! $metrics) {
            Log::error('Metrics: no result and no error for post', ['post_id' => $post->id, 'platform' => $platform]);

            return;
        }

        $now = now();

        // Historical snapshot (raw carries reach + anything the platform exposed)
        NotionPostMetric::create([
            'content_id' => $post->id,
            'platform' => $platform,
            'recorded_at' => $now,
            'views' => $metrics->views,
            'likes' => $metrics->likes,
            'comments' => $metrics->comments,
            'shares' => $metrics->shares,
            'saves' => $metrics->saves,
            'raw_payload' => $metrics->raw,
        ]);

        // Latest snapshot, one row per content + platform
        NotionPostLatestMetric::updateOrCreate(
            ['content_id' => $post->id, 'platform' => $platform],
            [
                'recorded_at' => $now,
                'views' => $metrics->views,
                'likes' => $metrics->likes,
                'comments' => $metrics->comments,
                'shares' => $metrics->shares,
                'saves' => $metrics->saves,
            ],
        );

        $post->metrics_last_scraped_at = $now;
        $post->save();

        // Mirror the latest numbers into the user's Notion database (best-effort).
        $this->pushMetricsToNotion($post, $metrics);
    }

    /**
     * Push the latest metric values into the post's Notion page.
     *
     * Best-effort: the numbers are already saved locally, so a Notion failure here
     * never fails the job. Errors are routed through NotionErrorManager, which marks
     * the post/database invalid when the page or database is gone, and tells us to
     * repair the scaffolding when a managed column has drifted or is missing.
     */
    private function pushMetricsToNotion(NotionPosts $post, $metrics): void
    {
        $database = NotionDatabases::with('token')->find($post->database_id);

        // Nothing we can safely write to.
        if (! $database || ! $database->is_valid || blank($post->post_page_id)
            || ! $database->token || blank($database->token->token)) {
            return;
        }

        // BETA gate: only mirror analytics into Notion for beta users while we settle
        // on column names/behaviour. Everyone else still gets their metrics saved
        // locally above — we just don't touch their Notion databases yet.
        if (! NotionDatabases::isBetaUser($database->userid)) {
            return;
        }

        // Map each stored Notion property ID -> its latest value (null clears the cell).
        $columns = [
            'column_metric_views'    => $metrics->views,
            'column_metric_likes'    => $metrics->likes,
            'column_metric_comments' => $metrics->comments,
            'column_metric_shares'   => $metrics->shares,
            'column_metric_saves'    => $metrics->saves,
        ];

        $byId = [];
        foreach ($columns as $column => $value) {
            $propId = $database->$column;
            if (filled($propId)) {
                $byId[$propId] = $value;
            }
        }

        // Columns aren't scaffolded yet — create them, then let the next scrape write.
        if (empty($byId)) {
            CorrectNotionDatabaseScaffolding::dispatch($database);

            return;
        }

        try {
            (new NotionHttp(
                ['userid' => $database->userid, 'post_id' => $post->id, 'database_id' => $database->id],
                $database->token->token,
                $post->post_page_id,
            ))->markPostMetrics($byId);
        } catch (\Throwable $e) {
            $outcome = NotionErrorManager::manageError(
                $database->userid,
                $e,
                $database->token->token,
                'QueryPostMetrics::pushMetricsToNotion',
                $database->id,
                $post->id,
            );

            // A drifted/missing managed column: rebuild the scaffolding, then it heals next run.
            if (($outcome['action'] ?? null) === 'correct_scaffolding') {
                CorrectNotionDatabaseScaffolding::dispatch($database);
            }

            // Otherwise manageError has already flagged the post/database where relevant.
            // The metrics are saved locally regardless — the Notion mirror is best-effort.
        }
    }
}
