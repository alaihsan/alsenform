import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import BrandFooter from './components/BrandFooter.vue';
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

// The page guard of resources/views/app.blade.php: it shows an error code instead of a blank page.
declare global {
    interface Window {
        FalconPageGuard?: { booted: () => void; failed: (code: string, message: string) => void };
    }
}

// The product name is the same everywhere, whatever APP_NAME says.
const appName = BRAND_NAME;

/** Whether nothing of the page is on screen: only the footer and the connection notice, or nothing. */
const isPageEmpty = (el: Element): boolean =>
    Array.from(el.children).every(
        (child) =>
            child.matches('[data-testid="brand-footer"], [role="alert"]') ||
            (!child.textContent?.trim() && !child.querySelector('img, svg, video, input')),
    );

/**
 * A page whose code is lost to the network once the app runs (Wi-Fi dropping as a link is clicked)
 * is opened with a full page load, instead of the click doing nothing. A link opens the page asked
 * for; a sent form (already handled by the server) or the back button loads the address shown again.
 * The first page is the page guard's (resources/views/partials/page-guard.blade.php).
 */
let isAppMounted = false;
let pendingVisit: { url: string; isGet: boolean } | null = null;
router.on('start', (event) => {
    pendingVisit = { url: event.detail.visit.url.href, isGet: event.detail.visit.method === 'get' };
});
router.on('finish', () => {
    pendingVisit = null;
});

const resolvePage = (name: string): Promise<DefineComponent> =>
    resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')).catch((error: unknown) => {
        if (!isAppMounted) {
            throw error;
        }
        if (pendingVisit?.isGet) {
            window.location.assign(pendingVisit.url);
        } else {
            window.location.reload();
        }

        return new Promise<DefineComponent>(() => {});
    });

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePage,
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => [h(App, props), h(BrandFooter), h(ConnectionLostNotice)] })
            .use(plugin)
            .use(ZiggyVue);

        // An error that leaves the page empty is shown with its code (FF-302), never as a blank page.
        app.config.errorHandler = (error, _instance, info) => {
            console.error(error);
            setTimeout(() => {
                if (isPageEmpty(el)) {
                    window.FalconPageGuard?.failed('FF-302', `${error instanceof Error ? error.message : String(error)} (${info})`);
                }
            });
        };

        app.mount(el);
        isAppMounted = true;
        window.FalconPageGuard?.booted();
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
