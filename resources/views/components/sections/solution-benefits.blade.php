{{-- Solution intro + benefits. The unique-content core of each page —
     this is what stops Google treating these as duplicate boilerplate. --}}

@props(['solution'])

<x-ui.section id="how" pad="default">
    <div class="grid gap-14 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">

        <div class="lg:sticky lg:top-28">
            <x-ui.tag tone="mint" rotate>Why it works for {{ $solution['name'] }}</x-ui.tag>
            <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">
                {{ $solution['intro_title'] }}
            </h2>
            <p class="mt-5 text-lg text-ink-soft">
                {{ $solution['intro_body'] }}
            </p>
            <div class="mt-8">
                <x-ui.button href="/app/register" variant="dark" icon>
                    Try it with {{ $solution['name'] }}
                </x-ui.button>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($solution['benefits'] as $i => [$title, $body])
                <x-ui.card pop tone="{{ $i === 0 ? 'flare' : 'paper' }}" class="p-7">
                    <span class="font-display text-3xl font-extrabold {{ $i === 0 ? 'text-paper/40' : 'text-line' }}">
                        0{{ $i + 1 }}
                    </span>
                    <h3 class="mt-3 text-xl font-bold">{{ $title }}</h3>
                    <p class="mt-2 text-sm {{ $i === 0 ? 'text-paper/80' : 'text-ink-soft' }}">
                        {{ $body }}
                    </p>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</x-ui.section>