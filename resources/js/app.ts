import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeHttpErrorToast } from '@/lib/httpErrorToast';
import { initializeSentry } from '@/lib/sentry';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    // Inertia v3 auto-mode hook: receives the Vue app instance before mount,
    // so Sentry can capture Vue component errors as well as uncaught/global ones.
    withApp(app) {
        initializeSentry(app);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Global safety net: toast on unexpected useHttp failures (5xx / network /
// expired session) so a failed action never silently re-enables its button.
initializeHttpErrorToast();
