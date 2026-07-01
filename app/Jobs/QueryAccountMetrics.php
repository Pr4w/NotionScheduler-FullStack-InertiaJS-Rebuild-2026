<?php

namespace App\Jobs;

use App\Models\NotionSocialAccounts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pr4w\SocialMetrics\Facades\SocialMetrics;
use Pr4w\SocialMetrics\Support\AccountRef;

/**
 * Refreshes a single account's follower metrics via Pr4w\SocialMetrics.
 *
 * Dispatched by the metrics:scrape-accounts command, which cadence-gates on
 * `metrics_last_scraped_at` so each account is only scraped ~once a day. The
 * whole thing is best-effort: any failure is swallowed, and the account is
 * always stamped as scanned so a failing/ineligible account isn't retried until
 * the next daily window (no API hammering).
 */
class QueryAccountMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public NotionSocialAccounts $account) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("account-metrics:{$this->account->id}"))->expireAfter(300)];
    }

    public function handle(): void
    {
        $account = $this->account->fresh();

        if (! $account) {
            return;
        }

        try {
            $this->fetchFollowers($account);
        } catch (\Throwable $e) {
            Log::info('QueryAccountMetrics - skipped (non-fatal)', [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'error' => $e->getMessage(),
            ]);
        }

        // Mark scanned regardless of the outcome above, so the daily cadence
        // holds even for accounts that error or are skipped.
        $account->metrics_last_scraped_at = now();
        $account->save();
    }

    /**
     * Fetch + store follower counts on the account (without saving — the caller
     * persists alongside the metrics_last_scraped_at stamp). Returns early for
     * anything ineligible; never throws for partial API failures.
     */
    private function fetchFollowers(NotionSocialAccounts $account): void
    {
        if (! $account->is_valid) {
            return;
        }

        // Only platforms the package has a driver for. Twitter/X has none.
        $supported = ['facebook', 'instagram', 'threads', 'linkedin', 'tiktok', 'youtube'];
        if (! in_array($account->platform, $supported, true)) {
            return;
        }

        // Facebook reads page-level stats via the page token; the rest use the
        // account token. YouTube is key-based (needs no token here).
        $tokenRow = $account->access_token;
        $token = $account->platform === 'facebook'
            ? ($tokenRow->access_token_page ?? $tokenRow->access_token ?? null)
            : ($tokenRow->access_token ?? null);

        if ($account->platform !== 'youtube' && ! $token) {
            return;
        }

        // LinkedIn: account-follower stats need scopes that were only added to
        // tokens issued from 1 Jul 2026. Older tokens lack them and would just
        // 403, so skip the refresh until the account is reconnected.
        if ($account->platform === 'linkedin'
            && (! $tokenRow?->created_at
                || $tokenRow->created_at->lt(Carbon::create(2026, 7, 1)))) {
            return;
        }

        // The package reads the identifier straight from accountId. LinkedIn
        // detects person-vs-org from the URN, so hand it the full URN
        // (account_full_identifier); every other platform wants its native id.
        $accountId = (string) ($account->platform === 'linkedin'
            ? $account->account_full_identifier
            : ($account->account_id ?? $account->id));

        $ref = AccountRef::make($account->platform, $accountId, $token);

        // fetchAccounts never throws for partial failures — errors land in the
        // result. We only act on a real number coming back.
        $metrics = SocialMetrics::fetchAccounts([$ref])->accounts->first();

        if ($metrics && $metrics->followers !== null) {
            $account->followers = $metrics->followers;
            if ($metrics->following !== null) {
                $account->followings = $metrics->following;
            }
            if ($metrics->posts !== null) {
                $account->post_count = $metrics->posts;
            }
        }
    }
}
