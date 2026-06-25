<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Link2 } from '@lucide/vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import SocialIcon from '@/components/SocialIcon.vue';
import { toastFromEnvelope } from '@/lib/notionToast';

interface SocialAccount {
    id: number;
    platform: string;
    name: string | null;
    profile_picture: string | null;
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

// Order accounts by platform, then name (the prop order is unsorted).
const sortedSocials = computed(() =>
    [...props.socials].sort(
        (a, b) =>
            a.platform.localeCompare(b.platform) ||
            (a.name ?? '').localeCompare(b.name ?? ''),
    ),
);

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
            <Button size="sm" variant="outline"
                ><Link2 class="h-4 w-4" /> Manage accounts</Button
            >
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Linked social accounts</DialogTitle>
                <DialogDescription
                    >Choose which connected accounts publish from this
                    database.</DialogDescription
                >
            </DialogHeader>

            <div class="max-h-[60vh] space-y-1 overflow-y-auto py-2">
                <p v-if="!socials.length" class="text-sm text-muted-foreground">
                    You have no connected social accounts yet.
                </p>
                <label
                    v-for="s in sortedSocials"
                    :key="s.id"
                    class="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted/50"
                >
                    <input
                        type="checkbox"
                        :checked="selected.includes(s.id)"
                        class="accent-primary"
                        @change="toggle(s.id)"
                    />
                    <div class="relative shrink-0">
                        <img
                            v-if="s.profile_picture"
                            :src="s.profile_picture"
                            alt=""
                            class="h-8 w-8 rounded-full object-cover"
                        />
                        <div v-else class="h-8 w-8 rounded-full bg-muted"></div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-background ring-1 ring-border"
                        >
                            <SocialIcon
                                :platform="s.platform"
                                class="h-2.5 w-2.5"
                            />
                        </span>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate font-medium">
                            {{ s.name ?? cap(s.platform) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ cap(s.platform) }}
                        </div>
                    </div>
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
