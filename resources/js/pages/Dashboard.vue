<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from 'vue';
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    AtSign,
    Bookmark,
    CalendarClock,
    ChevronDown,
    Database,
    Eye,
    Heart,
    MessageCircle,
    RefreshCw,
    Send,
    Share2,
    Sparkles,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import AddDatabaseDialog from '@/components/AddDatabaseDialog.vue';
import ManageDatabaseSocialsDialog from '@/components/ManageDatabaseSocialsDialog.vue';
import AddSocialAccountDialog from '@/components/AddSocialAccountDialog.vue';
import SocialIcon from '@/components/SocialIcon.vue';
import { dashboard } from '@/routes';
import { toastFromEnvelope } from '@/lib/notionToast';

/**
 * Single accent = blue-600, spelled out literally everywhere (Tailwind's JIT
 * can't see `bg-${x}-600`). To re-theme, find/replace `blue-600` and its
 * `blue-600/10` tints, or map them to your own token.
 *
 * Layout: a full-height muted canvas (`bg-muted/40`) with a centred, max-w-7xl
 * content column so it never goes full-bleed on a wide monitor. Everything
 * lives in one card — tabs in the header, data as tables in the body.
 *
 * Plan limits (x/y) live in the header next to the plan badge, not in the tabs
 * (two tabs have no cap, so tab fractions would be inconsistent). Linked
 * accounts render as a vertical list; each avatar carries a platform badge so
 * identical avatars across networks stay distinguishable.
 */

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

interface PostMetrics {
    views: number | null;
    likes: number | null;
    comments: number | null;
    shares: number | null;
    saves: number | null;
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
    latest_metrics?: PostMetrics | null;
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

// Time-aware greeting for the welcome header.
const greeting = computed<string>(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
});

// A friendly one-line summary that replaces the old KPI cards.
const summary = computed<string>(() => {
    const dbs = props.databases.length;
    const accts = props.socials.length;
    const sched = props.posts.length;
    const s = (n: number) => (n === 1 ? '' : 's');
    return `You have ${sched} post${s(sched)} scheduled across ${dbs} database${s(dbs)} and ${accts} connected account${s(accts)}.`;
});

// Plan usage vs. limits, surfaced next to the plan badge in the header.
const limits = computed(() =>
    plan.value
        ? {
              databases: {
                  used: props.databases.length,
                  total: plan.value.options.databases,
              },
              accounts: {
                  used: props.socials.length,
                  total: plan.value.options.social_accounts,
              },
          }
        : null,
);
const atLimit = (used: number, total: number): boolean =>
    total > 0 && used >= total;

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
        badge: String(props.databases.length),
    },
    {
        key: 'socials' as const,
        label: 'Accounts',
        icon: AtSign,
        badge: String(props.socials.length),
    },
    {
        key: 'posts' as const,
        label: 'Scheduled',
        icon: CalendarClock,
        badge: String(props.posts.length),
    },
    {
        key: 'submitted' as const,
        label: 'Published',
        icon: Send,
        badge:
            submittedTotal.value !== null ? String(submittedTotal.value) : null,
    },
]);

const truthy = (v: number | boolean | null | undefined): boolean =>
    v === 1 || v === true;

// Accounts whose token has gone invalid — they won't publish until reconnected.
const invalidAccounts = computed(() =>
    props.socials.filter((s) => !truthy(s.is_valid)),
);
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

// Engagement metrics for a submitted post — only the ones the platform actually
// reported (skip null/0), each as an icon + count. Empty array → no metrics yet.
function engagementItems(
    m: PostMetrics | null | undefined,
): { icon: Component; value: number; label: string }[] {
    if (!m) return [];
    const defs: { key: keyof PostMetrics; icon: Component; label: string }[] = [
        { key: 'views', icon: Eye, label: 'views' },
        { key: 'likes', icon: Heart, label: 'likes' },
        { key: 'comments', icon: MessageCircle, label: 'comments' },
        { key: 'shares', icon: Share2, label: 'shares' },
        { key: 'saves', icon: Bookmark, label: 'saves' },
    ];
    return defs
        .filter((d) => typeof m[d.key] === 'number' && (m[d.key] as number) > 0)
        .map((d) => ({
            icon: d.icon,
            value: m[d.key] as number,
            label: d.label,
        }));
}

