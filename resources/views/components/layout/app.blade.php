{{--
    Base layout — every page (landing, solution pages, blog) extends this.

    SEO is fully owned by ralphjsmit/laravel-seo. Pass an Eloquent model
    that uses the HasSEO trait (blog posts, solution pages) via $seoModel;
    otherwise the global seo() defaults / per-page seo()->... overrides apply.

    For static pages you can still set SEO from the route/controller, e.g.:
        seo()->title('Schedule Facebook posts with Notion')
             ->description('...')
             ->withUrl();
--}}

@props (['SEOData' => null])

<!DOCTYPE html>
<html lang="en" class="scroll-pt-24">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />

    {!! seo($SEOData) !!}

    {{-- Per-page extras (extra JSON-LD, preload hints, etc.) --}}
    {{ $head ?? '' }}

    <link rel="icon" type="image/png" href="/favicon.png" />

    {{-- Fonts: distinctive display + clean body. Self-hosting later is ideal for perf. --}}
    <!-- <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap"
        rel="stylesheet"
    /> -->

    @vite('resources/css/landing.css')
</head>
<body class="min-h-screen antialiased">
    <a
        href="#main"
        class="focus:bg-ink focus:text-paper sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:px-4 focus:py-2"
    >
        Skip to content
    </a>

    <x-layout.header />

    <main id="main">{{ $slot }}</main>

    <x-layout.footer />

    {{-- Progressive enhancement: mobile nav + FAQ toggles. No build step needed. --}}
    <script>
        document.addEventListener('click', (e) => {
            const navBtn = e.target.closest('[data-nav-toggle]');
            if (navBtn) {
                const menu = document.querySelector('[data-nav-menu]');
                const willOpen = menu?.classList.contains('hidden');
                menu?.classList.toggle('hidden');
                const btn = document.querySelector('button[data-nav-toggle]');
                if (btn) {
                    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    btn.setAttribute('aria-label', willOpen ? 'Close menu' : 'Open menu');
                }
            }
            const faq = e.target.closest('[data-faq-trigger]');
            if (faq) {
                const item = faq.closest('[data-faq-item]');
                const open = item.getAttribute('data-open') === 'true';
                item.setAttribute('data-open', open ? 'false' : 'true');
                faq.setAttribute('aria-expanded', open ? 'false' : 'true');
            }
        });
    </script>
</body>
</html>
