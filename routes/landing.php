<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\SocialMediaHubController;
use App\Http\Controllers\SolutionController;
use App\Http\Controllers\UseCaseController;
use App\Services\SiteUrls;
use Illuminate\Support\Facades\Route;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/*
|--------------------------------------------------------------------------
| Marketing landing (Blade) — ported 1:1 from home2026
|--------------------------------------------------------------------------
| Public, owns the root domain. The /{platform} and /for/{useCase} catch-alls
| are constrained to known config slugs, so they can't shadow /app, /login,
| /admin, /webhooks, /blog or /socialmedia. Registered last for hygiene.
*/

Route::get('/', function () {
    return view('pages.home', [
        'SEOData' => new SEOData(
            title: 'NotionScheduler — Schedule social media posts from Notion',
            description: 'Plan, write and schedule social posts to Instagram, LinkedIn, X, TikTok and more — without ever leaving your Notion workspace. Free to start.',
            schema: SchemaCollection::make()
                ->add(fn (SEOData $SEOData) => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'NotionScheduler',
                    'url' => url('/'),
                    'logo' => url('/favicon.png'),
                    'description' => 'NotionScheduler lets you plan, write and schedule social media posts directly from your Notion workspace.',
                    'sameAs' => [
                        'https://www.linkedin.com/company/notion-scheduler',
                    ],
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'contactType' => 'customer support',
                        'email' => 'contact@notionscheduler.app',
                        'availableLanguage' => ['English'],
                    ],
                ])
                ->add(fn (SEOData $SEOData) => [
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => 'NotionScheduler',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => 'Schedule social media posts to Instagram, LinkedIn, X, TikTok, Facebook, Threads and YouTube directly from Notion.',
                    'url' => url('/'),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'EUR',
                        'description' => 'Free plan available; paid plans for heavier usage.',
                    ],
                    'featureList' => [
                        'Schedule posts from a Notion database',
                        'Supports Instagram, Facebook, Threads, X, LinkedIn, TikTok, YouTube',
                        'Calendar-view content planning inside Notion',
                        'Team collaboration with existing Notion access',
                    ],
                ])
                ->add(fn (SEOData $SEOData) => [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => collect(config('faq'))->map(fn ($qa) => [
                        '@type' => 'Question',
                        'name' => $qa[0],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
                    ])->all(),
                ]),
        ),
    ]);
})->name('home');

Route::get('/urls.txt', function (SiteUrls $siteUrls) {
    $body = cache()->remember('urls-txt', now()->addHour(), fn () => implode("\n", $siteUrls->allAbsoluteUrls()));

    return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

Route::get('/llms.txt', [LlmsTxtController::class, 'show']);

Route::get('/socialmedia', [SocialMediaHubController::class, 'show'])->name('social-media-hub');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blogPost}', [BlogController::class, 'show'])->name('blog.show');

// Catch-alls last, constrained to known config slugs.
Route::get('/{platform}', [SolutionController::class, 'show'])
    ->where('platform', implode('|', array_keys(config('solutions'))))
    ->name('solution');

Route::get('/for/{useCase}', [UseCaseController::class, 'show'])
    ->where('useCase', implode('|', array_keys(config('use-cases'))))
    ->name('use-case');
