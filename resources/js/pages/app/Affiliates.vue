<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

interface AffiliateStats {
    signups: number;
    conversions: number;
    effectiveness: number;
    earnings: number;
    balance: number;
    paidOut: number;
}

const props = defineProps<{
    enrolled: boolean;
    referralName: string | null;
    stats: AffiliateStats | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Affiliates', href: '/app/affiliates' }],
    },
});

const referralUrl = computed(() =>
    props.referralName
        ? `https://notionscheduler.com/?ref=${encodeURIComponent(props.referralName)}`
        : '',
);

function copyReferral() {
    if (!referralUrl.value) return;
    navigator.clipboard.writeText(referralUrl.value);
    toast.success('Referral link copied to clipboard.');
}

const cards = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Sign-ups', value: props.stats.signups },
        { label: 'Conversions', value: props.stats.conversions },
        { label: 'Conversion rate', value: `${props.stats.effectiveness}%` },
        {
            label: 'Total earnings',
            value: `$${props.stats.earnings.toFixed(2)}`,
        },
        { label: 'Balance', value: `$${props.stats.balance.toFixed(2)}` },
        { label: 'Paid out', value: `$${props.stats.paidOut.toFixed(2)}` },
    ];
});
</script>

<template>
    <Head title="Affiliates" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Affiliate program</h1>
            <p class="text-sm text-muted-foreground">
                Earn 20% of every payment made by users you refer.
            </p>
        </div>

        <!-- Not enrolled -->
        <div
            v-if="!enrolled"
            class="rounded-xl border border-border p-8 text-center text-muted-foreground"
        >
            You're not enrolled in the affiliate program yet. Reach out to
            support to get your referral link.
        </div>

        <template v-else>
            <!-- Referral link -->
            <div class="rounded-xl border border-border p-4">
                <label class="text-sm font-medium text-muted-foreground"
                    >Your referral link</label
                >
                <div class="mt-2 flex items-center gap-2">
                    <input
                        :value="referralUrl"
                        readonly
                        class="w-full rounded-md border border-border bg-muted/40 px-3 py-2 text-sm"
                    />
                    <Button variant="outline" size="sm" @click="copyReferral"
                        >Copy</Button
                    >
                </div>
            </div>

            <!-- Stat cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="c in cards"
                    :key="c.label"
                    class="rounded-xl border border-border p-4"
                >
                    <div class="text-sm text-muted-foreground">
                        {{ c.label }}
                    </div>
                    <div class="mt-1 text-2xl font-semibold">{{ c.value }}</div>
                </div>
            </div>
        </template>
    </div>
</template>
