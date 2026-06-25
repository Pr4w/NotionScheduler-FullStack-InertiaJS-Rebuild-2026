{{-- Features — bento grid. Mixes one big "why" tile with smaller feature tiles. --}}

<x-ui.section id="features" pad="default">
    <div class="max-w-2xl">
        <x-ui.tag tone="flare" rotate>The good stuff</x-ui.tag>
        <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">Everything in one place. Finally.</h2>
        <p class="text-ink-soft mt-4 text-lg">No more juggling a planner, a doc, three browser tabs and a scheduler that looks like it was designed in 2013.</p>
    </div>

    <div class="mt-14 grid gap-5 lg:grid-cols-3">
        {{-- Big tile --}}
        <x-ui.card pop tone="ink" class="flex flex-col justify-between p-9 lg:col-span-2 lg:row-span-2">
            <div>
                <x-ui.tag tone="flare" class="!text-[10px]">Why people switch</x-ui.tag>
                <h3 class="mt-5 text-3xl font-extrabold sm:text-4xl">Your workspace is already your content HQ.</h3>
                <p class="text-paper/70 mt-4 max-w-md">You plan campaigns in Notion. You write drafts in Notion. You track ideas in Notion. So why does scheduling them mean exporting to yet another tool? It doesn't have to.</p>
            </div>
            <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach ([
                    ['Calendar view', 'See the month at a glance'],
                    ['Captions inline', 'Right next to the asset'],
                    ['Unlimited accounts', 'On paid plans'],
                ] as [$t, $d])
                    <div class="border-paper/15 rounded-xl border p-4">
                        <p class="font-bold">{{ $t }}</p>
                        <p class="text-paper/60 mt-1 text-xs">{{ $d }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        @foreach ([
    ['🗓️', 'Schedule from a calendar', 'Use Notion\'s calendar view to see every queued post. Drag, drop, done.'],
    ['🖼️', 'Images & video', 'Drop media straight into the Notion row. No re-uploading anywhere.'],
    ['👥', 'Team-friendly', 'Your whole team already has Notion access. They keep it.'],
] as [$emoji, $title, $body])
            <x-ui.card pop tone="paper" class="p-7">
                <div class="text-3xl">{{ $emoji }}</div>
                <h3 class="mt-4 text-xl font-bold">{{ $title }}</h3>
                <p class="text-ink-soft mt-2 text-sm">{{ $body }}</p>
            </x-ui.card>
        @endforeach

        {{-- Linked tile → /socialmedia. In-content link from the homepage
     (the site's highest-authority page) to the social media hub.
     Strongest single internal link available to that page. --}}
        <a href="/socialmedia" class="group">
            <x-ui.card pop tone="paper" class="h-full p-7 transition-transform group-hover:-translate-y-0.5">
                <div class="text-3xl">🌍</div>
                <h3 class="mt-4 text-xl font-bold">Notion for social media management</h3>
                <p class="text-ink-soft mt-2 text-sm">Instagram, LinkedIn, X, TikTok, Facebook, Threads, YouTube — all from one Notion database. See the full overview.</p>
            </x-ui.card>
        </a>
    </div>
</x-ui.section>
