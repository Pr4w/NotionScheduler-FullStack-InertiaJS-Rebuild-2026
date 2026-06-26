<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

use Illuminate\Support\Facades\Log;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            // return $isLocal ||
            //        $entry->isReportableException() ||
            //        $entry->isFailedRequest() ||
            //        $entry->isFailedJob() ||
            //        $entry->isScheduledTask() ||
            //        $entry->hasMonitoredTag();

            if ($entry->type === 'job') {

                // Log::debug('TELESCOPE JOB DEBUG', [
                //     'raw_type'      => $entry->type,
                //     'name'          => $entry->content['name'] ?? null,
                //     'commandName'   => $entry->content['commandName'] ?? null,
                //     'queue'         => $entry->content['queue'] ?? null,
                //     'tags'          => $entry->tags ?? null,
                //     'content_keys'  => array_keys($entry->content ?? []),
                // ]);

                $hideUnlessError = [
                    "CheckSocialTokens",
                    "FindNotionPostsInDB",
                    "CorrectNotionDatabaseScaffolding"
                ];
        
                $jobName = $entry->content['name'] ?? '';

                foreach ($hideUnlessError as $needle) {
                    if (str_contains($jobName, $needle)) {
                        // Hide the job unless it failed
                        return false;
                    }
                }

            } elseif ($entry->type === 'query') {
                return false;
            } elseif ($entry->type === 'model') {
                return false;
            } elseif ($entry->type === 'view') {
                return false;
            }

            // ---------------------------------------------------------
            // 1. Local environment: show everything
            // ---------------------------------------------------------
            if ($isLocal) {
                return true;
            }

            // FIXME - Changing this so that it's ALWAYS available, even in production
            return true;

            // ---------------------------------------------------------
            // 2. Always show errors & monitored entries (your existing logic)
            // ---------------------------------------------------------
            if (
                $entry->isReportableException() ||
                $entry->isFailedRequest() ||
                $entry->isFailedJob() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag()
            ) {
                return true;
            }


            // ---------------------------------------------------------
            // 3. Hide specific job classes unless they failed
            // ---------------------------------------------------------
        
            // ---------------------------------------------------------
            // 4. Default: hide everything else (your current behavior)
            // ---------------------------------------------------------
            return false;




        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                'eternal_ps@live.com'
            ]);
        });
    }
}
