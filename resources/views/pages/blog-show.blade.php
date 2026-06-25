{{--
    Blog post — /blog/{slug}.

    SEO: we call $post->getDynamicSEOData() directly and pass it as
    $SEOData so the layout's single seo($SEOData) path stays intact and
    still emits Article + BreadcrumbList JSON-LD. No ->for() plumbing in
    the layout, one code path, consistent with the rest of the app.

    ⚠ VERIFY BEFORE TRUSTING: the breadcrumb builder API inside
    getDynamicSEOData() (->addBreadcrumbList / ->prependBreadcrumbs) is
    the package's least-documented surface. If it throws, the fix is in
    the BlogPost model, not this view.
--}}

@php
    $SEOData = $post->getDynamicSEOData();

    // Resolve related config entries from the slugs stored on the post.
    $relatedPlatforms = collect($post->platforms ?? [])
        ->map(fn ($slug) => config("solutions.{$slug}"))
        ->filter()->values();

    $relatedUseCases = collect($post->use_cases ?? [])
        ->map(fn ($slug) => config("use-cases.{$slug}"))
        ->filter()->values();
@endphp

<x-layout.app :SEOData="$SEOData">
    {{-- Breadcrumb (visual; the JSON-LD equivalent is emitted by the SEO package) --}}
    <x-ui.section pad="tight" width="narrow">
        <nav class="text-ink-soft flex items-center gap-2 font-mono text-xs tracking-widest uppercase" aria-label="Breadcrumb">
            <a href="/" class="hover:text-flare-600">Home</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-flare-600">Blog</a>
            <span>/</span>
            <span class="text-ink">{{ Str::limit($post->title, 40) }}</span>
        </nav>
    </x-ui.section>

    {{-- Header --}}
    <x-ui.section pad="tight" width="narrow">
        <x-ui.tag tone="flare" rotate> {{ $post->published_at->format('F j, Y') }} </x-ui.tag>
        <h1 class="mt-6 text-4xl font-extrabold sm:text-5xl lg:text-6xl">{{ $post->title }}</h1>
        <p class="text-ink-soft mt-5 text-lg sm:text-xl">{{ $post->excerpt }}</p>
    </x-ui.section>

    @if ($post->cover_url)
        <x-ui.section pad="tight" width="default">
            <div class="border-ink card-pop overflow-hidden rounded-[var(--radius-card)] border-2">
                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full" />
            </div>
        </x-ui.section>
    @endif

    {{-- Body. RichEditor stores HTML, so it's rendered unescaped.
         The prose classes style headings/lists/links from the editor
         output to match the site. --}}
    <x-ui.section pad="default" width="narrow">
        <div
            class="prose prose-lg prose-headings:font-display prose-headings:font-extrabold prose-a:text-flare-600 prose-a:no-underline hover:prose-a:underline prose-strong:text-ink prose-img:rounded-2xl prose-img:border-2 prose-img:border-ink max-w-none"
        >
            {!! $post->body !!}
        </div>
    </x-ui.section>

    {{-- Internal linking payoff: post → relevant solution/use-case pages.
         This is the whole point of the platforms/use_cases columns. --}}
    @if ($relatedPlatforms->isNotEmpty() || $relatedUseCases->isNotEmpty())
        <x-ui.section pad="default" width="default">
            <x-ui.card pop tone="sink" class="p-8 sm:p-12">
                <x-ui.tag tone="ink" rotate>Keep going</x-ui.tag>
                <h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Put this into practice.</h2>

                <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($relatedPlatforms as $s)
                        <a
                            href="/{{ $s['slug'] }}"
                            class="group border-ink bg-paper flex items-center gap-3 rounded-2xl border-2 p-4 transition-transform hover:-translate-y-0.5"
                        >
                            <span class="bg-paper grid h-9 w-9 shrink-0 place-items-center rounded-xl" style="color:{{ $s['accent'] }}">
                                <x-dynamic-component :component="'icons.'.$s['slug']" class="h-5 w-5" />
                            </span>
                            <span class="font-display flex-1 text-sm font-bold">Schedule {{ $s['name'] }}</span>
                        </a>
                    @endforeach

                    @foreach ($relatedUseCases as $c)
                        <a
                            href="/for/{{ $c['slug'] }}"
                            class="group border-ink bg-paper flex items-center gap-3 rounded-2xl border-2 p-4 transition-transform hover:-translate-y-0.5"
                        >
                            <span class="bg-flare-100 font-display text-flare-700 grid h-9 w-9 shrink-0 place-items-center rounded-xl font-bold">
                                {{ Str::substr($c['name'], 0, 1) }}
                            </span>
                            <span class="font-display flex-1 text-sm font-bold">{{ $c['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        </x-ui.section>
    @endif

    {{-- Closing CTA --}}
    <x-ui.section pad="default" width="narrow">
        <x-ui.card pop tone="ink" class="p-8 text-center sm:p-12">
            <h2 class="text-3xl font-extrabold sm:text-4xl">Stop reading about it. Go schedule something.</h2>
            <p class="text-paper/70 mt-3">Free plan, no card, your Notion workspace.</p>
            <div class="mt-7 flex justify-center">
                <x-ui.button href="/app/register" variant="primary" size="lg" icon> Start free </x-ui.button>
            </div>
        </x-ui.card>
    </x-ui.section>
</x-layout.app>
