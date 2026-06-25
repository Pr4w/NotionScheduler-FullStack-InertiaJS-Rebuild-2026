<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
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

const tiers = computed(() =>
    Object.entries(props.packages).map(([key, p]) => ({ key, ...p })),
);

const priceOf = (p: PriceTier, which: 'monthly' | 'yearly'): number => {
    const v = p[which];
    return typeof v === 'object' ? v.price : v;
};

const features = (p: PriceTier): string[] => {
    const list = [
        `${p.social_accounts} social accounts`,
        `${p.databases} Notion database${p.databases === 1 ? '' : 's'}`,
    ];
    list.push(
        p.post_limit
            ? `${p.post_limit_count} posts / month`
            : 'Unlimited posts',
    );
    return list;
};

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
</script>

<template>
    <Head title="Pricing" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col items-center gap-3 text-center">
            <h1 class="text-2xl font-semibold">Plans &amp; pricing</h1>
            <p class="text-sm text-muted-foreground">
                Upgrade or change your plan at any time.
            </p>

            <!-- Billing period toggle -->
            <div class="inline-flex rounded-lg border border-border p-1">
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        plan === 'monthly'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground'
                    "
                    @click="plan = 'monthly'"
                >
                    Monthly
                </button>
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        plan === 'yearly'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground'
                    "
                    @click="plan = 'yearly'"
                >
                    Yearly
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="t in tiers"
                :key="t.key"
                class="flex flex-col rounded-xl border p-5"
                :class="
                    t.key === currentTier
                        ? 'border-primary ring-1 ring-primary'
                        : 'border-border'
                "
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ t.name }}</h2>
                    <span
                        v-if="t.key === currentTier"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        Current
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t.description }}
                </p>

                <div class="mt-4">
                    <span class="text-3xl font-bold"
                        >${{ priceOf(t, plan) }}</span
                    >
                    <span class="text-sm text-muted-foreground"
                        >/{{ plan === 'monthly' ? 'mo' : 'yr' }}</span
                    >
                </div>
                <p
                    v-if="plan === 'yearly' && t.saved > 0"
                    class="mt-1 text-xs font-medium text-green-600"
                >
                    Save {{ t.saved }}%
                </p>

                <ul class="mt-4 flex-1 space-y-2 text-sm">
                    <li
                        v-for="f in features(t)"
                        :key="f"
                        class="flex items-center gap-2"
                    >
                        <span class="text-green-600">✓</span>{{ f }}
                    </li>
                </ul>

                <Button
                    class="mt-5"
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
                              ? 'Free'
                              : 'Choose plan'
                    }}
                </Button>
            </div>
        </div>
    </div>
</template>
