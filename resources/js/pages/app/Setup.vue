<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, router, useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AddDatabaseDialog from '@/components/AddDatabaseDialog.vue';
import AddSocialAccountDialog from '@/components/AddSocialAccountDialog.vue';
import { useOAuthConnect } from '@/composables/useOAuthConnect';

const props = defineProps<{
    hasNotionToken: boolean;
    databasesCount: number;
    socialsCount: number;
    completedWizard: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Setup', href: '/app/setup' }],
    },
});

const stepTitles = [
    'Welcome',
    'Connect Notion',
    'Add a database',
    'Connect accounts',
    'All set',
];
const step = ref(0);

const hasNotion = ref(props.hasNotionToken);
const dbCount = ref(props.databasesCount);
const socialCount = ref(props.socialsCount);

const { connect, connecting } = useOAuthConnect();

function next() {
    if (step.value < stepTitles.length - 1) step.value++;
}
function back() {
    if (step.value > 0) step.value--;
}

function onDatabaseConnected() {
    dbCount.value++;
    toast.success('Database connected.');
}

const finishing = useHttp({});
function finish() {
    finishing.get('/app/user/finishedWizard', {
        onSuccess: () => router.visit('/app/dashboard'),
        onError: () => toast.error('Could not finish setup.'),
    });
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('oauth_status');

    if (status) {
        const platform = params.get('oauth_platform') ?? '';
        if (status === 'success') {
            if (platform === 'notion') {
                hasNotion.value = true;
                step.value = 2;
                toast.success('Notion connected.');
            } else {
                socialCount.value++;
                step.value = 3;
                toast.success('Account connected.');
            }
        } else {
            toast.error(params.get('oauth_message') || 'Connection failed.');
            step.value = hasNotion.value ? 3 : 1;
        }
        window.history.replaceState({}, '', '/app/setup');
        return;
    }

    // Fresh load: resume at the first incomplete step.
    if (!props.hasNotionToken) step.value = 0;
    else if (props.databasesCount < 1) step.value = 2;
    else step.value = 3;
});
</script>

<template>
    <Head title="Setup" />

    <div class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-6 p-4">
        <!-- Step indicator -->
        <div class="flex items-center justify-between">
            <div
                v-for="(title, i) in stepTitles"
                :key="title"
                class="flex flex-1 flex-col items-center gap-1"
            >
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
                    :class="
                        i < step
                            ? 'bg-primary text-primary-foreground'
                            : i === step
                              ? 'border-2 border-primary text-primary'
                              : 'border border-border text-muted-foreground'
                    "
                >
                    {{ i < step ? '✓' : i + 1 }}
                </div>
                <span class="text-center text-[10px] text-muted-foreground">{{
                    title
                }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-border p-6">
            <!-- Step 0: Welcome -->
            <div v-if="step === 0" class="space-y-4 text-center">
                <h1 class="text-2xl font-semibold">
                    Welcome to NotionScheduler
                </h1>
                <p class="text-sm text-muted-foreground">
                    Let's get you set up: connect your Notion workspace, pick a
                    database to schedule from, and link your social accounts. It
                    only takes a minute.
                </p>
                <Button @click="next">Let's go</Button>
            </div>

            <!-- Step 1: Connect Notion -->
            <div v-else-if="step === 1" class="space-y-4">
                <h2 class="text-lg font-semibold">
                    Connect your Notion workspace
                </h2>
                <p class="text-sm text-muted-foreground">
                    NotionScheduler reads the databases you choose so it can
                    publish your scheduled posts.
                </p>
                <div
                    v-if="hasNotion"
                    class="rounded-md bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-300"
                >
                    ✓ Notion is connected.
                </div>
                <Button
                    v-else
                    :disabled="connecting !== null"
                    @click="connect('notion', 'setup')"
                >
                    <Spinner v-if="connecting === 'notion'" /> Connect Notion
                </Button>
            </div>

            <!-- Step 2: Add a database -->
            <div v-else-if="step === 2" class="space-y-4">
                <h2 class="text-lg font-semibold">Add a Notion database</h2>
                <p class="text-sm text-muted-foreground">
                    Choose a database from your workspace to schedule posts
                    from. You currently have
                    <strong>{{ dbCount }}</strong> connected.
                </p>
                <AddDatabaseDialog @connected="onDatabaseConnected" />
            </div>

            <!-- Step 3: Connect social accounts -->
            <div v-else-if="step === 3" class="space-y-4">
                <h2 class="text-lg font-semibold">
                    Connect your social accounts
                </h2>
                <p class="text-sm text-muted-foreground">
                    Link the accounts you want to publish to. You currently have
                    <strong>{{ socialCount }}</strong> connected.
                </p>
                <AddSocialAccountDialog return-to="setup" />
            </div>

            <!-- Step 4: Done -->
            <div v-else class="space-y-4 text-center">
                <h2 class="text-xl font-semibold">You're all set! 🎉</h2>
                <p class="text-sm text-muted-foreground">
                    Head to your dashboard to manage databases, accounts, and
                    scheduled posts.
                </p>
                <Button :disabled="finishing.processing" @click="finish">
                    <Spinner v-if="finishing.processing" /> Go to dashboard
                </Button>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex items-center justify-between">
            <Button v-if="step > 0 && step < 4" variant="ghost" @click="back"
                >Back</Button
            >
            <span v-else></span>
            <Button
                v-if="step > 0 && step < 4"
                :disabled="step === 1 && !hasNotion"
                @click="next"
                >Next</Button
            >
        </div>
    </div>
</template>
