<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    AtSign,
    CalendarClock,
    ChevronDown,
    Database,
    RefreshCw,
    Send,
    Trash2,
} from '@lucide/vue';
import AddDatabaseDialog from '@/components/AddDatabaseDialog.vue';
import ManageDatabaseSocialsDialog from '@/components/ManageDatabaseSocialsDialog.vue';
import AddSocialAccountDialog from '@/components/AddSocialAccountDialog.vue';
import SocialIcon from '@/components/SocialIcon.vue';
import { dashboard } from '@/routes';
import { toastFromEnvelope } from '@/lib/notionToast';

interface SocialAccount {
    id: number;
    platform: string;
    name: string | null;
    profile_picture: string | null;
    followers: number | null;
    post_count: number | null;
    is_valid: number | boolean;
    account_full_identifier: string | null;
    database_id: number | null;
}

interface NotionDatabase {
    id: number;
    database_name: string | null;
    database_id: string;
    is_valid: number | boolean;
    is_active: number | boolean;
    socials: SocialAccount[];
}

interface Post {
    id: number;
    post_name: string | null;
    platform: string;
    status: string;
    scheduled_date: string | null;
    posted_date: string | null;
    account_id: number | null;
    in_flight: number | boolean;
    permalink: string | null;
    post_page_id: string | null;
}

interface Account {
    id: number;
    platform: string;
    name: string | null;
}

