{{--
    FAQ accordion. Accessible, works without JS (first item defaults open).

    Props (all optional — landing page uses the defaults):
      faqs    — array of [question, answer]; defaults to the general FAQ
      eyebrow — tag label
      title   — heading (HTML allowed via {!! !!} below for the <br>)

    JSON-LD FAQPage schema is intentionally NOT emitted here — structured
    data is centralised through ralphjsmit/laravel-seo.
--}}

@props ([
    'faqs' => null,
    'eyebrow' => 'FAQs',
    'title' => "The questions you're<br>already thinking.",
])

@php
    $faqs ??= config('faq');
    // Unique per-render prefix so aria-controls/labelledby ids don't collide
    // if more than one FAQ section appears on a page.
    $uid = uniqid('faq-');
@endphp

<x-ui.section id="faq" pad="default">
    <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr]">
        <div>
            <x-ui.tag tone="flare" rotate>{{ $eyebrow }}</x-ui.tag>
            <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">{!! $title !!}</h2>
            <p class="text-ink-soft mt-4 text-lg">Can't find it? Ping us from the support page once you're in — we actually read those.</p>
            <div class="mt-8 hidden lg:block">
                <x-ui.button href="/app/register" variant="dark" icon> Just try it free </x-ui.button>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($faqs as $i => [$q, $a])
                <div data-faq-item data-open="{{ $i === 0 ? 'true' : 'false' }}" class="group border-ink bg-paper rounded-2xl border-2">
                    <button
                        data-faq-trigger
                        type="button"
                        id="{{ $uid }}-t{{ $i }}"
                        aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                        aria-controls="{{ $uid }}-p{{ $i }}"
                        class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left"
                    >
                        <span class="font-display text-lg font-bold">{{ $q }}</span>
                        <span
                            class="border-ink grid h-7 w-7 shrink-0 place-items-center rounded-full border-2 transition-transform group-data-[open=true]:rotate-45"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                            </svg>
                        </span>
                    </button>
                    <div
                        id="{{ $uid }}-p{{ $i }}"
                        role="region"
                        aria-labelledby="{{ $uid }}-t{{ $i }}"
                        class="grid grid-rows-[0fr] transition-all duration-300 group-data-[open=true]:grid-rows-[1fr]"
                    >
                        <div class="overflow-hidden">
                            <p class="text-ink-soft px-6 pb-6">{{ $a }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-ui.section>
