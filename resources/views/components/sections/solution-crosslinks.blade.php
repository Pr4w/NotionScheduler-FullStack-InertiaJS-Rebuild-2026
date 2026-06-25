{{-- Cross-links to the other platform pages + the social media hub.
     Internal-linking glue: each platform page links to all the others
     AND up to /socialmedia. The hub link is the important one — eight
     pages pointing at /socialmedia is the authority signal lifting it
     from ~position 14 toward page 1. Reads the same config the footer does. --}}

@props (['current'])

@php
    $others = collect(config('solutions'))
        ->reject(fn ($s) => $s['slug'] === $current)
        ->values();
@endphp

<x-ui.section pad="tight" width="wide">
    <x-ui.card pop tone="sink" class="p-8 sm:p-12">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <x-ui.tag tone="ink" rotate>More platforms</x-ui.tag>
                <h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Schedule everywhere else, too.</h2>
            </div>
            <p class="text-ink-soft max-w-sm">One Notion workspace, every platform. Same workflow, wherever you post.</p>
        </div>

        {{-- Hub link — deliberately full-width and distinct from the
             platform grid so it reads as "see the whole picture", and
             so the internal link to /socialmedia stands on its own. --}}

        <a
            href="/socialmedia"
            class="group border-ink bg-ink text-paper mt-9 flex items-center gap-3 rounded-2xl border-2 p-5 transition-transform hover:-translate-y-0.5"
        >
            <span class="bg-paper/10 grid h-9 w-9 shrink-0 place-items-center rounded-xl">🗓️</span>
            <span class="font-display flex-1 font-bold">Notion for social media management — the full overview</span>
            <svg class="h-4 w-4 shrink-0 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </a>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($others as $s)
                <a
                    href="/{{ $s['slug'] }}"
                    class="group border-ink bg-paper flex items-center gap-3 rounded-2xl border-2 p-4 transition-transform hover:-translate-y-0.5"
                >
                    <span class="bg-paper grid h-9 w-9 shrink-0 place-items-center rounded-xl" style="color:{{ $s['accent'] }}">
                        <x-dynamic-component :component="'icons.'.$s['slug']" class="h-5 w-5" />
                    </span>
                    <span class="font-display flex-1 font-bold">Schedule {{ $s['name'] }}</span>
                    <svg class="text-ink-soft h-4 w-4 shrink-0 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            @endforeach
        </div>
    </x-ui.card>
</x-ui.section>
