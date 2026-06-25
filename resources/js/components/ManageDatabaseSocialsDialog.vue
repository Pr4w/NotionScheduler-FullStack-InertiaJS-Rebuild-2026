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

// Group accounts by platform (each group's accounts sorted by name) so the
// dialog can show a small platform header before each block.
const groupedSocials = computed(() => {
    const sorted = [...props.socials].sort(
        (a, b) =>
            a.platform.localeCompare(b.platform) ||
            (a.name ?? '').localeCompare(b.name ?? ''),
    );
    const groups = new Map<string, SocialAccount[]>();
    for (const s of sorted) {
        if (!groups.has(s.platform)) groups.set(s.platform, []);
        groups.get(s.platform)!.push(s);
    }
    return [...groups.entries()].map(([platform, accounts]) => ({
        platform,
        accounts,
    }));
});

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

            <div class="max-h-[60vh] space-y-3 overflow-y-auto py-2">
                <p v-if="!socials.length" class="text-sm text-muted-foreground">
                    You have no connected social accounts yet.
                </p>
                <div
                    v-for="group in groupedSocials"
                    :key="group.platform"
                    class="space-y-1 pb-4"
                >
                    <div
                        class="flex items-center gap-1.5 px-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <SocialIcon
                            :platform="group.platform"
                            class="h-3.5 w-3.5"
                        />
                        {{ cap(group.platform) }}
                    </div>
                    <label
                        v-for="s in group.accounts"
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
                            <div
                                v-else
                                class="h-8 w-8 rounded-full bg-muted"
                            ></div>
                            <span
                                class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-background ring-1 ring-border"
                            >
                                <SocialIcon
                                    :platform="s.platform"
                                    class="h-2.5 w-2.5"
                                />
                            </span>
                        </div>
                        <span class="min-w-0 truncate font-medium">
                            {{ s.name ?? cap(s.platform) }}
                        </span>
                    </label>
                </div>
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
