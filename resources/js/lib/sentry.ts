import * as Sentry from '@sentry/vue';
import type { App } from 'vue';

const env = import.meta.env as Record<string, string | undefined>;

/**
 * Front-end error tracking.
 *
 * No-op unless VITE_SENTRY_DSN is set, so local dev and any environment without
 * a DSN are completely unaffected. Works with Sentry's hosted free tier or a
 * self-hosted GlitchTip instance (GlitchTip speaks the Sentry protocol — point
 * the DSN at it and this exact code works).
 *
 * Wired via Inertia's `withApp(app)` hook so it captures, app-wide:
 *   - Vue component render/lifecycle errors (Vue's app.config.errorHandler)
 *   - uncaught errors                       (window.onerror)
 *   - unhandled promise rejections          (unhandledrejection)
 *
 * VITE_* vars are inlined at build time, so set the DSN before `npm run build`.
 */
export function initializeSentry(app: App): void {
    const dsn = env.VITE_SENTRY_DSN;
    if (!dsn) {
        return;
    }

    Sentry.init({
        app,
        dsn,
        environment: env.VITE_SENTRY_ENVIRONMENT ?? import.meta.env.MODE,
        // Errors-only by default (keeps you well within free-tier quotas).
        // Set 0..1 to also sample performance traces.
        tracesSampleRate: Number(env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0),
    });
}
