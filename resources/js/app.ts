import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ConnectionLostNotice from './components/ConnectionLostNotice.vue';
import { initializeTheme } from './composables/useAppearance';
import { BRAND_NAME } from './lib/brand';
import { setupDeviceHints } from './lib/deviceHints';
import { setupRequestTimeouts } from './lib/requestTimeouts';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

// The product name is the same everywhere, whatever APP_NAME says.
const appName = BRAND_NAME;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => [h(App, props), h(ConnectionLostNotice)] })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// The device each request comes from, for the activity report (see lib/deviceHints.ts).
setupDeviceHints();

// A page visit that stalls on weak Wi-Fi fails after a while and is tried again, instead of loading forever.
setupRequestTimeouts();
