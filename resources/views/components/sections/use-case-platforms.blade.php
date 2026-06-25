{{-- Cross-links from a use-case page into the most relevant solution
     pages. Closes the internal-linking loop: audience-intent pages feed
     tool-intent pages. Reads platform slugs from config/use-cases.php
     and pulls display data from config/solutions.php. --}}

@props (['case'])

@php
    $solutions = collect($case['platforms'])
        ->map(fn ($slug) => config("solutions.{$slug}"))
        ->filter()
        ->values();
@endphp

<x-ui.section pad="tight" width="wide">
    <x-ui.card pop tone="sink" class="p-8 sm:p-12">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <x-ui.tag tone="ink" rotate>Most relevant for you</x-ui.tag>
                <h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Where {{ $case['name'] }} usually post.</h2>
            </div>
            <p class="text-ink-soft max-w-sm">Same Notion workflow, tailored to each platform.</p>
        </div>

        <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($solutions as $s)
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
