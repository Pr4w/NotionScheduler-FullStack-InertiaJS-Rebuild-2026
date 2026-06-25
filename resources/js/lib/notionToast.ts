import { toast } from 'vue-sonner';

type ToastKind = 'success' | 'info' | 'warning' | 'error';

interface EnvelopeMessage {
    type?: string;
    message?: string;
}

interface Envelope {
    status?: string;
    messages?: EnvelopeMessage[];
}

const TYPE_MAP: Record<string, ToastKind> = {
    success: 'success',
    info: 'info',
    warning: 'warning',
    error: 'error',
    danger: 'error',
    fail: 'error',
};

/**
 * Surface the old JSON envelope ({ status, messages: [{ type, message }] })
 * returned by the ported controllers as vue-sonner toasts. When the envelope
 * carries no messages, fall back to a generic success/error based on status.
 */
export function toastFromEnvelope(response: unknown, fallbackSuccess?: string): void {
    const env = (response ?? {}) as Envelope;
    const messages = env.messages ?? [];

    if (messages.length) {
        for (const m of messages) {
            const kind = TYPE_MAP[(m.type ?? 'info').toLowerCase()] ?? 'info';
            toast[kind](m.message ?? '');
        }
        return;
    }

    if (env.status === 'OK') {
        if (fallbackSuccess) {
            toast.success(fallbackSuccess);
        }
    } else {
        toast.error('Something went wrong. Please try again.');
    }
}
