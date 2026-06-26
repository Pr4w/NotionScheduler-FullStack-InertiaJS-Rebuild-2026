{{--
    Terms of Service — /terms. Content ported 1:1 from the legacy app
    (notionscheduler-Vue TermsOfService.vue); rendered inside the landing chrome.
    The legacy content is Word-exported markup with its own inline styling, so it
    keeps that styling inside the prose container. SEO comes from routes/landing.php.
--}}

<x-layout.app :SEOData="$SEOData">
    <section class="mx-auto max-w-3xl px-6 py-16 sm:py-24">
        <div class="prose prose-neutral prose-headings:font-display max-w-none">
            @include('pages.legal.terms-content')
        </div>
    </section>
</x-layout.app>
