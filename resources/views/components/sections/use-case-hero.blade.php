{{-- Use-case hero. Same visual language as solution-hero; copy from config. --}}

@props (['case'])

<x-ui.section pad="default" width="wide" class="relative overflow-hidden">
    <div class="bg-flare-100 pointer-events-none absolute -top-32 -right-40 -z-10 h-96 w-96 rounded-full blur-3xl" aria-hidden="true"></div>

    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-8">
        <div class="max-w-xl">
            <x-ui.tag tone="flare" rotate>{{ $case['eyebrow'] }}</x-ui.tag>

            <h1 class="mt-6 text-5xl font-extrabold sm:text-6xl xl:text-7xl">
                {{ $case['headline'] }}
                <span class="text-flare-500">{{ $case['headline_em'] }}</span>
            </h1>

            <p class="text-ink-soft mt-6 text-lg sm:text-xl">{{ $case['subhead'] }}</p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.button href="/app/register" size="lg" icon> Start free — no card </x-ui.button>
                <a href="#argument" class="text-ink-soft hover:text-ink inline-flex items-center gap-2 px-4 py-3 font-semibold transition-colors">
                    Why this works
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 3v10M4 9l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </div>

        <div class="relative">
            <x-ui.card pop tone="ink" class="p-8 sm:p-10">
                <p class="text-paper/50 font-mono text-xs tracking-widest uppercase">In one line</p>
                <p class="font-display mt-4 text-2xl leading-snug font-bold">{{ $case['pull'] }}</p>
            </x-ui.card>
        </div>
    </div>
</x-ui.section>
