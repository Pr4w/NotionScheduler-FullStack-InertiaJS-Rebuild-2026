{{--
    Pricing. Plans as a data array — swap for config('billing.plans') later.
    Monthly/annual toggle handled by a tiny scoped script (no Alpine needed,
    but trivially portable to Alpine if you adopt it).
--}}

@php
    $plans = [
        [
            'name' => 'Free',
            'tagline' => 'For getting your feet wet',
            'monthly' => 0,
            'annual' => 0,
            'featured' => false,
            'cta' => 'Start free',
            'features' => [
                ['Up to 2 social accounts', true],
                ['1 Notion database', true],
                ['Every platform supported', true],
                ['10 scheduled posts / month', false],
                ['Standard support', false],
            ],
        ],
        [
            'name' => 'Basic',
            'tagline' => 'For people who actually post',
            'monthly' => 10,
            'annual' => 90,
            'featured' => true,
            'cta' => 'Get Basic',
            'features' => [
                ['Up to 10 social accounts', true],
                ['2 Notion databases', true],
                ['Every platform supported', true],
                ['Unlimited scheduled posts', true],
                ['Priority support', true],
            ],
        ],
        [
            'name' => 'Pro',
            'tagline' => 'For agencies & power users',
            'monthly' => 25,
            'annual' => 250,
            'featured' => false,
            'cta' => 'Go Pro',
            'features' => [
                ['Up to 25 social accounts', true],
                ['5 Notion databases', true],
                ['Every platform supported', true],
                ['Unlimited scheduled posts', true],
                ['Priority support', true],
            ],
        ],
    ];
@endphp

<x-ui.section id="pricing" pad="default">
    <div class="mx-auto max-w-2xl text-center">
        <x-ui.tag tone="mint" rotate>Pricing</x-ui.tag>
        <h2 class="mt-6 text-4xl font-extrabold sm:text-5xl">
            Start free. Upgrade if you outgrow it.
        </h2>
        <p class="mt-4 text-lg text-ink-soft">
            Most people never need to pay us a cent. We're cool with that.
        </p>
    </div>

    {{-- Billing toggle --}}
    <div class="mt-10 flex items-center justify-center" data-pricing>
        <div class="inline-flex items-center gap-1 rounded-full border-2 border-ink bg-paper p-1">
            <button type="button" data-billing="monthly"
                class="rounded-full px-5 py-2 text-sm font-bold transition-colors data-[active=true]:bg-ink data-[active=true]:text-paper"
                data-active="true">
                Monthly
            </button>
            <button type="button" data-billing="annual"
                class="rounded-full px-5 py-2 text-sm font-bold transition-colors data-[active=true]:bg-ink data-[active=true]:text-paper"
                data-active="false">
                Annual <span class="ml-1 text-flare-600">−20%</span>
            </button>
        </div>
    </div>

    <div class="mt-14 grid gap-6 lg:grid-cols-3">
        @foreach ($plans as $plan)
            <div class="relative {{ $plan['featured'] ? 'lg:-mt-4 lg:mb-4' : '' }}">
                @if ($plan['featured'])
                    <div class="absolute -top-3 left-1/2 z-10 -translate-x-1/2">
                        <x-ui.tag tone="flare" class="card-pop">⭐ Most popular</x-ui.tag>
                    </div>
                @endif

                <x-ui.card pop tone="{{ $plan['featured'] ? 'flare' : 'paper' }}" class="flex h-full flex-col p-8">
                    <h3 class="text-2xl font-extrabold">{{ $plan['name'] }}</h3>
                    <p class="mt-1 text-sm {{ $plan['featured'] ? 'text-paper/70' : 'text-ink-soft' }}">
                        {{ $plan['tagline'] }}
                    </p>

                    <div class="mt-6 flex items-end gap-1">
                        <span class="font-display text-5xl font-extrabold"
                              data-price
                              data-monthly="{{ $plan['monthly'] }}"
                              data-annual="{{ $plan['annual'] }}">{{ $plan['monthly'] }}€</span>
                        <span class="mb-1.5 text-sm {{ $plan['featured'] ? 'text-paper/70' : 'text-ink-soft' }}"
                              data-period>/ month</span>
                    </div>

                    <ul class="mt-8 space-y-3">
                        @foreach ($plan['features'] as [$label, $included])
                            <li class="flex items-start gap-2.5 text-sm">
                                @if ($included)
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $plan['featured'] ? 'text-paper' : 'text-mint-500' }}" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M3 8.5l3.5 3.5L13 5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @else
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $plan['featured'] ? 'text-paper/40' : 'text-line' }}" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M4 8h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                    </svg>
                                @endif
                                <span class="{{ $plan['featured'] ? 'text-paper/90' : 'text-ink-soft' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 pt-2">
                        <x-ui.button
                            href="/app/register"
                            variant="{{ $plan['featured'] ? 'dark' : 'primary' }}"
                            class="w-full" icon>
                            {{ $plan['cta'] }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        @endforeach
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-pricing]');
            if (!root) return;
            const buttons = root.querySelectorAll('[data-billing]');
            const apply = (mode) => {
                buttons.forEach(b => b.setAttribute('data-active', String(b.dataset.billing === mode)));
                document.querySelectorAll('[data-price]').forEach(el => {
                    el.textContent = (mode === 'annual' ? el.dataset.annual : el.dataset.monthly) + '€';
                });
                document.querySelectorAll('[data-period]').forEach(el => {
                    el.textContent = mode === 'annual' ? '/ year' : '/ month';
                });
            };
            buttons.forEach(b => b.addEventListener('click', () => apply(b.dataset.billing)));
        })();
    </script>
</x-ui.section>