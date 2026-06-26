<?php

namespace App\Jobs;

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

        // v2: uncomment once scraping is verified.
        // SyncPostMetricsToNotion::dispatch($post);
    }
}
