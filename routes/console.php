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

// Deferred until their dependencies are wired:
//   - metrics:scrape       -> needs pr4w/laravel-social-metrics (Phase 1 follow-up)
//   - telescope:prune      -> Telescope is a deferred monitoring tool (Phase 7)
//   - backup:run / :clean  -> needs config/backup.php published (Phase 7)
//   - app:check-supervisor -> deployment-specific (Phase 7)