const props = defineProps<{
    databases: NotionDatabase[];
    socials: SocialAccount[];
    posts: Post[];
    accounts: Account[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

interface SubscriptionDetails {
    is_subscribed: boolean;
    tier: string;
    options: {
        social_accounts: number;
        databases: number;
        post_limit: boolean;
        post_limit_count?: number;
    };
    details: { name: string; description: string };
}

const page = usePage();
const authUser = computed(
    () =>
        (
            page.props.auth as
                | {
                      user?: {
                          name?: string | null;
                          subscription_details?: SubscriptionDetails;
                      };
                  }
                | undefined
        )?.user,
);
const userName = computed<string>(() => authUser.value?.name ?? 'there');
const plan = computed<SubscriptionDetails | null>(
    () => authUser.value?.subscription_details ?? null,
);

type TabKey = 'databases' | 'socials' | 'posts' | 'submitted';
const tab = ref<TabKey>('databases');

// Submitted (already-posted) posts — lazy-loaded + paginated via /app/posts/submitted.
const submitted = ref<{ items: Post[]; accounts: Account[]; lastPage: number }>(
    {
        items: [],
        accounts: [],
        lastPage: 1,
    },
);
const submittedTotal = ref<number | null>(null);
const submittedPage = ref(0);
const submittedHttp = useHttp({});

const tabs = computed(() => [
    {
        key: 'databases' as const,
        label: 'Databases',
        icon: Database,
        badge: plan.value
            ? `${props.databases.length}/${plan.value.options.databases}`
            : String(props.databases.length),
    },
    {
        key: 'socials' as const,
        label: 'Social Accounts',
        icon: AtSign,
        badge: plan.value
            ? `${props.socials.length}/${plan.value.options.social_accounts}`
            : String(props.socials.length),
    },
    {
        key: 'posts' as const,
        label: 'Scheduled Posts',
        icon: CalendarClock,
        badge: String(props.posts.length),
    },
    {
        key: 'submitted' as const,
        label: 'Submitted Posts',
        icon: Send,
        badge:
            submittedTotal.value !== null ? String(submittedTotal.value) : null,
    },
]);

const truthy = (v: number | boolean | null | undefined): boolean =>
    v === 1 || v === true;
const cap = (s: string | null): string =>
    s ? s.charAt(0).toUpperCase() + s.slice(1) : '—';
const accountName = (id: number | null): string =>
    props.accounts.find((a) => a.id === id)?.name ?? '—';
const fmtDate = (s: string | null): string =>
    s ? new Date(s).toLocaleString() : '—';
const notionUrl = (databaseId: string): string =>
    `https://www.notion.so/${(databaseId ?? '').replace(/-/g, '')}`;

function fmtNum(n: number | null): string {
    if (n === null || n === undefined) return '—';
    if (n >= 1_000_000)
        return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}

// Only surface followers/posts when we actually have the data, so cards never
// read "— followers · 0 posts".
function socialStats(s: SocialAccount): string {
    const parts: string[] = [];
    if (typeof s.followers === 'number' && s.followers > 0)
        parts.push(`${fmtNum(s.followers)} followers`);
    if (typeof s.post_count === 'number' && s.post_count > 0)
        parts.push(`${s.post_count} posts`);
    return parts.join(' · ');
}

// Stable ordering for accounts: by platform, then by name.
const socialSort = (a: SocialAccount, b: SocialAccount): number =>
    a.platform.localeCompare(b.platform) ||
    (a.name ?? '').localeCompare(b.name ?? '');
const sortAccounts = (list: SocialAccount[]): SocialAccount[] =>
    [...list].sort(socialSort);

// Group accounts by platform (each group sorted by name) so the Databases tab
// can show one column per social network.
function groupAccounts(
    list: SocialAccount[],
): { platform: string; accounts: SocialAccount[] }[] {
    const groups = new Map<string, SocialAccount[]>();
    for (const s of sortAccounts(list)) {
        if (!groups.has(s.platform)) groups.set(s.platform, []);
        groups.get(s.platform)!.push(s);
    }
    return [...groups.entries()].map(([platform, accounts]) => ({
        platform,
        accounts,
    }));
}

// Compact per-platform counts (used in the Databases tab + the social filter).
function platformSummary(
    list: SocialAccount[],
): { platform: string; count: number }[] {
    const map = new Map<string, number>();
    for (const s of list) map.set(s.platform, (map.get(s.platform) ?? 0) + 1);
    return [...map.entries()]
        .map(([platform, count]) => ({ platform, count }))
        .sort((a, b) => a.platform.localeCompare(b.platform));
}

// Social Accounts tab: platform filter + ordering.
const socialFilter = ref<string>('all');
const socialPlatforms = computed(() => platformSummary(props.socials));
const filteredSocials = computed(() => {
    const sorted = [...props.socials].sort(socialSort);
    return socialFilter.value === 'all'
        ? sorted
        : sorted.filter((s) => s.platform === socialFilter.value);
});
const filterChipClass = (p: string): string =>
    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors ' +
    (socialFilter.value === p
        ? 'bg-primary text-primary-foreground'
        : 'bg-muted text-muted-foreground hover:text-foreground');

function postStatusClass(post: Post): string {
    if (truthy(post.in_flight))
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    const s = (post.status ?? '').toLowerCase();
    if (['error', 'failed'].includes(s))
        return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
    if (s === 'posted')
        return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300';
    return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
}

const pill =
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';

// --- Mutations (JSON endpoints via useHttp; toasts from the envelope) ---
const postAction = useHttp<{ id: number | null }>({ id: null });
function deletePost(id: number) {
    postAction.id = id;
    postAction.post('/app/post/remove', {
        onSuccess: (res) => {
            toastFromEnvelope(res, 'Post removed from the schedule.');
            router.reload({ only: ['posts', 'accounts'] });
        },
        onError: () => toast.error('Could not remove the post.'),
    });
}

const socialAction = useHttp<{ id: number | null }>({ id: null });
function removeSocial(id: number) {
    socialAction.id = id;
    socialAction.post('/app/socials/remove', {
        onSuccess: (res) => {
            toastFromEnvelope(res, 'Social account removed.');
            router.reload({
                only: ['socials', 'databases', 'posts', 'accounts'],
            });
        },
        onError: () => toast.error('Could not remove the account.'),
    });
}

const dbAction = useHttp<{ id: number | null }>({ id: null });
function removeDatabase(id: number) {
    if (!window.confirm('Remove this database from NotionScheduler?')) return;
    dbAction.id = id;
    dbAction.post('/app/databases/remove', {
        onSuccess: (res) => {
            toastFromEnvelope(res, 'Database removed.');
            router.reload({ only: ['databases', 'posts', 'accounts'] });
        },
        onError: () => toast.error('Could not remove the database.'),
    });
}

const reconnectAction = useHttp<{ id: number | null }>({ id: null });
function reconnectDatabase(id: number) {
    reconnectAction.id = id;
    reconnectAction.post('/app/databases/reconnect', {
        onSuccess: (res) => {
            toastFromEnvelope(res, 'Database reconnected.');
            router.reload({ only: ['databases'] });
        },
        onError: () => toast.error('Could not reconnect the database.'),
    });
}

function onDatabaseConnected() {
    router.reload({ only: ['databases'] });
}

function onSocialsUpdated() {
    router.reload({ only: ['databases', 'socials'] });
}

function submittedAccountName(id: number | null): string {
    return submitted.value.accounts.find((a) => a.id === id)?.name ?? '—';
}

function loadSubmitted(reset = false) {
    const nextPage = reset ? 1 : submittedPage.value + 1;
    submittedHttp.get(`/app/posts/submitted?page=${nextPage}`, {
        onSuccess: (res: unknown) => {
            const env = res as {
                data?: {
                    posts: Post[];
                    accounts: Account[];
                    currentPage: number;
                    lastPage: number;
                    total: number;
                };
            };
            const d = env.data;
            if (!d) return;
            if (reset) {
                submitted.value.items = d.posts;
                submitted.value.accounts = d.accounts;
            } else {
                submitted.value.items.push(...d.posts);
                submitted.value.accounts.push(...d.accounts);
            }
            submittedPage.value = d.currentPage;
            submitted.value.lastPage = d.lastPage;
            submittedTotal.value = d.total;
        },
        onError: () => toast.error('Could not load submitted posts.'),
    });
}

watch(tab, (t) => {
    if (t === 'submitted' && submittedTotal.value === null) loadSubmitted(true);
});

// Surface the result of an OAuth round-trip (callback redirects here with
// ?oauth_status / &oauth_platform / &oauth_message), then clean the URL.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('oauth_status');
    if (!status) return;

    const platform = cap(params.get('oauth_platform') ?? 'account');
    if (status === 'success') {
        toast.success(`${platform} connected successfully.`);
    } else {
        toast.error(
            params.get('oauth_message') || `Could not connect ${platform}.`,
        );
    }
    window.history.replaceState({}, '', '/app/dashboard');
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <!-- Greeting -->
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome back, {{ userName }} 👋
            </h1>
            <p class="text-sm text-muted-foreground">
                Here's an overview of your Notion databases, connected accounts
                and scheduled posts.
            </p>

            <div
                v-if="plan"
                class="mt-2 flex flex-wrap items-center gap-2 text-xs"
            >
                <span
                    class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 font-medium text-primary"
                >
                    {{ plan.details.name }} plan
                </span>
                <Link
                    href="/app/pricing"
                    class="font-medium text-primary hover:underline"
                    >Upgrade ↗</Link
                >
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 overflow-x-auto border-b border-border">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    tab === t.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="tab = t.key"
            >
                <component :is="t.icon" class="h-4 w-4 shrink-0" />
                {{ t.label }}
                <span
                    v-if="t.badge"
                    class="ml-0.5 rounded-full bg-muted px-1.5 py-0.5 text-xs"
                    >{{ t.badge }}</span
                >
            </button>
        </div>

        <!-- Databases -->
        <div v-if="tab === 'databases'" class="flex flex-col gap-3">
            <div class="flex justify-end">
                <AddDatabaseDialog @connected="onDatabaseConnected" />
            </div>
            <div
                v-if="!databases.length"
                class="rounded-xl border border-border p-8 text-center text-muted-foreground"
            >
                No Notion databases connected yet.
            </div>

            <div v-else class="grid gap-3 xl:grid-cols-2">
                <div
                    v-for="db in databases"
                    :key="db.id"
                    class="@container rounded-xl border border-border p-4"
                >
                    <!-- Header: name + status + actions -->
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{
                                    db.database_name ?? 'Untitled database'
                                }}</span>
                                <a
                                    :href="notionUrl(db.database_id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-medium text-primary hover:underline"
                                >
                                    Open in Notion ↗
                                </a>
                            </div>
                            <span
                                :class="[
                                    pill,
                                    truthy(db.is_valid)
                                        ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                                        : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
                                ]"
                            >
                                {{
                                    truthy(db.is_valid)
                                        ? 'Connected'
                                        : 'Needs reconnect'
                                }}
                            </span>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <ManageDatabaseSocialsDialog
                                :database-id="db.id"
                                :socials="socials"
                                @updated="onSocialsUpdated"
                            />
                            <Button
                                v-if="!truthy(db.is_valid)"
                                size="sm"
                                variant="outline"
                                :disabled="reconnectAction.processing"
                                @click="reconnectDatabase(db.id)"
                            >
                                <RefreshCw class="h-4 w-4" />
                                Reconnect
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="dbAction.processing"
                                @click="removeDatabase(db.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                                Remove
                            </Button>
                        </div>
                    </div>

                    <!-- Linked accounts — grouped by platform, avatar chips -->
                    <div class="mt-4 border-t border-border pt-3">
                        <div
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Linked accounts ({{ db.socials?.length ?? 0 }})
                        </div>
                        <p
                            v-if="!db.socials?.length"
                            class="text-sm text-muted-foreground"
                        >
                            No accounts linked yet.
                        </p>
                        <div v-else class="space-y-3">
                            <div
                                v-for="group in groupAccounts(db.socials)"
                                :key="group.platform"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    <SocialIcon
                                        :platform="group.platform"
                                        class="h-3.5 w-3.5 shrink-0"
                                    />
                                    {{ cap(group.platform) }}
                                    <span class="font-normal"
                                        >({{ group.accounts.length }})</span
                                    >
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="s in group.accounts"
                                        :key="s.id"
                                        class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 py-1 pr-3 pl-1 text-sm"
                                    >
                                        <span class="relative shrink-0">
                                            <img
                                                v-if="s.profile_picture"
                                                :src="s.profile_picture"
                                                alt=""
                                                class="h-6 w-6 rounded-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-muted"
                                            >
                                                <SocialIcon
                                                    :platform="s.platform"
                                                    class="h-3 w-3"
                                                />
                                            </span>
                                            <span
                                                class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-background"
                                                :class="
                                                    truthy(s.is_valid)
                                                        ? 'bg-green-500'
                                                        : 'bg-red-500'
                                                "
                                                :title="
                                                    truthy(s.is_valid)
                                                        ? 'Connected'
                                                        : 'Needs reconnecting'
                                                "
                                            ></span>
                                        </span>
                                        <span
                                            class="max-w-[12rem] truncate font-medium"
                                            >{{
                                                s.name ?? cap(s.platform)
                                            }}</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social accounts (card grid) -->
        <div v-else-if="tab === 'socials'" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div
                    v-if="socials.length"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <button
                        type="button"
                        :class="filterChipClass('all')"
                        @click="socialFilter = 'all'"
                    >
                        All ({{ socials.length }})
                    </button>
                    <button
                        v-for="p in socialPlatforms"
                        :key="p.platform"
                        type="button"
                        :class="filterChipClass(p.platform)"
                        @click="socialFilter = p.platform"
                    >
                        <SocialIcon
                            :platform="p.platform"
                            class="h-3.5 w-3.5"
                        />
                        {{ cap(p.platform) }} ({{ p.count }})
                    </button>
                </div>
                <span v-else></span>
                <AddSocialAccountDialog />
            </div>
            <div
                v-if="!socials.length"
                class="rounded-xl border border-border p-8 text-center text-muted-foreground"
            >
                No social accounts connected yet.
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="s in filteredSocials"
                    :key="s.id"
                    class="flex items-center gap-3 rounded-xl border border-border p-3"
                >
                    <div class="relative shrink-0">
                        <img
                            v-if="s.profile_picture"
                            :src="s.profile_picture"
                            alt=""
                            class="h-11 w-11 rounded-full object-cover"
                        />
                        <div
                            v-else
                            class="h-11 w-11 rounded-full bg-muted"
                        ></div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-background ring-1 ring-border"
                        >
                            <SocialIcon
                                :platform="s.platform"
                                class="h-3 w-3"
                            />
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate font-medium">{{
                                s.name ?? s.account_full_identifier ?? 'Account'
                            }}</span>
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="
                                    truthy(s.is_valid)
                                        ? 'bg-green-500'
                                        : 'bg-red-500'
                                "
                                :title="
                                    truthy(s.is_valid)
                                        ? 'Active'
                                        : 'Needs attention'
                                "
                            ></span>
                        </div>
                        <div
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ socialStats(s) || cap(s.platform) }}
                        </div>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        class="shrink-0"
                        :disabled="socialAction.processing"
                        @click="removeSocial(s.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Remove
                    </Button>
                </div>
            </div>
        </div>

        <!-- Scheduled posts -->
        <div
            v-else-if="tab === 'posts'"
            class="overflow-x-auto rounded-xl border border-border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Post</th>
                        <th class="px-4 py-2 font-medium">Account</th>
                        <th class="px-4 py-2 font-medium">Scheduled</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!posts.length">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No scheduled posts.
                        </td>
                    </tr>
                    <tr
                        v-for="p in posts"
                        :key="p.id"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-3 font-medium">
                            <div class="flex items-center gap-2">
                                <SocialIcon
                                    :platform="p.platform"
                                    class="h-4 w-4 shrink-0"
                                />
                                <a
                                    v-if="p.permalink"
                                    :href="p.permalink"
                                    target="_blank"
                                    rel="noopener"
                                    class="hover:underline"
                                >
                                    {{ p.post_name ?? 'Untitled post' }}
                                </a>
                                <span v-else>{{
                                    p.post_name ?? 'Untitled post'
                                }}</span>
                                <a
                                    v-if="p.post_page_id"
                                    :href="notionUrl(p.post_page_id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-medium text-primary hover:underline"
                                    >Notion ↗</a
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ accountName(p.account_id) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ fmtDate(p.scheduled_date) }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="[pill, postStatusClass(p)]">{{
                                truthy(p.in_flight)
                                    ? 'In flight'
                                    : cap(p.status)
                            }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="postAction.processing"
                                    @click="deletePost(p.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Remove
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Submitted posts -->
        <div v-else class="flex flex-col gap-3">
            <div class="overflow-x-auto rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Post</th>
                            <th class="px-4 py-2 font-medium">Account</th>
                            <th class="px-4 py-2 font-medium">Posted</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-if="
                                submittedHttp.processing &&
                                !submitted.items.length
                            "
                        >
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Loading…
                            </td>
                        </tr>
                        <tr v-else-if="!submitted.items.length">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No submitted posts yet.
                            </td>
                        </tr>
                        <tr
                            v-for="p in submitted.items"
                            :key="p.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-2">
                                    <SocialIcon
                                        :platform="p.platform"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    <a
                                        v-if="p.permalink"
                                        :href="p.permalink"
                                        target="_blank"
                                        rel="noopener"
                                        class="hover:underline"
                                    >
                                        {{ p.post_name ?? 'Untitled post' }}
                                    </a>
                                    <span v-else>{{
                                        p.post_name ?? 'Untitled post'
                                    }}</span>
                                    <a
                                        v-if="p.post_page_id"
                                        :href="notionUrl(p.post_page_id)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs font-medium text-primary hover:underline"
                                        >Notion ↗</a
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ submittedAccountName(p.account_id) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ fmtDate(p.posted_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        pill,
                                        'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
                                    ]"
                                    >Posted</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="submittedTotal !== null"
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <span
                    >Showing {{ submitted.items.length }} of
                    {{ submittedTotal }}</span
                >
                <Button
                    v-if="submittedPage < submitted.lastPage"
                    size="sm"
                    variant="outline"
                    :disabled="submittedHttp.processing"
                    @click="loadSubmitted(false)"
                >
                    <ChevronDown class="h-4 w-4" />
                    Load more
                </Button>
            </div>
        </div>
    </div>
</template>
