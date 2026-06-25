{{--
    Blog index — /blog. Lists published posts (BlogPost::published()).
    SEOData passed from BlogController (explicit, no model). Reuses the
    same x-ui primitives so it's visually identical to the rest of the site.
--}}

<x-layout.app :SEOData="$SEOData">
    <x-ui.section pad="default" width="wide">
        <div class="max-w-2xl">
            <x-ui.tag tone="flare" rotate>The blog</x-ui.tag>
            <h1 class="mt-6 text-5xl font-extrabold sm:text-6xl">Notion, content, and not losing your mind.</h1>
            <p class="text-ink-soft mt-5 text-lg">Practical guides on planning, batching and scheduling social content from the workspace you already live in.</p>
        </div>

        @if ($posts->isEmpty())
            <x-ui.card tone="sink" class="mt-14 p-12 text-center">
                <p class="font-display text-xl font-bold">Nothing published yet.</p>
                <p class="text-ink-soft mt-2">First guide is on its way.</p>
            </x-ui.card>
        @else
            <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="group block">
                        <x-ui.card pop tone="paper" class="flex h-full flex-col overflow-hidden">
                            @if ($post->cover_url)
                                <div class="border-ink aspect-[16/10] overflow-hidden border-b-2">
                                    <img
                                        src="{{ $post->cover_url }}"
                                        alt="{{ $post->title }}"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                            @else
                                <div class="border-ink bg-flare-100 aspect-[16/10] border-b-2"></div>
                            @endif

                            <div class="flex flex-1 flex-col p-6">
                                <p class="text-ink-soft font-mono text-xs tracking-widest uppercase">{{ $post->published_at->format('M j, Y') }}</p>
                                <h2 class="font-display mt-3 text-xl leading-snug font-bold">{{ $post->title }}</h2>
                                <p class="text-ink-soft mt-2 flex-1 text-sm">{{ $post->excerpt }}</p>
                                <span class="text-flare-600 mt-4 inline-flex items-center gap-1.5 font-semibold">
                                    Read it
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </div>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
            <div class="mt-12">{{ $posts->links() }}</div>
        @endif
    </x-ui.section>
</x-layout.app>
