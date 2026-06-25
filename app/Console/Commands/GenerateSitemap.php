<?php

namespace App\Console\Commands;

use App\Services\SiteUrls;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate sitemap.xml for static, programmatic and blog pages.';

    public function handle(SiteUrls $siteUrls): int
    {
        $this->info('Generating sitemap...');

        $sitemap = Sitemap::create();

        foreach ($siteUrls->all() as $url) {
            $sitemap->add(
                // Google ignores priority/changefreq; only lastmod is consumed.
                Url::create($url['loc'])
                    ->setLastModificationDate($url['lastmod'])
            );
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap written to public/sitemap.xml ('
            .count($siteUrls->all()).' URLs).');

        return self::SUCCESS;
    }
}
