{{--
    Solution page — /{platform}. Driven entirely by config/solutions.php.

    Route (routes/web.php):
        Route::get('/{platform}', [SolutionController::class, 'show'])
            ->where('platform', 'instagram|facebook|threads|twitter|x|linkedin|tiktok|youtube')
            ->name('solution');

    Keep the where() constraint in sync with the config keys so unknown
    slugs 404 instead of hitting the controller. SEO is passed from the
    controller as an SEOData instance ($SEOData), picked up automatically
    by ralphjsmit/laravel-seo in the layout.
--}}

<x-layout.app :SEOData="$SEOData">
    <x-sections.solution-hero :solution="$solution" />

    <x-sections.solution-benefits :solution="$solution" />

    {{-- Pricing is identical across platforms — reuse the landing component as-is --}}
    <x-sections.pricing />

    {{-- Platform-specific FAQ, then the answer is unique per page --}}
    <x-sections.faq :faqs="$solution['faq']" eyebrow="{{ $solution['name'] }} FAQs" title="{{ $solution['name'] }} questions,<br>answered." />

    {{-- Internal link back to the other platforms for crawl depth + link equity --}}
    <x-sections.solution-crosslinks :current="$solution['slug']" />
</x-layout.app>
