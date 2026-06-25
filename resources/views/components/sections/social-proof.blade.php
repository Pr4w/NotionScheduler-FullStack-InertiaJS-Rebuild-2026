{{-- Social proof — a contained card, not a full-bleed band. Uses the
     site's core card motif so it reads as part of the page rhythm rather
     than a flashy interruption. Hidden entirely if stats unavailable. --}}

@php ($stats = app(\App\Services\FrontEndStats::class)->get())

@if ($stats['published_posts'] > 0)
    <x-ui.section pad="tight" width="default">
        <x-ui.card pop tone="paper" class="p-8 text-center sm:p-12">
            <p class="text-ink-soft font-mono text-xs font-medium tracking-widest uppercase">Join the movement</p>

            <div class="mt-7 flex flex-col items-center justify-center gap-8 sm:flex-row sm:gap-14">
                <div class="flex flex-col">
                    <span class="font-display text-5xl leading-none font-extrabold sm:text-6xl"> {{ number_format($stats['users']) }} </span>
                    <span class="text-ink-soft mt-2 text-sm font-semibold"> creators &amp; teams </span>
                </div>

                <div class="bg-line hidden h-14 w-px sm:block"></div>

                <div class="flex flex-col">
                    <span class="font-display text-flare-500 text-5xl leading-none font-extrabold sm:text-6xl">
                        {{ number_format($stats['published_posts']) }}
                    </span>
                    <span class="text-ink-soft mt-2 text-sm font-semibold"> posts published </span>
                </div>
            </div>

            <p class="text-ink-soft mx-auto mt-7 max-w-md text-base">Join the creators and teams already scheduling their social media straight from Notion.</p>
        </x-ui.card>
    </x-ui.section>
@endif
