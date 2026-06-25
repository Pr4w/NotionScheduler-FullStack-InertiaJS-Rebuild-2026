{{--
    Landing page. Pure composition — every section is its own component, so
    you can reorder, reuse on solution pages, or A/B test individual blocks.

    SEO for this static page: set it from the route or a controller using
    ralphjsmit/laravel-seo, e.g. in routes/web.php:

        Route::get('/', function () {
            seo()
                ->title('NotionScheduler — Schedule social media posts from Notion')
                ->description('Plan, write and schedule social posts to Instagram, LinkedIn, X, TikTok and more — without ever leaving your Notion workspace. Free to start.')
                ->withUrl();

            return view('pages.home');
        })->name('home');

    The package's global config (config/seo.php) handles site name, default
    OG image, Twitter handle, etc., so this page only overrides title/description.
--}}

<x-layout.app :SEOData="$SEOData">
    <x-sections.hero />
    <x-sections.social-proof />
    <x-sections.logo-strip />
    <x-sections.how-it-works />
    <x-sections.features />
    <x-sections.pricing />
    <x-sections.faq />
</x-layout.app>
