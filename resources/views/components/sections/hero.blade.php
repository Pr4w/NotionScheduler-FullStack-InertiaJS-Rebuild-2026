{{-- Hero. Big editorial headline, one clear CTA, a "Notion → everywhere" visual. --}}

<x-ui.section pad="default" width="wide" class="relative overflow-hidden">
    {{-- Decorative blob, behind everything --}}
    <div class="bg-flare-100 pointer-events-none absolute -top-32 -right-40 -z-10 h-96 w-96 rounded-full blur-3xl" aria-hidden="true"></div>

    <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-8">
        {{-- Left: copy --}}
        <div class="max-w-xl">
            <div class="reveal reveal-1">
                <x-ui.tag tone="flare" rotate>📅 Notion-native scheduling</x-ui.tag>
            </div>

            <h1 class="reveal reveal-2 mt-6 text-5xl font-extrabold sm:text-6xl xl:text-7xl">
                The social media scheduler that runs on Notion<
            </h1>

            <p class="reveal reveal-3 text-ink-soft mt-6 text-lg sm:text-xl">Write your captions, drop your images, pick a date — all in the Notion workspace you already obsess over. We'll post it to every platform for you.</p>

            <div class="reveal reveal-4 mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.button href="/app/register" size="lg" icon> Start free — no card </x-ui.button>
                <a href="#how" class="text-ink-soft hover:text-ink inline-flex items-center gap-2 px-4 py-3 font-semibold transition-colors">
                    See how it works
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 3v10M4 9l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>

            <p class="reveal reveal-4 text-ink-soft mt-5 font-mono text-xs">Free forever for up to 10 posts/month · No Notion plugin to install</p>
        </div>

        {{-- Right: visual — a stylised "one Notion row → many platforms" diagram --}}
        <div class="reveal reveal-3 relative">
            <x-ui.card pop tone="paper" class="p-5 sm:p-7">
                {{-- Fake Notion row --}}
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
                    <span class="font-mono text-xs tracking-widest uppercase">auto-posts to</span>
                    <span class="dotted-rule h-px w-12"></span>
                </div>

                {{-- Platform chips --}}
                <div class="grid grid-cols-4 gap-3">
                    @foreach ([
        ['instagram', 'Instagram', '#E1306C'],
        ['linkedin', 'LinkedIn', '#0A66C2'],
        ['x', 'X', '#000000'],
        ['tiktok', 'TikTok', '#000000'],
        ['facebook', 'Facebook', '#1877F2'],
        ['threads', 'Threads', '#000000'],
        ['youtube', 'YouTube', '#FF0000'],
    ] as [$icon, $name, $color])
                        <div class="border-line bg-paper flex flex-col items-center gap-1.5 rounded-xl border p-3 text-center">
                            <span class="grid h-7 w-7 place-items-center" style="color:{{ $color }}; font-color:{{$color}}">
                                <x-dynamic-component :component="'icons.'.$icon" class="h-5 w-5" />
                            </span>
                            <span class="text-ink-soft text-[11px] font-semibold">{{ $name }}</span>
                        </div>
                    @endforeach
                    <div class="border-line bg-paper flex flex-col items-center gap-1.5 rounded-xl border border-dashed p-3 text-center">
                        <span class="text-ink-soft grid h-7 w-7 place-items-center text-lg font-bold">+</span>
                        <span class="text-ink-soft text-[11px] font-semibold">more</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Floating sticker --}}
            <div class="absolute -bottom-5 -left-5 hidden sm:block">
                <x-ui.tag tone="ink" rotate class="card-pop !text-xs"> ⚡ One write, eight platforms </x-ui.tag>
            </div>
        </div>
    </div>
</x-ui.section>
