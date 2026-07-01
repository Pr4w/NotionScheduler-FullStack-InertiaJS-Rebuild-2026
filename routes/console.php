<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| NotionScheduler engine schedule (ported from api.notionscheduler)
|--------------------------------------------------------------------------
| The posting-related commands honour the POSTING_ENABLED guard internally,
| so they are safe to schedule even on the preprod subdomain.
*/

Schedule::command('app:check-notion-tokens')->everyMinute();
Schedule::command('app:check-social-tokens')->everyMinute();
Schedule::command('app:correct-notion-database-scaffolding')->everyMinute();
Schedule::command('app:delete-old-uploads')->daily();
Schedule::command('app:perform-posts')->everyMinute();
Schedule::command('app:query-d-b-and-find-ready-posts')->everyMinute();
Schedule::command('app:remove-unused-social-tokens')->daily();
Schedule::command('app:reset-in-flight-status')->everyTenMinutes();
Schedule::command('app:set-default-scaffolding')->daily();
Schedule::command('app:trial-expired')->daily();
Schedule::command('app:send-telemetry')->daily();

// Post-publish social metrics (views/likes/etc.) — pr4w/laravel-social-metrics.
Schedule::command('metrics:scrape')->everyMinute()->withoutOverlapping();

// Account follower metrics — cadence-gated to ~once/day per account, so the
// frequency here only controls how quickly newly-due accounts get picked up.
Schedule::command('metrics:scrape-accounts')->everyTenMinutes()->withoutOverlapping();

// 3am maintenance slot: prune Telescope, then back up the DB + files.
Schedule::command('telescope:prune --hours=24')->dailyAt('03:00');
Schedule::command('backup:run')->daily()->at('03:10');
Schedule::command('backup:clean')->daily()->at('03:40');

// Weekly SEO housekeeping (Sundays): refresh the sitemap, then re-submit the
// full URL set to IndexNow. (--no-interaction skips the --all confirm prompt;
// incremental IndexNow pings already fire on blog-post publish.)
Schedule::command('sitemap:generate')->weeklyOn(0, '04:00');
Schedule::command('indexnow:submit --all --no-interaction')->weeklyOn(0, '04:10');

// Still deferred (deployment-specific):
//   - app:check-supervisor -> needs the Forge/supervisor process setup
