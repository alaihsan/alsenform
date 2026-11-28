import axios from 'axios';
import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';

/**
 * Alamat online: the address of the server on the internet (a Cloudflare quick tunnel), for admins
 * and teachers. The tunnel gets a new address whenever it starts again, so every open page asks
 * for it again every half minute, and as soon as the page is looked at again: the new address shows
 * by itself. One question at a time for the whole page, however many places show it.
 */

/** How often an open page asks for the address again. */
export const ONLINE_ADDRESS_REFRESH_MS = 30_000;

/** A page opened right after another one uses the address just asked for. */
const FRESH_FOR_MS = 10_000;

const configured = ref(false);
const url = ref<string | null>(null);
const hasLoaded = ref(false);

let users = 0;
let timer: ReturnType<typeof setInterval> | null = null;
let pending: Promise<void> | null = null;
let fetchedAt = 0;

const refresh = (): Promise<void> => {
    if (pending) {
        return pending;
    }

    pending = axios
        .get(route('online-address'), { timeout: 5_000 })
        .then(({ data }) => {
            configured.value = Boolean(data?.configured);
            url.value = typeof data?.url === 'string' && data.url.startsWith('https://') ? data.url : null;
            hasLoaded.value = true;
            fetchedAt = Date.now();
        })
        .catch(() => {
            // The last address known stays: a moment without Wi-Fi is not a stopped tunnel.
        })
        .finally(() => {
            pending = null;
        });

    return pending;
};

const refreshIfStale = () => {
    if (document.visibilityState === 'visible' && Date.now() - fetchedAt >= FRESH_FOR_MS) {
        void refresh();
    }
};

const refreshOnTimer = () => {
    if (document.visibilityState === 'visible') {
        void refresh();
    }
};

/**
 * @param isEnabled Whether this page may ask (admins and teachers): a student's page never does.
 */
export function useOnlineAddress(isEnabled: () => boolean = () => true) {
    let isAsking = false;

    onMounted(() => {
        if (!isEnabled()) {
            return;
        }
        isAsking = true;
        users++;
        if (users > 1) {
            return;
        }
        refreshIfStale();
        timer = setInterval(refreshOnTimer, ONLINE_ADDRESS_REFRESH_MS);
        document.addEventListener('visibilitychange', refreshIfStale);
        window.addEventListener('focus', refreshIfStale);
    });

    onBeforeUnmount(() => {
        if (!isAsking) {
            return;
        }
        users = Math.max(0, users - 1);
        if (users > 0) {
            return;
        }
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        document.removeEventListener('visibilitychange', refreshIfStale);
        window.removeEventListener('focus', refreshIfStale);
    });

    return {
        /** Whether the server is meant to be opened on the internet (a tunnel is set up). */
        configured: readonly(configured),
        /** The address now (https://…), or null while the tunnel is stopped or not known yet. */
        url: readonly(url),
        hasLoaded: readonly(hasLoaded),
        refresh,
    };
}
