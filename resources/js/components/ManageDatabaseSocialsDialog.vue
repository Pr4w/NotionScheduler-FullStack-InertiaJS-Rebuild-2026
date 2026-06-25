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
import { toastFromEnvelope } from '@/lib/notionToast';

interface SocialAccount {
    id: number;
    platform: string;
    name: string | null;
    database_id: number | null;
}

const props = defineProps<{
    databaseId: number;
    socials: SocialAccount[];
}>();

const emit = defineEmits<{ updated: [] }>();

const open = ref(false);
const selected = ref<number[]>([]);

const cap = (s: string): string =>
    s ? s.charAt(0).toUpperCase() + s.slice(1) : s;

watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = props.socials
            .filter((s) => s.database_id === props.databaseId)
            .map((s) => s.id);
    }
});

function toggle(id: number) {
    const i = selected.value.indexOf(id);
    if (i === -1) {
        selected.value.push(id);
    } else {
        selected.value.splice(i, 1);
    }
}

const save = useHttp<{ database_id: number; social_accounts: number[] }>({
    database_id: props.databaseId,
    social_accounts: [],
});
function doSave() {
    save.database_id = props.databaseId;
    save.social_accounts = selected.value;
    save.post('/app/databases/editSocials', {
        onSuccess: (res: unknown) => {
            toastFromEnvelope(res, 'Linked accounts updated.');
            if ((res as { status?: string }).status === 'OK') {
                open.value = false;
                emit('updated');
            }
        },
        onError: () => toast.error('Could not update linked accounts.'),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm" variant="outline">Manage accounts</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Linked social accounts</DialogTitle>
                <DialogDescription
                    >Choose which connected accounts publish from this
                    database.</DialogDescription
                >
            </DialogHeader>

            <div class="max-h-[300px] space-y-1 overflow-y-auto py-2">
                <p v-if="!socials.length" class="text-sm text-muted-foreground">
                    You have no connected social accounts yet.
                </p>
                <label
                    v-for="s in socials"
                    :key="s.id"
                    class="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted/50"
                >
                    <input
                        type="checkbox"
                        :checked="selected.includes(s.id)"
                        class="accent-primary"
                        @change="toggle(s.id)"
                    />
                    <span class="font-medium">{{ cap(s.platform) }}</span>
                    <span v-if="s.name" class="text-muted-foreground"
                        >· {{ s.name }}</span
                    >
                </label>
            </div>

            <DialogFooter>
                <Button
                    size="sm"
                    :disabled="save.processing || !socials.length"
                    @click="doSave"
                    >Save</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
