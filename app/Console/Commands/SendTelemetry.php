<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use Laravel\Cashier\Subscription;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendTelemetry extends Command
{

    // NOTE - Laravel stuff
    protected $signature = 'app:send-telemetry';
    protected $description = 'Send daily stats to the central dashboard';

    // NOTE - Constructor
    protected string $appName = 'NotionScheduler';
    protected string $appKey  = 'e466409f159dfb3e6420deabafe6ba5d';
    protected string $apiUrl  = 'https://markhadjhamou.com/telemetry';

    /**
     * Execute the console command.
     */
    public function handle()
    {
       $this->info('Step 1: Fetching Subscriptions from Stripe...');

        // Get the secret key from Cashier config
        $stripeSecret = config('cashier.secret') ?? env('STRIPE_SECRET');

        if (!$stripeSecret) {
            $this->error('Stripe Secret Key not found in config/cashier.php or .env');
            return Command::FAILURE;
        }

        $mrrCents = 0;
        $hasMore = true;
        $startingAfter = null;

        // Loop for pagination (Stripe returns max 100 per request)
        while ($hasMore) {
            $response = Http::withToken($stripeSecret)
                ->get('https://api.stripe.com/v1/subscriptions', [
                    'status' => 'active',
                    'limit' => 100,
                    'starting_after' => $startingAfter,
                    'expand' => ['data.items.data.price'],
                ]);

            if ($response->failed()) {
                $this->error('Stripe API Error: ' . $response->body());
                return Command::FAILURE;
            }

            $payload = $response->json();
            $subscriptions = $payload['data'];

            foreach ($subscriptions as $subscription) {
                foreach ($subscription['items']['data'] as $item) {
                    $price = $item['price'];
                    $unitAmount = $price['unit_amount'];
                    $quantity = $item['quantity'] ?? 1;
                    $interval = $price['recurring']['interval'];
                    $intervalCount = $price['recurring']['interval_count'] ?? 1;

                    // Total amount for this item
                    $itemTotal = $unitAmount * $quantity;

                    // Normalize to Monthly (ChatGPT logic)
                    $monthlyAmount = match ($interval) {
                        'month' => $itemTotal / $intervalCount,
                        'year'  => $itemTotal / (12 * $intervalCount),
                        'week'  => ($itemTotal * 52) / (12 * $intervalCount),
                        'day'   => ($itemTotal * 365) / (12 * $intervalCount),
                        default => $itemTotal,
                    };

                    $mrrCents += $monthlyAmount;
                }
            }

            // Check if there is another page
            $hasMore = $payload['has_more'];
            if ($hasMore) {
                $startingAfter = end($subscriptions)['id'];
                $this->info("Fetching next page starting after: $startingAfter");
            }
        }

        $finalMrr = (int) round($mrrCents);
        $userCount = (int) User::count();

        $this->info("Calculated Stats -> Users: $userCount | MRR: " . number_format($finalMrr / 100, 2) . "€");

        // Step 2: Send to Central Hub
        $this->sendToHub($userCount, $finalMrr);
    }

    protected function sendToHub(int $users, int $mrr)
    {

        if (!$this->appKey || !$this->apiUrl) {
            $this->error('Central Hub config (url or token) is missing in config/services.php');
            return;
        }

        $response = Http::withHeaders([
            'X-App-Token' => $this->appKey,
            'Accept' => 'application/json',
        ])->post(rtrim($this->apiUrl), [
            'total_users' => $users,
            'mrr_cents'   => $mrr,
        ]);

        if ($response->successful()) {
            $this->info('SUCCESS: Telemetry sent to apps.markhadjhamou.com');
        } else {
            $this->error('HUB ERROR: ' . $response->body());
        }
    }
}
