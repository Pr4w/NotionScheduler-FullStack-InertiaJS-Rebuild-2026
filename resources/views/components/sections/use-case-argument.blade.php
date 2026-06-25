{{-- The differentiated core: distinct argument + 3-step workflow + a real
     objection handled. This is what stops the four use-case pages reading
     as the same page with a word swapped. --}}

@props (['case'])

<x-ui.section id="argument" pad="default">
    <div class="grid gap-14 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
        <div class="lg:sticky lg:top-28">
            <x-ui.tag tone="mint" rotate>Why {{ $case['name'] }}</x-ui.tag>
            <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">The honest version.</h2>
            <p class="text-ink-soft mt-5 text-lg">{{ $case['argument'] }}</p>
        </div>

        <div>
            <div class="grid gap-5">
                @foreach ($case['workflow'] as $i => [$title, $body])
                    <x-ui.card pop tone="{{ $i === 0 ? 'flare' : 'paper' }}" class="p-7">
                        <div class="flex items-start gap-4">
                            <span class="font-display text-3xl font-extrabold {{ $i === 0 ? 'text-paper/40' : 'text-line' }}"> 0{{ $i + 1 }} </span>
                            <div>
                                <h3 class="text-xl font-bold">{{ $title }}</h3>
                                <p class="mt-2 text-sm {{ $i === 0 ? 'text-paper/80' : 'text-ink-soft' }}">{{ $body }}</p>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{-- The objection block — addresses the one thing this audience
                 actually pushes back on. Different per page by design. --}}
            <x-ui.card tone="sink" class="mt-5 p-7">
                <p class="font-display text-lg font-bold">{{ $case['objection'][0] }}</p>
                <p class="text-ink-soft mt-2">{{ $case['objection'][1] }}</p>
            </x-ui.card>
        </div>
    </div>
</x-ui.section>
