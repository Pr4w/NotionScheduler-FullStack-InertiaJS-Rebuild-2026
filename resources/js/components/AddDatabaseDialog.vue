<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { FileText, Plus } from '@lucide/vue';
import { toastFromEnvelope } from '@/lib/notionToast';
import { useOAuthConnect } from '@/composables/useOAuthConnect';

interface ScanItem {
    id: string;
    title: string;
    url?: string;
    icon?: string | null;
    icon_type?: string | null;
}

const emit = defineEmits<{ connected: [] }>();

// 'existing' = connect a database the user already built in Notion.
// 'page'     = create a fresh scheduler database inside a chosen Notion page.
type Mode = 'existing' | 'page';
const mode = ref<Mode>('existing');

const open = ref(false);
const selected = ref<string | null>(null);
const available = ref<ScanItem[]>([]);
const scanMessage = ref<string | null>(null);

const { connect: connectNotion, connecting: notionConnecting } =
    useOAuthConnect();

const scanUrl = computed(() =>
    mode.value === 'existing'
        ? '/app/databases/scanForNew'
        : '/app/pages/scanAll',
);
const emptyMessage = computed(() =>
    mode.value === 'existing'
        ? 'No new databases found in your Notion workspace.'
        : 'No pages found in your Notion workspace.',
);

const scan = useHttp({});
function runScan() {
    available.value = [];
    scanMessage.value = null;
    selected.value = null;
    scan.get(scanUrl.value, {
        onSuccess: (res: unknown) => {
            const env = res as {
                status?: string;
                data?: ScanItem[];
                messages?: { message?: string }[];
            };
            if (env.status === 'OK' && Array.isArray(env.data)) {
                available.value = env.data;
            } else {
                scanMessage.value =
                    env.messages?.[0]?.message ?? emptyMessage.value;
            }
        },
        onError: () => {
            scanMessage.value =
                'Could not reach your Notion workspace. Try reconnecting Notion.';
        },
    });
}

watch(open, (isOpen) => {
    if (isOpen) runScan();
});
watch(mode, () => {
    if (open.value) runScan();
});

const connect = useHttp<{
    database_id: string | null;
    page_id: string | null;
}>({ database_id: null, page_id: null });
function doConnect() {
    if (!selected.value) return;
    const url =
        mode.value === 'existing'
            ? '/app/databases/buildScaffolding'
            : '/app/pages/buildScaffolding';
    connect.database_id = mode.value === 'existing' ? selected.value : null;
    connect.page_id = mode.value === 'page' ? selected.value : null;
    connect.post(url, {
        onSuccess: (res: unknown) => {
            toastFromEnvelope(
                res,
                mode.value === 'existing'
                    ? 'Database connected.'
                    : 'Scheduler database created.',
            );
            if ((res as { status?: string }).status === 'OK') {
                open.value = false;
                emit('connected');
            }
        },
        onError: () => toast.error('Could not connect the database.'),
    });
}

const tabClass = (m: Mode): string =>
    'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ' +
    (mode.value === m
        ? 'bg-background text-foreground shadow-sm'
        : 'text-muted-foreground hover:text-foreground');
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm"><Plus class="h-4 w-4" /> Add database</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add a Notion database</DialogTitle>
                <DialogDescription>
                    {{
                        mode === 'existing'
                            ? 'Connect a database you’ve already set up in Notion.'
                            : 'Pick a Notion page — we’ll create a ready-to-use scheduler database inside it.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- Mode toggle -->
            <div class="flex gap-1 rounded-lg bg-muted p-1">
                <button
                    type="button"
                    :class="tabClass('existing')"
                    @click="mode = 'existing'"
                >
                    Existing database
                </button>
                <button
                    type="button"
                    :class="tabClass('page')"
                    @click="mode = 'page'"
                >
                    New from a page
                </button>
            </div>

            <div class="min-h-[120px] py-1">
                <div
                    v-if="scan.processing"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner />
                    {{
                        mode === 'existing'
                            ? 'Scanning your Notion workspace…'
                            : 'Looking for pages…'
                    }}
                </div>
                <div v-else-if="scanMessage" class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        {{ scanMessage }}
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="notionConnecting !== null"
                        @click="connectNotion('notion', 'dashboard')"
                    >
                        <Spinner v-if="notionConnecting === 'notion'" /> Connect
                        Notion
                    </Button>
                </div>
                <div
                    v-else-if="available.length"
                    class="max-h-[45vh] space-y-2 overflow-y-auto"
                >
                    <label
                        v-for="item in available"
                        :key="item.id"
                        class="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-muted/50"
                        :class="
                            selected === item.id
                                ? 'border-primary ring-1 ring-primary'
                                : 'border-border'
                        "
                    >
                        <input
                            v-model="selected"
                            type="radio"
                            name="db"
                            :value="item.id"
                            class="accent-primary"
                        />
                        <span
                            v-if="mode === 'page'"
                            class="flex h-5 w-5 shrink-0 items-center justify-center text-base"
                        >
                            <span v-if="item.icon_type === 'emoji'">{{
                                item.icon
                            }}</span>
                            <FileText
                                v-else
                                class="h-4 w-4 text-muted-foreground"
                            />
                        </span>
                        <span class="truncate font-medium">{{
                            item.title
                        }}</span>
                    </label>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="scan.processing"
                    @click="runScan"
                    >Rescan</Button
                >
                <Button
                    size="sm"
                    :disabled="!selected || connect.processing"
                    @click="doConnect"
                >
                    <Spinner v-if="connect.processing" />
                    {{ mode === 'existing' ? 'Connect' : 'Create database' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