// Only surface followers/posts when we actually have the data.
function socialStats(s: SocialAccount): string {
    const parts: string[] = [];
    if (typeof s.followers === 'number' && s.followers > 0)
        parts.push(`${fmtNum(s.followers)} followers`);
    if (typeof s.post_count === 'number' && s.post_count > 0)
        parts.push(`${s.post_count} posts`);
    return parts.join(' · ');
}

// Stable ordering for accounts: by platform, then by name — so same-network
// accounts cluster together in the linked-accounts list.
const socialSort = (a: SocialAccount, b: SocialAccount): number =>
    a.platform.localeCompare(b.platform) ||
    (a.name ?? '').localeCompare(b.name ?? '');
const sortAccounts = (list: SocialAccount[]): SocialAccount[] =>
    [...list].sort(socialSort);

// Compact per-platform counts (used by the account filter).
function platformSummary(
    list: SocialAccount[],
): { platform: string; count: number }[] {
    const map = new Map<string, number>();
    for (const s of list) map.set(s.platform, (map.get(s.platform) ?? 0) + 1);
    return [...map.entries()]
        .map(([platform, count]) => ({ platform, count }))
        .sort((a, b) => a.platform.localeCompare(b.platform));
}

// Accounts tab: platform filter + ordering.
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
        ? 'bg-blue-600 text-white'
        : 'bg-muted text-muted-foreground hover:text-foreground');

function postStatusClass(post: Post): string {
    if (truthy(post.in_flight))
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    const s = (post.status ?? '').toLowerCase();
    if (['error', 'failed'].includes(s))
        return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
    if (s === 'posted')
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
}

const pill =
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium';
const th =
    'px-4 py-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:px-5';
const td = 'px-4 py-3 sm:px-5';

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

