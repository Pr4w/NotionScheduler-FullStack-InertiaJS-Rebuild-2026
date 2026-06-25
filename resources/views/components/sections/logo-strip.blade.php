{{-- "Works with" strip — scrolling marquee of supported platforms. --}}

<div class="border-y-2 border-ink bg-ink py-6 text-paper">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-5 sm:px-8">
        <p class="hidden shrink-0 font-mono text-xs uppercase tracking-widest text-paper/50 md:block">
            Plays nice with
        </p>
        <div class="relative flex-1 overflow-hidden">
            <div class="marquee-track flex w-max items-center gap-12">
                @foreach (array_merge(
                    ['Notion', 'Instagram', 'Facebook', 'Threads', 'X (Twitter)', 'LinkedIn', 'TikTok', 'YouTube'],
                    ['Notion', 'Instagram', 'Facebook', 'Threads', 'X (Twitter)', 'LinkedIn', 'TikTok', 'YouTube']
                ) as $name)
                    <span class="shrink-0 font-display text-xl font-bold text-paper/80">{{ $name }}</span>
                @endforeach
            </div>
            {{-- Edge fades --}}
            <div class="pointer-events-none absolute inset-y-0 left-0 w-16 bg-gradient-to-r from-ink to-transparent"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-16 bg-gradient-to-l from-ink to-transparent"></div>
        </div>
    </div>
</div>