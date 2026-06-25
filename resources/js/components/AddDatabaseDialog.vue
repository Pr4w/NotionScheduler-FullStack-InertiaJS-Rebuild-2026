<script setup lang="ts">
import { ref, watch } from 'vue';
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
import { Plus } from '@lucide/vue';
import { toastFromEnvelope } from '@/lib/notionToast';
import { useOAuthConnect } from '@/composables/useOAuthConnect';

interface AvailableDatabase {
    id: string;
    title: string;
    url: string;
}

const emit = defineEmits<{ connected: [] }>();

const open = ref(false);
const selected = ref<string | null>(null);
const available = ref<AvailableDatabase[]>([]);
const scanMessage = ref<string | null>(null);

const { connect: connectNotion, connecting: notionConnecting } =
    useOAuthConnect();

const scan = useHttp({});
function runScan() {
    available.value = [];
    scanMessage.value = null;
    selected.value = null;
    scan.get('/app/databases/scanForNew', {
        onSuccess: (res: unknown) => {
            const env = res as {
                status?: string;
                data?: AvailableDatabase[];
                messages?: { message?: string }[];
            };
            if (env.status === 'OK' && Array.isArray(env.data)) {
                available.value = env.data;
            } else {
                scanMessage.value =
                    env.messages?.[0]?.message ??
                    'No new databases found in your Notion workspace.';
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

const connect = useHttp<{ database_id: string | null }>({ database_id: null });
function doConnect() {
    if (!selected.value) return;
    connect.database_id = selected.value;
    connect.post('/app/databases/buildScaffolding', {
        onSuccess: (res: unknown) => {
            toastFromEnvelope(res, 'Database connected.');
            if ((res as { status?: string }).status === 'OK') {
                open.value = false;
                emit('connected');
            }
        },
        onError: () => toast.error('Could not connect the database.'),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm"><Plus class="h-4 w-4" /> Add database</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Connect a Notion database</DialogTitle>
                <DialogDescription>
                    We'll scan your Notion workspace for databases you can
                    schedule posts from.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-[120px] py-2">
                <div
                    v-if="scan.processing"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner /> Scanning your Notion workspace…
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
                <div v-else-if="available.length" class="space-y-2">
                    <label
                        v-for="db in available"
                        :key="db.id"
                        class="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-muted/50"
                        :class="
                            selected === db.id
                                ? 'border-primary ring-1 ring-primary'
                                : 'border-border'
                        "
                    >
                        <input
                            v-model="selected"
                            type="radio"
                            name="db"
                            :value="db.id"
                            class="accent-primary"
                        />
                        <span class="font-medium">{{ db.title }}</span>
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
                    <Spinner v-if="connect.processing" /> Connect
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
