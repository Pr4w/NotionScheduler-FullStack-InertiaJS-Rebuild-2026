<?php

namespace App\Providers;

use App\Support\Cloudinary\CloudinaryManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use SocialiteProviders\Instagram\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Drop-in replacement for the old cloudinary-labs facade (no L13 release).
        $this->app->singleton(CloudinaryManager::class, fn () => new CloudinaryManager);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSocialiteProviders();
        $this->registerHttpMacros();
        $this->registerResponseMacros();

        // Automatic collection of VAT on payments.
        Cashier::calculateTaxes();
    }

    /**
     * Configure default behaviors for production-ready applications.
     *
     * NOTE: CarbonImmutable is intentionally NOT enabled — the ported posting
     * engine was written against mutable Carbon and relies on in-place mutation.
     */
    protected function configureDefaults(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the custom Socialite drivers for every connected platform.
     */
    protected function registerSocialiteProviders(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('instagram', Provider::class);
            $event->extendSocialite('facebook', \SocialiteProviders\Facebook\Provider::class);
            $event->extendSocialite('notion', \SocialiteProviders\Notion\Provider::class);
            $event->extendSocialite('twitter', \SocialiteProviders\Twitter\Provider::class);
            $event->extendSocialite('linkedin', \SocialiteProviders\LinkedIn\Provider::class);
            $event->extendSocialite('tiktok', \SocialiteProviders\TikTok\Provider::class);
            $event->extendSocialite('threads', \SocialiteProviders\Threads\Provider::class);
            $event->extendSocialite('youtube', \SocialiteProviders\YouTube\Provider::class);
        });
    }

    /**
     * Base-URL'd HTTP clients used throughout the posting engine.
     */
    protected function registerHttpMacros(): void
    {
        Http::macro('facebook', fn () => Http::baseUrl('https://graph.facebook.com/v25.0/'));
        Http::macro('twitter', fn () => Http::baseUrl('https://api.twitter.com/2/'));
        Http::macro('twitterX', fn () => Http::baseUrl('https://api.x.com/2/'));
        Http::macro('linkedin', fn () => Http::baseUrl('https://api.linkedin.com/v2/'));
        Http::macro('tiktok', fn () => Http::baseUrl('https://open.tiktokapis.com/v2/'));
        Http::macro('threads', fn () => Http::baseUrl('https://graph.threads.net/'));

        Http::macro('notion', fn () => Http::baseUrl('https://api.notion.com/v1/')
            ->withHeaders(['Notion-Version' => '2026-03-11']));

        Http::macro('linkedinwithheaders', function (?string $accessToken = null) {
            $request = Http::baseUrl('https://api.linkedin.com/rest/')
                ->withHeaders([
                    'LinkedIn-Version' => '202604',
                    'X-RestLi-Protocol-Version' => '2.0.0',
                ]);

            return $accessToken ? $request->withToken($accessToken) : $request;
        });
    }

    /**
     * Standard JSON envelope helpers shared by the API-style controllers.
     */
    protected function registerResponseMacros(): void
    {
        Response::macro('default', fn ($status = 'OK', $data = [], $messages = []) => Response::make([
            'status' => $status,
            'data' => $data,
            'messages' => $messages,
        ]));

        Response::macro('failWithMessage', fn ($type = 'warning', $message = 'Unspecified error', $data = []) => Response::default('FAIL', $data, [
            ['type' => $type, 'message' => $message],
        ]));

        Response::macro('successWithMessage', fn ($message = 'Unspecified error', $data = []) => Response::default('OK', $data, [
            ['type' => 'success', 'message' => $message],
        ]));
    }
}
