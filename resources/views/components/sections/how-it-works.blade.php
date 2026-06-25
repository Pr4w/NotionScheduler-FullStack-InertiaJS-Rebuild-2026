{{-- How it works — three plain steps. Data-driven so it's easy to tweak. --}}

@php
    $steps = [
        [
            'n' => '01',
            'title' => 'Connect Notion',
            'body' => 'Two clicks to give NotionScheduler access to one page in your workspace. No clunky plugin, no template you have to copy.',
            'emoji' => '🔗',
        ],
        [
            'n' => '02',
            'title' => 'Link your accounts',
            'body' => 'Hook up Instagram, LinkedIn, X, TikTok, whatever you run. They all live under one roof from here on.',
            'emoji' => '🪄',
        ],
        [
            'n' => '03',
            'title' => 'Write & schedule',
            'body' => "Draft posts the way you already write everything else — in Notion. Set a date. We handle the publishing.",
            'emoji' => '🚀',
        ],
    ];
@endphp

<x-ui.section id="how" pad="default">
    <div class="mx-auto max-w-2xl text-center">
        <x-ui.tag tone="mint" rotate>How it works</x-ui.tag>
        <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">
            Three steps. Then never think about it again.
        </h2>
        <p class="mt-4 text-lg text-ink-soft">
            Honestly, the hardest part is deciding what to post.
        </p>
    </div>

    <div class="mt-16 grid gap-6 md:grid-cols-3">
        @foreach ($steps as $i => $step)
            <x-ui.card pop tone="{{ $i === 1 ? 'flare' : 'paper' }}" class="relative p-8">
                <span class="font-display text-6xl font-extrabold {{ $i === 1 ? 'text-paper/30' : 'text-line' }}">
                    {{ $step['n'] }}
                </span>
                <div class="mt-4 text-3xl">{{ $step['emoji'] }}</div>
                <h3 class="mt-3 text-2xl font-bold">{{ $step['title'] }}</h3>
                <p class="mt-3 {{ $i === 1 ? 'text-paper/80' : 'text-ink-soft' }}">
                    {{ $step['body'] }}
                </p>
            </x-ui.card>
        @endforeach
    </div>
</x-ui.section>