// Pre-warm the submitted total so the Published tab badge shows a number on load.
onMounted(() => loadSubmitted(true));

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

    <!-- Full-height muted canvas -->
    <div class="flex h-full flex-1 flex-col bg-muted/40">
        <!-- Contained, centred content column -->
        <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
            <!-- Welcome header -->
            <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight sm:text-2xl"
                    >
                        {{ greeting }}, {{ userName }} 👋
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ summary }}
                    </p>
                </div>
                <div
                    v-if="plan"
                    class="flex flex-col items-start gap-1.5 text-xs sm:items-end"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-blue-600/10 px-3 py-1.5 font-semibold text-blue-600 dark:text-blue-400"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ plan.details.name }} plan
                        </span>
                        <Link
                            href="/app/pricing"
                            class="inline-flex items-center rounded-full border border-border bg-card px-3 py-1.5 font-medium transition-colors hover:bg-muted"
                            >Upgrade ↗</Link
                        >
                    </div>
                    <div
                        v-if="limits"
                        class="flex items-center gap-1.5 text-muted-foreground"
                    >
                        <span
                            :class="
                                atLimit(
                                    limits.databases.used,
                                    limits.databases.total,
                                )
                                    ? 'font-medium text-amber-600 dark:text-amber-400'
                                    : ''
                            "
                            >{{ limits.databases.used }}/{{
                                limits.databases.total
                            }}
                            databases</span
                        >
                        <span class="text-muted-foreground/40">·</span>
                        <span
                            :class="
                                atLimit(
                                    limits.accounts.used,
                                    limits.accounts.total,
                                )
                                    ? 'font-medium text-amber-600 dark:text-amber-400'
                                    : ''
                            "
                            >{{ limits.accounts.used }}/{{
                                limits.accounts.total
                            }}
                            accounts</span
                        >
                    </div>
                </div>
            </header>

            <!-- Invalid-account notice — persistent while any account is invalid -->
            <div
                v-if="invalidAccounts.length"
                class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200"
            >
                <TriangleAlert
                    class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <span class="flex-1">
                    <strong>{{ invalidAccounts.length }}</strong>
                    {{
                        invalidAccounts.length === 1
                            ? 'account needs'
                            : 'accounts need'
                    }}
                    reconnecting — they won't publish until you do.
                </span>
                <button
                    type="button"
                    class="shrink-0 rounded-full bg-amber-600 px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-amber-700"
                    @click="tab = 'socials'"
                >
                    Review accounts
                </button>
            </div>

            <!-- Main card -->
            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <!-- Card header: tabs + per-tab primary action -->
                <div
                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-border px-4 sm:px-5"
                >
                    <nav class="-mb-px flex gap-4 overflow-x-auto sm:gap-6">
                        <button
                            v-for="t in tabs"
                            :key="t.key"
                            type="button"
                            class="inline-flex items-center gap-2 border-b-2 py-3.5 text-sm whitespace-nowrap transition-colors"
                            :class="
                                tab === t.key
                                    ? 'border-blue-600 font-semibold text-blue-600 dark:text-blue-400'
                                    : 'border-transparent font-medium text-muted-foreground hover:text-foreground'
                            "
                            @click="tab = t.key"
                        >
                            <component :is="t.icon" class="h-4 w-4 shrink-0" />
                            {{ t.label }}
                            <span
                                v-if="t.badge"
                                class="rounded-full px-1.5 py-0.5 text-xs"
                                :class="
                                    tab === t.key
                                        ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{ t.badge }}</span
                            >
                        </button>
                    </nav>
                    <div class="flex items-center py-2">
                        <AddDatabaseDialog
                            v-if="tab === 'databases'"
                            @connected="onDatabaseConnected"
                        />
                        <AddSocialAccountDialog v-else-if="tab === 'socials'" />
                    </div>
                </div>

                <!-- Databases -->
                <div v-if="tab === 'databases'">
                    <div
                        v-if="!databases.length"
                        class="flex flex-col items-center gap-2 p-12 text-center"
                    >
                        <Database class="h-8 w-8 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">
                            No Notion databases connected yet.
                        </p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th :class="th">Database</th>
                                    <th :class="th">Linked accounts</th>
                                    <th :class="th">Status</th>
                                    <th :class="[th, 'text-right']">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="db in databases"
                                    :key="db.id"
                                    class="border-t border-border align-top transition-colors hover:bg-muted/30"
                                >
                                    <td :class="td">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600/10 text-blue-600 dark:text-blue-400"
                                            >
                                                <Database class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <div
                                                    class="truncate font-medium"
                                                >
                                                    {{
                                                        db.database_name ??
                                                        'Untitled database'
                                                    }}
                                                </div>
                                                <a
                                                    :href="
                                                        notionUrl(
                                                            db.database_id,
                                                        )
                                                    "
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                                    >Open in Notion ↗</a
                                                >
                                            </div>
                                        </div>
                                    </td>
                                    <td :class="td">
                                        <ul
                                            v-if="db.socials?.length"
                                            class="space-y-1.5"
                                        >
                                            <li
                                                v-for="s in sortAccounts(
                                                    db.socials,
                                                )"
                                                :key="s.id"
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="relative shrink-0"
                                                    :title="`${s.name ?? cap(s.platform)} · ${cap(s.platform)}`"
                                                >
                                                    <img
                                                        v-if="s.profile_picture"
                                                        :src="s.profile_picture"
                                                        alt=""
                                                        class="h-6 w-6 rounded-full object-cover"
                                                    />
                                                    <span
                                                        v-else
                                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600/10 text-blue-600 dark:text-blue-400"
                                                    >
                                                        <SocialIcon
                                                            :platform="
                                                                s.platform
                                                            "
                                                            class="h-3 w-3"
                                                        />
                                                    </span>
                                                    <span
                                                        class="absolute -right-1 -bottom-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-card text-muted-foreground ring-1 ring-border"
                                                    >
                                                        <SocialIcon
                                                            :platform="
                                                                s.platform
                                                            "
                                                            class="h-2 w-2"
                                                        />
                                                    </span>
                                                </span>
                                                <span
                                                    class="truncate text-sm"
                                                    >{{
                                                        s.name ??
                                                        cap(s.platform)
                                                    }}</span
                                                >
                                                <span
                                                    v-if="!truthy(s.is_valid)"
                                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"
                                                    title="Needs reconnecting"
                                                ></span>
                                            </li>
                                        </ul>
                                        <span
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            >No accounts linked</span
                                        >
                                    </td>
                                    <td :class="td">
                                        <span
                                            :class="[
                                                pill,
                                                truthy(db.is_valid)
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                                            ]"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    truthy(db.is_valid)
                                                        ? 'bg-emerald-500'
                                                        : 'bg-amber-500'
                                                "
                                            ></span>
                                            {{
                                                truthy(db.is_valid)
                                                    ? 'Connected'
                                                    : 'Needs reconnect'
                                            }}
                                        </span>
                                    </td>
                                    <td :class="td">
                                        <div
                                            class="flex flex-wrap items-center justify-end gap-2"
                                        >
                                            <ManageDatabaseSocialsDialog
                                                :database-id="db.id"
                                                :socials="socials"
                                                @updated="onSocialsUpdated"
                                            />
                                            <Button
                                                v-if="!truthy(db.is_valid)"
                                                size="sm"
                                                variant="outline"
                                                :disabled="
                                                    reconnectAction.processing
                                                "
                                                @click="
                                                    reconnectDatabase(db.id)
                                                "
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-if="databases.length"
                        class="border-t border-border px-4 py-3 text-sm text-muted-foreground sm:px-5"
                    >
                        {{ databases.length }} database{{
                            databases.length === 1 ? '' : 's'
                        }}
                    </div>
                </div>

                <!-- Accounts -->
                <div v-else-if="tab === 'socials'">
                    <div
                        v-if="socials.length"
                        class="flex flex-wrap items-center gap-1.5 border-b border-border px-4 py-3 sm:px-5"
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
                    <div
                        v-if="!socials.length"
                        class="flex flex-col items-center gap-2 p-12 text-center"
                    >
                        <AtSign class="h-8 w-8 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">
                            No social accounts connected yet.
                        </p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th :class="th">Account</th>
                                    <th :class="th">Platform</th>
                                    <th :class="th">Audience</th>
                                    <th :class="th">Status</th>
                                    <th :class="[th, 'text-right']">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in filteredSocials"
                                    :key="s.id"
                                    class="border-t border-border transition-colors hover:bg-muted/30"
                                >
                                    <td :class="td">
                                        <div class="flex items-center gap-3">
                                            <div class="relative shrink-0">
                                                <img
                                                    v-if="s.profile_picture"
                                                    :src="s.profile_picture"
                                                    alt=""
                                                    class="h-9 w-9 rounded-full object-cover"
                                                />
                                                <span
                                                    v-else
                                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600/10 text-blue-600 dark:text-blue-400"
                                                >
                                                    <SocialIcon
                                                        :platform="s.platform"
                                                        class="h-4 w-4"
                                                    />
                                                </span>
                                            </div>
                                            <div class="min-w-0">
                                                <div
                                                    class="truncate font-medium"
                                                >
                                                    {{
                                                        s.name ??
                                                        s.account_full_identifier ??
                                                        'Account'
                                                    }}
                                                </div>
                                                <div
                                                    v-if="
                                                        s.account_full_identifier &&
                                                        s.name
                                                    "
                                                    class="truncate text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        s.account_full_identifier
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td :class="td">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <SocialIcon
                                                :platform="s.platform"
                                                class="h-4 w-4"
                                            />
                                            {{ cap(s.platform) }}
                                        </span>
                                    </td>
                                    <td :class="[td, 'text-muted-foreground']">
                                        {{ socialStats(s) || '—' }}
                                    </td>
                                    <td :class="td">
                                        <span
                                            :class="[
                                                pill,
                                                truthy(s.is_valid)
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
                                            ]"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    truthy(s.is_valid)
                                                        ? 'bg-emerald-500'
                                                        : 'bg-red-500'
                                                "
                                            ></span>
                                            {{
                                                truthy(s.is_valid)
                                                    ? 'Active'
                                                    : 'Needs attention'
                                            }}
                                        </span>
                                    </td>
                                    <td :class="td">
                                        <div class="flex justify-end">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                :disabled="
                                                    socialAction.processing
                                                "
                                                @click="removeSocial(s.id)"
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
                    <div
                        v-if="socials.length"
                        class="border-t border-border px-4 py-3 text-sm text-muted-foreground sm:px-5"
                    >
                        {{ filteredSocials.length }} account{{
                            filteredSocials.length === 1 ? '' : 's'
                        }}
                    </div>
                </div>

                <!-- Scheduled posts -->
                <div v-else-if="tab === 'posts'">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th :class="th">Post</th>
                                    <th :class="th">Account</th>
                                    <th :class="th">Scheduled</th>
                                    <th :class="th">Status</th>
                                    <th :class="[th, 'text-right']">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!posts.length">
                                    <td
                                        colspan="5"
                                        class="px-5 py-12 text-center text-muted-foreground"
                                    >
                                        No scheduled posts.
                                    </td>
                                </tr>
                                <tr
                                    v-for="p in posts"
                                    :key="p.id"
                                    class="border-t border-border transition-colors hover:bg-muted/30"
                                >
                                    <td :class="[td, 'font-medium']">
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
                                                {{
                                                    p.post_name ??
                                                    'Untitled post'
                                                }}
                                            </a>
                                            <span v-else>{{
                                                p.post_name ?? 'Untitled post'
                                            }}</span>
                                            <a
                                                v-if="p.post_page_id"
                                                :href="
                                                    notionUrl(p.post_page_id)
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                                >Notion ↗</a
                                            >
                                        </div>
                                    </td>
                                    <td :class="td">
                                        {{ accountName(p.account_id) }}
                                    </td>
                                    <td :class="[td, 'whitespace-nowrap']">
                                        {{ fmtDate(p.scheduled_date) }}
                                    </td>
                                    <td :class="td">
                                        <span
                                            :class="[pill, postStatusClass(p)]"
                                            >{{
                                                truthy(p.in_flight)
                                                    ? 'In flight'
                                                    : cap(p.status)
                                            }}</span
                                        >
                                    </td>
                                    <td :class="td">
                                        <div class="flex justify-end">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                :disabled="
                                                    postAction.processing
                                                "
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
                    <div
                        v-if="posts.length"
                        class="border-t border-border px-4 py-3 text-sm text-muted-foreground sm:px-5"
                    >
                        {{ posts.length }} scheduled post{{
                            posts.length === 1 ? '' : 's'
                        }}
                    </div>
                </div>

                <!-- Published posts -->
                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th :class="th">Post</th>
                                    <th :class="th">Account</th>
                                    <th :class="th">Posted</th>
                                    <th :class="th">Engagement</th>
                                    <th :class="th">Status</th>
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
                                        colspan="5"
                                        class="px-5 py-12 text-center text-muted-foreground"
                                    >
                                        Loading…
                                    </td>
                                </tr>
                                <tr v-else-if="!submitted.items.length">
                                    <td
                                        colspan="5"
                                        class="px-5 py-12 text-center text-muted-foreground"
                                    >
                                        No submitted posts yet.
                                    </td>
                                </tr>
                                <tr
                                    v-for="p in submitted.items"
                                    :key="p.id"
                                    class="border-t border-border transition-colors hover:bg-muted/30"
                                >
                                    <td :class="[td, 'font-medium']">
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
                                                {{
                                                    p.post_name ??
                                                    'Untitled post'
                                                }}
                                            </a>
                                            <span v-else>{{
                                                p.post_name ?? 'Untitled post'
                                            }}</span>
                                            <a
                                                v-if="p.post_page_id"
                                                :href="
                                                    notionUrl(p.post_page_id)
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                                >Notion ↗</a
                                            >
                                        </div>
                                    </td>
                                    <td :class="td">
                                        {{ submittedAccountName(p.account_id) }}
                                    </td>
                                    <td :class="[td, 'whitespace-nowrap']">
                                        {{ fmtDate(p.posted_date) }}
                                    </td>
                                    <td :class="td">
                                        <div
                                            v-if="
                                                engagementItems(
                                                    p.latest_metrics,
                                                ).length
                                            "
                                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                                        >
                                            <span
                                                v-for="e in engagementItems(
                                                    p.latest_metrics,
                                                )"
                                                :key="e.label"
                                                class="inline-flex items-center gap-1"
                                                :title="e.label"
                                            >
                                                <component
                                                    :is="e.icon"
                                                    class="h-3.5 w-3.5 text-muted-foreground"
                                                />
                                                {{ fmtNum(e.value) }}
                                            </span>
                                        </div>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td :class="td">
                                        <span
                                            :class="[
                                                pill,
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
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
                        class="flex items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm text-muted-foreground sm:px-5"
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
        </div>
    </div>
</template>
