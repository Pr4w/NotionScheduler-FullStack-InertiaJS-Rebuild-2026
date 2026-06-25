{{--
    /socialmedia — the social media management hub.

    NOT a config-driven solution page. This is a standalone category /
    commercial-intent landing page that was already ranking ~14 for
    "notion social media management" while orphaned. Its job is to
    consolidate that cluster and funnel to the platform pages.

    URL is fixed at /socialmedia — do not change it (ranking history).
--}}

<x-layout.app :SEOData="$SEOData">
    {{-- HERO: H1 uses the exact query language it ranks for --}}
    <x-ui.section pad="default" width="wide" class="relative overflow-hidden">
        <div class="bg-flare-100 pointer-events-none absolute -top-32 -right-40 -z-10 h-96 w-96 rounded-full blur-3xl" aria-hidden="true"></div>

        <div class="max-w-3xl">
            <x-ui.tag tone="flare" rotate>🗓️ Notion + social media</x-ui.tag>

            <h1 class="mt-6 text-5xl font-extrabold sm:text-6xl xl:text-7xl">Notion for social media management.</h1>

            <p class="text-ink-soft mt-6 text-lg sm:text-xl">Plan, write and auto-publish your entire social presence from one Notion database. No second app, no copy-paste, no exporting your plan into a tool you then have to keep in sync.</p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.button href="/app/register" size="lg" icon> Start free — no card </x-ui.button>
                <a href="#how" class="text-ink-soft hover:text-ink inline-flex items-center gap-2 px-4 py-3 font-semibold transition-colors">
                    How it works
                </a>
            </div>
        </div>
    </x-ui.section>

    {{-- WHAT IT IS: directly answers "can notion post to social media" --}}
    <x-ui.section id="how" pad="default" width="default">
        <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
            <div class="lg:sticky lg:top-28">
                <x-ui.tag tone="mint" rotate>The short version</x-ui.tag>
                <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">Notion can't post on its own. This is the part that does.</h2>
                <p class="text-ink-soft mt-5 text-lg">Notion is a database that looks like a document. It has no idea your caption is meant to become an Instagram post. NotionScheduler is the layer that reads your Notion database and publishes to each platform on the dates you set, so the place you plan and the thing that posts are the same place.</p>
            </div>

            <div class="grid gap-5">
                @foreach ([
                    ['Plan in Notion', 'One database, one row per post: date, platform, status, the caption, the media. The structure a calendar actually needs.'],
                    ['Schedule from the row', 'Pick the account and the date, mark it scheduled. No separate compose screen, the Notion row is the compose screen.'],
                    ['It publishes itself', 'At the scheduled time it posts, hands-off, to the platform you chose. The plan and the publishing stop being two jobs.'],
                ] as $i => [$t, $b])
                    <x-ui.card pop tone="{{ $i === 0 ? 'flare' : 'paper' }}" class="p-7">
                        <span class="font-display text-3xl font-extrabold {{ $i === 0 ? 'text-paper/40' : 'text-line' }}">0{{ $i + 1 }}</span>
                        <h3 class="mt-3 text-xl font-bold">{{ $t }}</h3>
                        <p class="mt-2 text-sm {{ $i === 0 ? 'text-paper/80' : 'text-ink-soft' }}">{{ $b }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    </x-ui.section>

    {{-- PLATFORM GRID: the internal-link engine. Funnels authority from
         this strong page DOWN to the weak platform pages (/twitter etc).
         This is the structural fix for those position-34 pages. --}}
    <x-ui.section pad="default" width="wide">
        <x-ui.card pop tone="sink" class="p-8 sm:p-12">
            <x-ui.tag tone="ink" rotate>Every platform</x-ui.tag>
            <h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Manage all of them from the one database.</h2>
            <p class="text-ink-soft mt-3 max-w-lg">Same Notion workflow, every platform. Each has its own quirks, here's the honest detail per network.</p>

            <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (config('solutions') as $s)
                    <a
                        href="/{{ $s['slug'] }}"
                        class="group border-ink bg-paper flex items-center gap-3 rounded-2xl border-2 p-4 transition-transform hover:-translate-y-0.5"
                    >
                        <span class="bg-paper grid h-9 w-9 shrink-0 place-items-center rounded-xl" style="color:{{ $s['accent'] }}">
                            <x-dynamic-component :component="'icons.'.$s['slug']" class="h-5 w-5" />
                        </span>
                        <span class="font-display flex-1 text-sm font-bold">{{ $s['name'] }}</span>
                        <svg class="text-ink-soft h-4 w-4 shrink-0 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    {{-- HONESTY BLOCK: the churn-prevention filter, consistent with the
         whole site's voice and your stated churn cause --}}
    <x-ui.section pad="default" width="narrow">
        <x-ui.card tone="sink" class="p-8 sm:p-10">
            <h2 class="text-2xl font-extrabold sm:text-3xl">Is this actually for you?</h2>
            <p class="text-ink-soft mt-4">Honest answer, because it's the main reason people stay or leave. If you already live in Notion, this is one of those rare setups that's genuinely better than a dedicated tool: planning and publishing collapse into the one place you already work. If you don't use Notion daily, a purpose-built scheduler will feel less fiddly, and that's a fair thing for us to say out loud.</p>
        </x-ui.card>
    </x-ui.section>

    {{-- FAQ: matches the JSON-LD emitted by the controller --}}
    <x-sections.faq :faqs="$faqs" eyebrow="Social media + Notion FAQs" title="The questions<br>people actually ask." />
</x-layout.app>
