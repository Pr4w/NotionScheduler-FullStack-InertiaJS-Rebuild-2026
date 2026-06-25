<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Money = number | { price: number; stripe: string };

interface PriceTier {
    name: string;
    description: string;
    monthly: Money;
    yearly: Money;
    saved: number;
    social_accounts: number;
    databases: number;
    post_limit: boolean;
    post_limit_count?: number;
}

interface Tier extends PriceTier {
    key: string;
}

const props = defineProps<{
    packages: Record<string, PriceTier>;
    currentTier: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pricing', href: '/app/pricing' }],
    },
});

const plan = ref<'monthly' | 'yearly'>('monthly');
// Agency (tier_4) is tucked away behind a "need more?" toggle — unless the user
// is already on it.
const showAgency = ref(props.currentTier === 'tier_4');

const tier = (key: string): Tier | null =>
    props.packages[key] ? { key, ...props.packages[key] } : null;

const standardTiers = computed(() =>
    ['tier_1', 'tier_2', 'tier_3']
        .map(tier)
        .filter((t): t is Tier => t !== null),
);
const agencyTier = computed(() => tier('tier_4'));

const visibleTiers = computed<Tier[]>(() =>
    showAgency.value
        ? agencyTier.value
            ? [agencyTier.value]
            : []
        : standardTiers.value,
);

const priceOf = (p: PriceTier, which: 'monthly' | 'yearly'): number => {
    const v = p[which];
    return typeof v === 'object' ? v.price : v;
};

const features = (p: PriceTier): string[] => [
    `${p.social_accounts} social accounts`,
    `${p.databases} Notion database${p.databases === 1 ? '' : 's'}`,
    p.post_limit ? `${p.post_limit_count} posts / month` : 'Unlimited posts',
    'Calendar planning inside Notion',
];

const purchase = useHttp<{ package: string | null; plan: string }>({
    package: null,
    plan: 'monthly',
});
function subscribe(tierKey: string) {
    purchase.package = tierKey;
    purchase.plan = plan.value;
    purchase.post('/app/purchase', {
        onSuccess: (res: unknown) => {
            const env = res as {
                status?: string;
                data?: unknown;
                messages?: { message?: string }[];
            };
            if (typeof env.data === 'string' && env.data.startsWith('http')) {
                window.location.href = env.data;
                return;
            }
            toast.error(
                env.messages?.[0]?.message ?? 'Could not start checkout.',
            );
        },
        onError: () => toast.error('Could not start checkout.'),
    });
}

const togglePlan = (p: string): string =>
    'rounded-full px-5 py-1.5 text-sm font-medium transition-colors ' +
    (plan.value === p
        ? 'bg-primary text-primary-foreground shadow-sm'
        : 'text-muted-foreground hover:text-foreground');
</script>

<template>
    <Head title="Pricing" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 py-6">
        <!-- Header + billing toggle -->
        <div class="flex flex-col items-center gap-4 text-center">
            <h1 class="text-3xl font-bold tracking-tight">
                Plans &amp; pricing
            </h1>
            <p class="max-w-md text-sm text-muted-foreground">
                Schedule social posts straight from Notion. Upgrade or change
                your plan at any time.
            </p>
            <div class="inline-flex rounded-full border border-border p-1">
                <button
                    type="button"
                    :class="togglePlan('monthly')"
                    @click="plan = 'monthly'"
                >
                    Monthly
                </button>
                <button
                    type="button"
                    :class="togglePlan('yearly')"
                    @click="plan = 'yearly'"
                >
                    Yearly
                </button>
            </div>
        </div>

        <!-- Plans -->
        <div
            class="grid gap-6"
            :class="showAgency ? 'mx-auto w-full max-w-sm' : 'md:grid-cols-3'"
        >
            <div
                v-for="t in visibleTiers"
                :key="t.key"
                class="relative flex flex-col rounded-2xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md"
                :class="
                    t.key === currentTier
                        ? 'border-primary ring-2 ring-primary/30'
                        : 'border-border'
                "
            >
                <span
                    v-if="t.key === currentTier"
                    class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-0.5 text-xs font-semibold text-primary-foreground"
                >
                    Current plan
                </span>

                <h2 class="text-lg font-semibold">{{ t.name }}</h2>
                <p class="mt-1 min-h-[2.5rem] text-sm text-muted-foreground">
                    {{ t.description }}
                </p>

                <div class="mt-5 flex items-baseline gap-1">
                    <span class="text-4xl font-bold"
                        >€{{ priceOf(t, plan) }}</span
                    >
                    <span class="text-sm text-muted-foreground"
                        >/{{ plan === 'monthly' ? 'mo' : 'yr' }}</span
                    >
                </div>
                <p
                    v-if="plan === 'yearly' && t.saved > 0"
                    class="mt-1 text-xs font-medium text-green-600"
                >
                    Save {{ t.saved }}€ vs paying monthly
                </p>
                <p v-else class="mt-1 text-xs text-transparent select-none">
                    .
                </p>

                <ul class="mt-6 flex-1 space-y-3 text-sm">
                    <li
                        v-for="f in features(t)"
                        :key="f"
                        class="flex items-center gap-2"
                    >
                        <Check class="h-4 w-4 shrink-0 text-primary" />
                        {{ f }}
                    </li>
                </ul>

                <Button
                    class="mt-6 w-full"
                    :variant="t.key === currentTier ? 'outline' : 'default'"
                    :disabled="
                        t.key === currentTier ||
                        priceOf(t, plan) === 0 ||
                        purchase.processing
                    "
                    @click="subscribe(t.key)"
                >
                    {{
                        t.key === currentTier
                            ? 'Current plan'
                            : priceOf(t, plan) === 0
                              ? 'Free forever'
                              : 'Choose plan'
                    }}
                </Button>
            </div>
        </div>

        <!-- Agency / contact toggles -->
        <div class="text-center text-sm text-muted-foreground">
            <template v-if="!showAgency">
                <template v-if="agencyTier">
                    Need more?
                    <button
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="showAgency = true"
                    >
                        Check out our agency plan →
                    </button>
                </template>
            </template>
            <div v-else class="space-y-2">
                <div>
                    <button
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="showAgency = false"
                    >
                        ← Back to plans
                    </button>
                </div>
                <div>
                    Need even more?
                    <Link
                        href="/app/support"
                        class="font-medium text-primary hover:underline"
                    >
                        Contact support
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
