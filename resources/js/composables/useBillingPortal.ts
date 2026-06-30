import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * Opens Stripe's billing portal (manage subscription / update card / cancel).
 * The endpoint returns the envelope { status, data: { url } }, or a friendly
 * FAIL message (e.g. "not a customer yet") which we surface as a toast.
 */
export function useBillingPortal() {
    const http = useHttp({});

    function openBillingPortal() {
        http.get('/app/user/billing/portal', {
            onSuccess: (res: unknown) => {
                const env = res as {
                    status?: string;
                    data?: { url?: string };
                    messages?: { message?: string }[];
                };
                if (env.status === 'OK' && env.data?.url) {
                    window.location.href = env.data.url;
                    return;
                }
                toast.error(
                    env.messages?.[0]?.message ??
                        'Could not open the billing portal.',
                );
            },
            onError: () => toast.error('Could not open the billing portal.'),
        });
    }

    return { openBillingPortal, http };
}
