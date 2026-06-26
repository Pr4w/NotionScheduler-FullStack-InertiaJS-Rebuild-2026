import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { sentryVitePlugin } from '@sentry/vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    // loadEnv with an empty prefix also reads non-VITE_ vars (kept server-side,
    // never exposed to the browser) so the Sentry plugin can find its token.
    const env = loadEnv(mode, process.cwd(), '');
    const sentryEnabled = Boolean(
        env.SENTRY_AUTH_TOKEN && env.SENTRY_ORG && env.SENTRY_PROJECT,
    );

    return {
        // Sentry needs source maps to turn minified production stack traces back
        // into real file/line frames. Only emit them when we're actually going
        // to upload + delete them (see filesToDeleteAfterUpload below), so they
        // never ship to or stay in the public build otherwise.
        build: {
            sourcemap: sentryEnabled,
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/css/landing.css',
                    'resources/js/app.ts',
                    'resources/css/filament/admin/theme.css',
                ],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
            // Must come last. Only runs when the build-time Sentry creds are
            // present (so local/dev builds are untouched). Uploads source maps,
            // then deletes them from public/build so they aren't served.
            sentryEnabled &&
                sentryVitePlugin({
                    org: env.SENTRY_ORG,
                    project: env.SENTRY_PROJECT,
                    authToken: env.SENTRY_AUTH_TOKEN,
                    sourcemaps: {
                        filesToDeleteAfterUpload: ['./public/build/**/*.map'],
                    },
                }),
        ],
    };
});
