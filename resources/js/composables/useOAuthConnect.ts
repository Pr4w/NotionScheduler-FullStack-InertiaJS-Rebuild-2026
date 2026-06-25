import { ref } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * Kicks off an OAuth connection. The /app/connect/{provider}/redirect endpoints
 * return the provider's authorize URL as JSON ({ data: url }); we then send the
 * browser there. After consent the provider returns to
 * /app/connect/{provider}/callback, which redirects back to /app/{return_to}
 * with ?oauth_status / &oauth_platform query params (surfaced as a toast).
 */
export function useOAuthConnect() {
    const connecting = ref<string | null>(null);
    const http = useHttp({});

    function connect(
        slug: string,
        returnTo: 'dashboard' | 'setup' = 'dashboard',
    ) {
        connecting.value = slug;
        http.get(`/app/connect/${slug}/redirect?return_to=${returnTo}`, {
            onSuccess: (res: unknown) => {
                const env = res as {
                    data?: string;
                    messages?: { message?: string }[];
                };
                if (
                    typeof env.data === 'string' &&
                    env.data.startsWith('http')
                ) {
                    window.location.href = env.data;
                    return;
                }
                toast.error(
                    env.messages?.[0]?.message ??
                        'Could not start the connection.',
                );
                connecting.value = null;
            },
            onError: () => {
                toast.error(
                    'Could not start the connection. Check the provider configuration.',
                );
                connecting.value = null;
            },
        });
    }

    return { connect, connecting };
}
