<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { ChevronDown, Info, Plus } from '@lucide/vue';
import SocialIcon from '@/components/SocialIcon.vue';
import { useOAuthConnect } from '@/composables/useOAuthConnect';

const props = withDefaults(
    defineProps<{ returnTo?: 'dashboard' | 'setup' }>(),
    {
        returnTo: 'dashboard',
    },
);

const open = ref(false);
const { connect, connecting } = useOAuthConnect();

// `slug` is the OAuth route; `icon` maps to the SocialIcon brand logo.
const platforms: { slug: string; icon: string; label: string }[] = [
    { slug: 'facebook', icon: 'facebook', label: 'Facebook & Instagram' },
    { slug: 'linkedin-pro', icon: 'linkedin', label: 'LinkedIn' },
    // { slug: 'twitter', icon: 'twitter', label: 'X (Twitter)' },
    { slug: 'tiktok', icon: 'tiktok', label: 'TikTok' },
    { slug: 'threads', icon: 'threads', label: 'Threads' },
    { slug: 'youtube', icon: 'youtube', label: 'YouTube' },
];
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm"><Plus class="h-4 w-4" /> Add account</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Connect a social account</DialogTitle>
                <DialogDescription>
                    Choose a platform to authorize. You'll be sent to the
                    provider to grant access, then returned here.
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-2">
                <button
                    v-for="p in platforms"
                    :key="p.slug"
                    type="button"
                    :disabled="connecting !== null"
                    class="flex items-center gap-3 rounded-lg border border-border p-3 text-left text-sm font-medium transition-colors hover:border-primary/40 hover:bg-muted disabled:opacity-50"
                    @click="connect(p.slug, props.returnTo)"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted"
                    >
                        <Spinner v-if="connecting === p.slug" class="h-4 w-4" />
                        <SocialIcon v-else :platform="p.icon" class="h-5 w-5" />
                    </span>
                    {{ p.label }}
                </button>
            </div>

            <!-- Meta only shares the pages/accounts you tick during OAuth, so
                 extra Facebook/Instagram accounts need re-authorizing in their
                 Business Integrations settings before they show up here. -->
            <details class="group rounded-lg border border-border bg-muted/30">
                <summary
                    class="flex cursor-pointer list-none items-center gap-2 p-3 text-sm font-medium select-none [&::-webkit-details-marker]:hidden"
                >
                    <Info class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span class="flex-1"
                        >Not all your Facebook / Instagram accounts showing
                        up?</span
                    >
                    <ChevronDown
                        class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                    />
                </summary>
                <div class="px-3 pb-3 text-sm text-muted-foreground">
                    Head to
                    <a
                        href="https://www.facebook.com/settings/?tab=business_tools"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-primary hover:underline"
                        >your Facebook business integrations</a
                    >, find
                    <span class="font-medium text-foreground"
                        >NotionScheduler</span
                    >
                    and hit
                    <span class="font-medium text-foreground">Edit</span>, then
                    add the pages and accounts you want it to manage. Once
                    saved, click
                    <span class="font-medium text-foreground"
                        >Facebook &amp; Instagram</span
                    >
                    above again and they'll come through.
                </div>
            </details>
        </DialogContent>
    </Dialog>
</template>
