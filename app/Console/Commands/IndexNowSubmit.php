<?php

namespace App\Console\Commands;

use App\Services\IndexNow;
use App\Services\SiteUrls;
use Illuminate\Console\Command;

class IndexNowSubmit extends Command
{
    protected $signature = 'indexnow:submit
                            {--all : Submit every URL on the site (use once, at initial setup)}
                            {--url=* : Submit one or more specific URLs}';

    protected $description = 'Submit URLs to IndexNow.';

    public function handle(SiteUrls $siteUrls, IndexNow $indexNow): int
    {
        if ($this->option('all')) {
            if (! $this->option('no-interaction')
                && ! $this->confirm('Submit every URL on the site to IndexNow?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }

            $urls = $siteUrls->allAbsoluteUrls();
            $this->info('Submitting '.count($urls).' URLs...');
            $ok = $indexNow->submit($urls);
            $this->line($ok ? 'Submitted.' : 'Failed — check logs.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $urls = $this->option('url');

        if (empty($urls)) {
            $this->error('Specify --all or --url=https://...');

            return self::FAILURE;
        }

        $ok = $indexNow->submit($urls);
        $this->line($ok ? 'Submitted.' : 'Failed — check logs.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
