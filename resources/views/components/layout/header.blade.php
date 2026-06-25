{{--
    Site header. Sticky, with the one-page anchor nav and a mobile drawer.
    Nav links use real anchors so they keep working as plain HTML.
--}}

<header class="border-ink bg-paper/85 sticky top-0 z-40 border-b-2 backdrop-blur-md">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
        {{-- Logo --}}
        <a href="/" class="font-display flex items-center gap-2 text-xl font-extrabold tracking-tight">
            <span class="border-ink bg-flare-500 text-paper grid h-9 w-9 place-items-center rounded-xl border-2">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M3 2h7l3 3v9H3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                    <path d="M5.5 6.5h5M5.5 9h5M5.5 11.5h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </span>
            NotionScheduler
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary">
            @foreach ([
                '/#how' => 'How it works',
                '/#features' => 'Features',
                '/#pricing' => 'Pricing',
                '/#faq' => 'FAQs',
            ] as $href => $label)
                <a
                    href="{{ $href }}"
                    class="text-ink-soft hover:bg-paper-sink hover:text-ink rounded-full px-4 py-2 text-sm font-semibold transition-colors"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            {{-- Wrapper hides on mobile: `hidden` on the button itself loses to x-ui.button's base `inline-flex`. --}}
            <span class="hidden sm:inline-flex">
                <x-ui.button href="/app/register" size="sm" icon> Get started </x-ui.button>
            </span>

            {{-- Mobile toggle --}}
            <button
                data-nav-toggle
                type="button"
                aria-label="Open menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
                class="border-ink bg-paper grid h-12 w-12 place-items-center rounded-full border-2 lg:hidden"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile drawer --}}
    <div data-nav-menu id="mobile-menu" class="border-ink bg-paper hidden border-t-2 lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col px-5 py-4" aria-label="Mobile">
            @foreach ([
                '/#how' => 'How it works',
                '/#features' => 'Features',
                '/#pricing' => 'Pricing',
                '/#faq' => 'FAQs',
            ] as $href => $label)
                <a href="{{ $href }}" data-nav-toggle class="border-line border-b py-3 font-semibold"> {{ $label }} </a>
            @endforeach
            <x-ui.button href="/app/register" class="mt-4" icon>Get started free</x-ui.button>
        </nav>
    </div>
</header>
