{{--
    Use-case page — /for/{slug}. Driven by config/use-cases.php.

    Route is registered BEFORE the /{platform} solution catch-all so the
    /for/ prefix resolves correctly. SEO passed as $SEOData and consumed
    by seo($SEOData) in the layout (same pattern as solution pages).
--}}

<x-layout.app :SEOData="$SEOData">
    <x-sections.use-case-hero :case="$case" />

    <x-sections.use-case-argument :case="$case" />

    <x-sections.pricing />

    <x-sections.faq eyebrow="{{ $case['name'] }} — common questions" title="Questions {{ $case['name'] }}<br>usually ask." />

    <x-sections.use-case-platforms :case="$case" />
</x-layout.app>
