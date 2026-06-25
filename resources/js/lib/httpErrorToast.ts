import { http } from '@inertiajs/vue3';
import type { HttpResponseError } from '@inertiajs/core';
import { toast } from 'vue-sonner';

/**
 * Global safety net for useHttp (XHR/JSON) requests.
 *
 * useHttp splits failure handling: a per-request `onError` only receives 422
 * *validation* errors, while genuine server failures go to `onHttpException` /
 * `onNetworkError` — and if those aren't provided, the promise just rejects.
 * Most of our callers only wire up `onError`, so an unhandled 500 or a dropped
 * connection would re-enable the button and show nothing (cue the rage-clicks).
 *
 * Registering one global `http.onError` handler gives every useHttp call a
 * baseline toast for the *unexpected* failures, while leaving 4xx (validation,
 * plus our own 200-envelope `{status:'FAIL'}` responses) to per-request code.
 */
export function initializeHttpErrorToast(): void {
    http.onError((error) => {
        switch (error.code) {
            // Caller aborted (component unmount, superseded request) — not a
            // failure worth surfacing.
            case 'ERR_CANCELLED':
                return;

            // Request never reached the server (offline, DNS, CORS…).
            case 'ERR_NETWORK':
                toast.error(
                    'Network error — check your connection and try again.',
                );
                return;

            // Server replied with a non-2xx status.
            case 'ERR_HTTP_RESPONSE': {
                const status = (error as HttpResponseError).response?.status;

                // Expired CSRF / session token.
                if (status === 419) {
                    toast.error(
                        'Your session expired. Please refresh the page and try again.',
                    );
                    return;
                }

                // 4xx is either validation (handled per-request) or carried in
                // our own envelope — only shout about server-side failures.
                if (status && status < 500) return;

                toast.error(
                    'Something went wrong on our end. Please try again in a moment.',
                );
                return;
            }
        }
    });
}
