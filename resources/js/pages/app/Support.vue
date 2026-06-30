<script setup lang="ts">
import { ref } from 'vue';
import { Head, useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { CheckCircle2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { toastFromEnvelope } from '@/lib/notionToast';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Support', href: '/app/support' }],
    },
});

const form = useHttp<{ message: string }>({ message: '' });
const sent = ref(false);

function submit() {
    form.post('/app/user/support', {
        onSuccess: (res: unknown) => {
            // On success, swap the form for the confirmation panel instead of a
            // toast. Anything other than OK is a validation/business failure —
            // surface its message and keep the form so they can retry.
            if ((res as { status?: string }).status === 'OK') {
                sent.value = true;
                form.message = '';
                return;
            }
            toastFromEnvelope(res, '');
        },
        onError: () => toast.error('Could not send your message.'),
    });
}

function sendAnother() {
    sent.value = false;
}
</script>

<template>
    <Head title="Support" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <!-- Success confirmation — replaces the form once the email is sent -->
        <div
            v-if="sent"
            class="flex flex-col items-center gap-4 rounded-xl border border-green-300 bg-green-50 p-10 text-center dark:border-green-900 dark:bg-green-950"
        >
            <CheckCircle2 class="h-16 w-16 text-green-600 dark:text-green-400" />
            <h1
                class="text-xl font-semibold text-green-900 dark:text-green-200"
            >
                Message sent!
            </h1>
            <p class="max-w-md text-sm text-green-800 dark:text-green-300">
                Thanks for reaching out — we've received your message and we'll
                look into it as soon as possible. Keep an eye on your inbox for
                our reply.
            </p>
            <button
                type="button"
                class="text-sm font-medium text-green-700 hover:underline dark:text-green-400"
                @click="sendAnother"
            >
                Send another message
            </button>
        </div>

        <!-- Support form -->
        <template v-else>
            <div>
                <h1 class="text-xl font-semibold">Support</h1>
                <p class="text-sm text-muted-foreground">
                    Need help? Send us a message and we'll get back to you as
                    soon as possible.
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
                    <span
                        :class="form.message.length < 50 ? 'text-amber-600' : ''"
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
        </template>
    </div>
</template>
