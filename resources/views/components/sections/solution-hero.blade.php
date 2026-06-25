{{-- Solution hero. Same visual language as the landing hero, but every
     string comes from config/solutions.php so each page reads uniquely. --}}

@props (['solution'])

<x-ui.section pad="default" width="wide" class="relative overflow-hidden">
    <div class="bg-flare-100 pointer-events-none absolute -top-32 -right-40 -z-10 h-96 w-96 rounded-full blur-3xl" aria-hidden="true"></div>

    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-8">
        <div class="max-w-xl">
            <div class="reveal reveal-1">
                <x-ui.tag tone="flare" rotate>{{ $solution['eyebrow'] }}</x-ui.tag>
            </div>

            <h1 class="reveal reveal-2 mt-6 text-5xl font-extrabold sm:text-6xl xl:text-7xl">
                {{ $solution['headline'] }}
                <span class="relative inline-block">
                    <span class="relative z-10">{{ $solution['headline_em'] }}</span>
                </span>
            </h1>

            <p class="reveal reveal-3 text-ink-soft mt-6 text-lg sm:text-xl">{{ $solution['subhead'] }}</p>

            <div class="reveal reveal-4 mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.button href="/app/register" size="lg" icon> Start free — no card </x-ui.button>
                <a href="#how" class="text-ink-soft hover:text-ink inline-flex items-center gap-2 px-4 py-3 font-semibold transition-colors">
                    See how it works
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 3v10M4 9l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </div>

        <div class="reveal reveal-3 relative">
            <x-ui.card pop tone="paper" class="p-5 sm:p-7">
                <div class="border-line flex items-center gap-3 border-b pb-4">
                    <span class="bg-paper-sink grid h-8 w-8 place-items-center rounded-lg text-sm">📝</span>
                    <div class="flex-1">
                        <div class="bg-ink/80 h-2.5 w-32 rounded-full"></div>
                        <div class="bg-line mt-2 h-2 w-48 rounded-full"></div>
                    </div>
                    <x-ui.tag tone="mint" class="!text-[10px]">Scheduled</x-ui.tag>
                </div>

                <div class="text-ink-soft my-5 flex items-center justify-center gap-2">
                    <span class="dotted-rule h-px w-12"></span>
                    <span class="font-mono text-xs tracking-widest uppercase">posts to</span>
                    <span class="dotted-rule h-px w-12"></span>
                </div>

                <div class="border-line bg-paper flex flex-col items-center gap-3 rounded-xl border p-8">
                    <span class="bg-paper grid h-16 w-16 place-items-center rounded-2xl" style="color:{{ $solution['accent'] }}">
                        <x-dynamic-component :component="'icons.'.$solution['slug']" class="h-8 w-8" />
                    </span>
                    <span class="font-display text-2xl font-bold">{{ $solution['name'] }}</span>
                    <span class="text-ink-soft font-mono text-xs">on your schedule</span>
                </div>
            </x-ui.card>

            <div class="absolute -bottom-5 -left-5 hidden sm:block">
                <x-ui.tag tone="ink" rotate class="card-pop !text-xs"> ⚡ Straight from Notion </x-ui.tag>
            </div>
        </div>
    </div>
</x-ui.section>
