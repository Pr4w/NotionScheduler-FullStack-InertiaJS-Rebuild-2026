{{--
    Privacy Policy — /privacy. Content ported 1:1 from the legacy app
    (notionscheduler-Vue Privacy.vue); rendered inside the landing chrome.
    SEO is passed from routes/landing.php as an SEOData instance.
--}}

<x-layout.app :SEOData="$SEOData">
    <section class="mx-auto max-w-3xl px-6 py-16 sm:py-24">
        <div class="prose prose-neutral prose-headings:font-display max-w-none">
            @include('pages.legal.privacy-content')
        </div>
    </section>
</x-layout.app>
