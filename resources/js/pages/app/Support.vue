<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { toastFromEnvelope } from '@/lib/notionToast';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Support', href: '/app/support' }],
    },
});

const form = useHttp<{ message: string }>({ message: '' });

function submit() {
    form.post('/app/user/support', {
        onSuccess: (res: unknown) => {
            toastFromEnvelope(
                res,
                'Message sent — we’ll get back to you ASAP.',
            );
            if ((res as { status?: string }).status === 'OK') {
                form.message = '';
            }
        },
        onError: () => toast.error('Could not send your message.'),
    });
}
</script>

<template>
    <Head title="Support" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Support</h1>
            <p class="text-sm text-muted-foreground">
                Need help? Send us a message and we'll get back to you as soon
                as possible.
            </p>
        </div>

        <div class="rounded-xl border border-border p-4">
            <label for="message" class="text-sm font-medium"
                >Your message</label
            >
            <textarea
                id="message"
                v-model="form.message"
                rows="8"
                minlength="50"
                maxlength="1000"
                placeholder="Describe what you need help with (at least 50 characters)…"
                class="mt-2 w-full resize-y rounded-md border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
            />
            <div
                class="mt-2 flex items-center justify-between text-xs text-muted-foreground"
            >
                <span :class="form.message.length < 50 ? 'text-amber-600' : ''"
                    >Minimum 50 characters</span
                >
                <span>{{ form.message.length }}/1000</span>
            </div>

            <div class="mt-4 flex justify-end">
                <Button
                    :disabled="form.processing || form.message.length < 50"
                    @click="submit"
                >
                    Send message
                </Button>
            </div>
        </div>
    </div>
</template>
