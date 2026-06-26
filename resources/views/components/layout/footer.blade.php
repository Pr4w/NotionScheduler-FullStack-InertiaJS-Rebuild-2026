{{--
    Footer. This is SEO real-estate: every platform "solution" page and the
    blog is linked here so they get crawled and pass internal link equity.

    Later: replace the hardcoded arrays with a config()/route() lookup so
    adding a solution page automatically surfaces it here.
--}}

@php
    $platforms = collect([['/socialmedia', 'All social media']])
        ->concat(
            collect(config('solutions'))
                ->map(fn ($s) => ['/'.$s['slug'], $s['name']])
        )
        ->values();

    $useCases = collect(config('use-cases'))
        ->map(fn ($c) => ['/for/'.$c['slug'], $c['name']])
        ->values();

    $resources = [
        ['/blog',                          'Blog'],
        ['/blog/notion-content-calendar',  'Notion content calendar guide'],
        ['/app/register','Open the app'],
    ];

    $company = [
        [route('privacy'), 'Privacy policy'],
        [route('terms'),   'Terms of service'],
    ];
@endphp

<footer class="border-ink bg-paper-sink border-t-2">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8">
        {{-- Closing CTA banner --}}
        <x-ui.card pop tone="ink" class="mb-16 overflow-hidden">
            <div class="flex flex-col items-start gap-6 p-8 sm:flex-row sm:items-center sm:justify-between sm:p-12">
                <div class="max-w-xl">
                    <h2 class="text-3xl font-extrabold sm:text-4xl">Your content calendar already lives in Notion.</h2>
                    <p class="text-paper/70 mt-3">Stop copy-pasting into five different apps. Schedule it where you write it.</p>
                </div>
                <x-ui.button href="/app/register" variant="primary" size="lg" icon> Start free </x-ui.button>
            </div>
        </x-ui.card>

        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-1">
                <a href="/" class="font-display flex items-center gap-2 text-lg font-extrabold">
                    <span class="border-ink bg-flare-500 text-paper grid h-8 w-8 place-items-center rounded-lg border-2">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 2h7l3 3v9H3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                        </svg>
                    </span>
                    NotionScheduler
                </a>
                <p class="text-ink-soft mt-4 max-w-xs text-sm">Plan, write and schedule social posts without ever leaving Notion.</p>
            </div>

            <x-layout.footer-column title="Schedule with Notion">
                @foreach ($platforms as [$href, $name])
                    <li>
                        <a href="{{ $href }}" class="text-ink-soft hover:text-flare-600 text-sm transition-colors"> Schedule {{ $name }} posts </a>
                    </li>
                @endforeach
            </x-layout.footer-column>

            <x-layout.footer-column title="Use cases">
                @foreach ($useCases as [$href, $name])
                    <li>
                        <a href="{{ $href }}" class="text-ink-soft hover:text-flare-600 text-sm transition-colors">{{ $name }}</a>
                    </li>
                @endforeach
            </x-layout.footer-column>

            <x-layout.footer-column title="Resources">
                @foreach ($resources as [$href, $name])
                    <li>
                        <a href="{{ $href }}" class="text-ink-soft hover:text-flare-600 text-sm transition-colors">{{ $name }}</a>
                    </li>
                @endforeach
            </x-layout.footer-column>

            <x-layout.footer-column title="Company">
                @foreach ($company as [$href, $name])
                    <li>
                        <a href="{{ $href }}" class="text-ink-soft hover:text-flare-600 text-sm transition-colors">{{ $name }}</a>
                    </li>
                @endforeach
            </x-layout.footer-column>
        </div>

        <div class="border-line text-ink-soft mt-14 flex flex-col items-center justify-between gap-4 border-t pt-8 text-sm sm:flex-row">
            <p>&copy; {{ date('Y') }} NotionScheduler. All rights reserved.</p>
            <p class="font-mono text-xs">Made for people who live in Notion.</p>
        </div>
    </div>
</footer>
