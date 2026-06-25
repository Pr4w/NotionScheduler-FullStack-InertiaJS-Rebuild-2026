<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/IndexNow';

    public function submit(array $urls): bool
    {
        $urls = array_values(array_unique($urls));

        if (empty($urls)) {
            return false;
        }

        $key = config('services.indexnow.key');
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        if (! $key || ! $host) {
            Log::warning('IndexNow : clé ou host manquant, soumission annulée.');

            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(self::ENDPOINT, [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => rtrim(config('app.url'), '/')."/{$key}.txt",
                    'urlList' => $urls,
                ]);

            if ($response->successful()) {
                Log::info('IndexNow : soumission réussie', [
                    'count' => count($urls),
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('IndexNow : soumission échouée', [
                'status' => $response->status(),
                'body' => $response->body(),
                'urls' => $urls,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('IndexNow : exception lors de la soumission', [
                'message' => $e->getMessage(),
                'urls' => $urls,
            ]);

            return false;
        }
    }
}
