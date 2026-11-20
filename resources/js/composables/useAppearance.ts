import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeMount, onUnmounted, readonly, ref } from 'vue';

/**
 * The look of the app: light, dark, the device's setting, or dark in the evening by the device's
 * clock; in the standard colours or the warm ones. Saved on the account (Profil → Tampilan) and on
 * the device, which the login page uses. The first paint is set by the script in app.blade.php,
 * from the same values, so a page never flashes in the wrong look.
 */
export type Appearance = 'light' | 'dark' | 'system' | 'schedule';
export type ColorTheme = 'standard' | 'warm';

export const APPEARANCES: readonly Appearance[] = ['light', 'dark', 'system', 'schedule'];
export const COLOR_THEMES: readonly ColorTheme[] = ['standard', 'warm'];

/** "Ikuti jam" is dark from 18.00 until 06.00, by the device's clock (same as in app.blade.php). */
export const SCHEDULE_DARK_FROM_HOUR = 18;
export const SCHEDULE_DARK_UNTIL_HOUR = 6;

const STORAGE_KEYS = { appearance: 'appearance', colorTheme: 'color-theme' } as const;

/** A change made here wins over the account's value of a page that was already on its way. */
const LOCAL_CHANGE_WINS_FOR_MS = 5000;

const isAppearance = (value: unknown): value is Appearance => APPEARANCES.includes(value as Appearance);
const isColorTheme = (value: unknown): value is ColorTheme => COLOR_THEMES.includes(value as ColorTheme);

function readStored(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function store(key: string, value: string): void {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Storage full or blocked: the look still changes, only this device does not remember it.
    }
}

const root = document.documentElement;
const darkQuery = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: dark)') : null;

/** The account's values come with the page (app.blade.php); without them, this device's. */
function initialAppearance(): Appearance {
    const fromAccount = root.getAttribute('data-appearance');
    if (isAppearance(fromAccount)) {
        return fromAccount;
    }

    const stored = readStored(STORAGE_KEYS.appearance);

    return isAppearance(stored) ? stored : 'system';
}

function initialColorTheme(): ColorTheme {
    const fromAccount = root.getAttribute('data-color-theme');
    if (isColorTheme(fromAccount)) {
        return fromAccount;
    }

    const stored = readStored(STORAGE_KEYS.colorTheme);

    return isColorTheme(stored) ? stored : 'standard';
}

const appearance = ref<Appearance>(initialAppearance());
const colorTheme = ref<ColorTheme>(initialColorTheme());

let isSignedIn = false;
let lastLocalChangeAt = 0;

/** Pages that always show the light look (exam pages); the server holds the first one. */
let lightOnlyHolds = root.hasAttribute('data-light-only') ? 1 : 0;
let serverHoldsLightOnly = lightOnlyHolds > 0;

export function isScheduledDark(date: Date = new Date()): boolean {
    const hour = date.getHours();

    return hour >= SCHEDULE_DARK_FROM_HOUR || hour < SCHEDULE_DARK_UNTIL_HOUR;
}

export function resolvesToDark(value: Appearance, date: Date = new Date()): boolean {
    if (value === 'dark') {
        return true;
    }
    if (value === 'schedule') {
        return isScheduledDark(date);
    }

    return value === 'system' && Boolean(darkQuery?.matches);
}

function applyLook(): void {
    const lightOnly = lightOnlyHolds > 0;

    root.classList.toggle('dark', !lightOnly && resolvesToDark(appearance.value));

    if (!lightOnly && colorTheme.value === 'warm') {
        root.setAttribute('data-theme', 'warm');
    } else {
        root.removeAttribute('data-theme');
    }
}

function saveOnAccount(): Promise<void> {
    if (!isSignedIn) {
        return Promise.resolve();
    }

    return axios.patch(route('profile.appearance.update'), { appearance: appearance.value, color_theme: colorTheme.value }).then(() => undefined);
}

function choose(nextAppearance: Appearance, nextColorTheme: ColorTheme): Promise<void> {
    appearance.value = nextAppearance;
    colorTheme.value = nextColorTheme;
    lastLocalChangeAt = Date.now();
    store(STORAGE_KEYS.appearance, nextAppearance);
    store(STORAGE_KEYS.colorTheme, nextColorTheme);
    applyLook();

    return saveOnAccount();
}

interface PageWithUser {
    props: { auth?: { user?: { appearance?: unknown; color_theme?: unknown } | null } };
}

/** The account's look, from each page the server sends: set on another device, it follows here. */
export function syncThemeFromPage(page: PageWithUser): void {
    const user = page.props.auth?.user;
    isSignedIn = Boolean(user);

    if (!user || Date.now() - lastLocalChangeAt < LOCAL_CHANGE_WINS_FOR_MS) {
        return;
    }

    let changed = false;
    if (isAppearance(user.appearance) && user.appearance !== appearance.value) {
        appearance.value = user.appearance;
        store(STORAGE_KEYS.appearance, user.appearance);
        changed = true;
    }
    if (isColorTheme(user.color_theme) && user.color_theme !== colorTheme.value) {
        colorTheme.value = user.color_theme;
        store(STORAGE_KEYS.colorTheme, user.color_theme);
        changed = true;
    }

    if (changed) {
        applyLook();
    }
}

export function initializeTheme(): void {
    applyLook();

    // The device's own setting changed (e.g. macOS switching to dark at sunset).
    if (darkQuery) {
        if (typeof darkQuery.addEventListener === 'function') {
            darkQuery.addEventListener('change', applyLook);
        } else {
            darkQuery.addListener(applyLook);
        }
    }

    // "Ikuti jam": the evening comes while the page stays open.
    window.setInterval(() => {
        if (appearance.value === 'schedule') {
            applyLook();
        }
    }, 60 * 1000);

    router.on('navigate', (event) => syncThemeFromPage(event.detail.page as unknown as PageWithUser));
}

/** Once the first page is mounted, its own useLightOnlyPage() holds the light look, not the server. */
export function releaseServerLightOnlyHold(): void {
    if (serverHoldsLightOnly) {
        serverHoldsLightOnly = false;
        lightOnlyHolds = Math.max(0, lightOnlyHolds - 1);
        root.removeAttribute('data-light-only');
        applyLook();
    }
}

/**
 * The page always shows the standard light look: exam pages keep the design their teacher made,
 * whatever look the student's device prefers. The server lists the same pages (App\Support\Appearance).
 */
export function useLightOnlyPage(): void {
    onBeforeMount(() => {
        lightOnlyHolds++;
        applyLook();
    });

    onUnmounted(() => {
        lightOnlyHolds = Math.max(0, lightOnlyHolds - 1);
        applyLook();
    });
}

export function useAppearance() {
    return {
        appearance: readonly(appearance),
        colorTheme: readonly(colorTheme),
        setAppearance: (value: Appearance) => choose(value, colorTheme.value),
        setColorTheme: (value: ColorTheme) => choose(appearance.value, value),
    };
}
