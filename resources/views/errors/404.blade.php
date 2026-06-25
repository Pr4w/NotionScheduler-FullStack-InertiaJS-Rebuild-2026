@php
    $SEOData = new \RalphJSmit\Laravel\SEO\Support\SEOData(
        title: 'Page not found — NotionScheduler',
        description: 'That page doesn\'t exist. Let\'s get you back to scheduling.',
    );
@endphp

<x-layout.app :SEOData="$SEOData">
    <x-ui.section pad="default" width="default">
        <div class="mx-auto max-w-2xl text-center">
            <x-ui.tag tone="flare" rotate>404</x-ui.tag>

            <h1 class="mt-6 text-6xl font-extrabold sm:text-7xl">This page never got scheduled.</h1>

            <p class="text-ink-soft mt-6 text-lg">Either it moved, or it never existed. Both are fixable — unlike that post you forgot to publish last week.</p>

            <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <x-ui.button href="/" size="lg" icon> Back to home </x-ui.button>
                <a
                    href="/app/register"
                    class="text-ink-soft hover:text-ink inline-flex items-center gap-2 px-4 py-3 font-semibold transition-colors"
                >
                    Open the app
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>

            <div class="mt-14">
                <p class="text-ink-soft font-mono text-xs tracking-widest uppercase">Popular instead</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach (collect(config('solutions'))->take(5) as $s)
                        <a
                            href="/{{ $s['slug'] }}"
                            class="border-ink bg-paper rounded-full border-2 px-4 py-2 text-sm font-semibold transition-transform hover:-translate-y-0.5"
                        >
                            Schedule {{ $s['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </x-ui.section>
</x-layout.app>
