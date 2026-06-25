<?php

namespace App\Services;

use App\Models\BlogPost;
use Carbon\Carbon;
use Spatie\Sitemap\Tags\Url;

class SiteUrls
{
    /**
     * Last-modified date for static / programmatic pages. Derived from the
     * deploy time: with Forge zero-downtime releases, each deploy is a fresh
     * directory, so a core file's mtime reflects when this release shipped.
     * No deploy-script changes or manual bumping needed.
     */
    private function staticLastmod(): Carbon
    {
        return Carbon::createFromTimestamp(
            filemtime(base_path('composer.json'))
        );
    }

    /**
     * Solution slugs treated as high-priority (top search volume).
     * Keep these as config keys from config/solutions.php.
     */
    private const HIGH_PRIORITY_SOLUTIONS = [
        'instagram',
        'linkedin',
        'tiktok',
    ];

    /**
     * All site URLs with sitemap metadata.
     */
    public function all(): array
    {
        $staticLastmod = $this->staticLastmod();

        $lastBlogPostDate = BlogPost::published()->max('updated_at');
        $lastBlogPostDate = $lastBlogPostDate
            ? Carbon::parse($lastBlogPostDate)
            : $staticLastmod;

        $urls = [];

        // Homepage
        $urls[] = [
            'loc' => url('/'),
            'priority' => 1.0,
            'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
            'lastmod' => $staticLastmod,
        ];

        // Generic social media page
        $urls[] = [
            'loc' => url('/socialmedia'),
            'priority' => 0.9,
            'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
            'lastmod' => $staticLastmod,
        ];

        // Solution pages — /{platform}, from config/solutions.php
        foreach (config('solutions', []) as $key => $page) {
            $urls[] = [
                'loc' => url('/'.$page['slug']),
                'priority' => in_array($key, self::HIGH_PRIORITY_SOLUTIONS) ? 0.9 : 0.7,
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'lastmod' => $staticLastmod,
            ];
        }

        // Use-case pages — /for/{slug}, from config/use-cases.php
        foreach (config('use-cases', []) as $page) {
            $urls[] = [
                'loc' => url('/for/'.$page['slug']),
                'priority' => 0.7,
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'lastmod' => $staticLastmod,
            ];
        }

        // Blog index — moves whenever a new post goes live
        $urls[] = [
            'loc' => route('blog.index'),
            'priority' => 0.8,
            'frequency' => Url::CHANGE_FREQUENCY_WEEKLY,
            'lastmod' => $lastBlogPostDate,
        ];

        // Blog posts — published only (respects the model scope)
        foreach (BlogPost::published()->get() as $post) {
            $urls[] = [
                'loc' => route('blog.show', $post->slug),
                'priority' => 0.7,
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'lastmod' => Carbon::parse($post->updated_at),
            ];
        }

        return $urls;
    }

    /**
     * Absolute URLs impacted by publishing/updating a blog post (IndexNow).
     */
    public function urlsAffectedByBlogPost(BlogPost $post): array
    {
        return [
            route('blog.index'),
            route('blog.show', $post->slug),
        ];
    }

    /**
     * Every absolute URL on the site (initial IndexNow run).
     */
    public function allAbsoluteUrls(): array
    {
        return array_map(fn (array $url) => $url['loc'], $this->all());
    }
}
