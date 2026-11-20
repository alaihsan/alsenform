// @ts-check
/**
 * The colours of the app's four looks: standard (light and dark) and warm (light and dark).
 *
 * The pages are written with Tailwind's own colours (bg-white, text-slate-900, bg-indigo-600...).
 * Every one of them is a CSS variable here, set for each look on <html>, so a page follows the
 * chosen look without classes of its own. Backgrounds, borders and rings read the surface colours
 * (--c-*); text and icons the text colours (--t-*). The two differ in the dark looks: bg-slate-50
 * becomes a dark panel, while text-slate-900 becomes light text.
 *
 * Read by tailwind.config.js, and by tests/Feature/ThemePaletteTest.php to check the contrast.
 */
import tailwindColors from 'tailwindcss/colors.js';
import plugin from 'tailwindcss/plugin.js';

export const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
export const GRAY_PALETTES = ['slate', 'gray', 'zinc', 'neutral', 'stone'];
export const ACCENT_PALETTES = [
    'red',
    'orange',
    'amber',
    'yellow',
    'lime',
    'green',
    'emerald',
    'teal',
    'cyan',
    'sky',
    'blue',
    'indigo',
    'violet',
    'purple',
    'fuchsia',
    'pink',
    'rose',
];

/** Elements that always show the standard light look: exam pages, form designs, print. */
export const LIGHT_SCOPE = '.theme-light, .af-designed';

/** Terracotta clay: the accent of the warm look, in place of indigo. */
const CLAY = {
    50: '#fbf3ee',
    100: '#f7e4d9',
    200: '#efc7b3',
    300: '#e5a488',
    400: '#da8463',
    500: '#cc6b47',
    600: '#b5583a',
    700: '#984a2e',
    800: '#7b3d27',
    900: '#643323',
    950: '#381a10',
};

/** Kraft brown: the second accent of the warm look, in place of violet and purple. */
const KRAFT = {
    50: '#faf4ec',
    100: '#f2e5d2',
    200: '#e6cba9',
    300: '#d8ae80',
    400: '#c9955f',
    500: '#b57d47',
    600: '#98663a',
    700: '#7a5231',
    800: '#62432a',
    900: '#503824',
    950: '#2c1d12',
};

/** Warm greys for every grey palette of the warm light look: ivory cards on a cream page. */
const WARM_LIGHT_GRAYS = {
    50: '#f6f4ee',
    100: '#efece3',
    200: '#e4e0d5',
    300: '#cfc9bb',
    400: '#9f998b',
    500: '#716b5f',
    600: '#5a554b',
    700: '#45413a',
    800: '#312e29',
    900: '#24221e',
    950: '#161512',
};

/**
 * Greys of a dark look. Surfaces get lighter from white (the cards) to 900, which becomes a light
 * surface for the dark buttons, toasts and tooltips (their white text turns dark, see invertedText);
 * 950 stays near black for the dimmed backdrop behind dialogs. Text gets lighter towards 900, the
 * headings, and fainter towards 50, as on a light page.
 */
const STANDARD_DARK = {
    white: '#191c22',
    canvas: '#111317',
    canvasStrong: '#15171c',
    inverseText: '#111418',
    surface: {
        50: '#1d2026',
        100: '#252930',
        200: '#2f343c',
        300: '#3c424c',
        400: '#555d6a',
        500: '#707988',
        600: '#8d95a3',
        700: '#b4bbc6',
        800: '#cfd5dd',
        900: '#e6e9ee',
        950: '#050608',
    },
    text: {
        50: '#2a2f37',
        100: '#343a43',
        200: '#474e59',
        300: '#5d6572',
        400: '#8a93a1',
        500: '#9ba3af',
        600: '#b3bac4',
        700: '#c9cfd7',
        800: '#dce1e7',
        900: '#e9ecf1',
        950: '#f3f5f8',
    },
};

const WARM_DARK = {
    white: '#2f2e2b',
    canvas: '#262624',
    canvasStrong: '#2a2a27',
    inverseText: '#1c1b19',
    surface: {
        50: '#353431',
        100: '#3c3b37',
        200: '#46443f',
        300: '#55534d',
        400: '#6e6b64',
        500: '#8b887f',
        600: '#a5a298',
        700: '#bfbcb2',
        800: '#d8d5cb',
        900: '#edebe4',
        950: '#0e0e0d',
    },
    text: {
        50: '#3d3c38',
        100: '#48463f',
        200: '#5a5851',
        300: '#6f6c65',
        400: '#98948b',
        500: '#aaa69c',
        600: '#bfbbb1',
        700: '#d2cec5',
        800: '#e3e0d8',
        900: '#f0eee7',
        950: '#f8f7f3',
    },
};

/** The shadcn tokens of the standard light look, as they were before the looks existed. */
const STANDARD_LIGHT_TOKENS = {
    background: '0 0% 100%',
    foreground: '0 0% 3.9%',
    card: '0 0% 100%',
    'card-foreground': '0 0% 3.9%',
    popover: '0 0% 100%',
    'popover-foreground': '0 0% 3.9%',
    primary: '0 0% 9%',
    'primary-foreground': '0 0% 98%',
    secondary: '0 0% 92.1%',
    'secondary-foreground': '0 0% 9%',
    muted: '0 0% 96.1%',
    'muted-foreground': '0 0% 45.1%',
    accent: '0 0% 96.1%',
    'accent-foreground': '0 0% 9%',
    destructive: '0 84.2% 60.2%',
    'destructive-foreground': '0 0% 98%',
    border: '0 0% 92.8%',
    input: '0 0% 89.8%',
    ring: '0 0% 3.9%',
    'chart-1': '12 76% 61%',
    'chart-2': '173 58% 39%',
    'chart-3': '197 37% 24%',
    'chart-4': '43 74% 66%',
    'chart-5': '27 87% 67%',
    'sidebar-background': '0 0% 98%',
    'sidebar-foreground': '240 5.3% 26.1%',
    'sidebar-primary': '0 0% 10%',
    'sidebar-primary-foreground': '0 0% 98%',
    'sidebar-accent': '0 0% 94%',
    'sidebar-accent-foreground': '0 0% 30%',
    'sidebar-border': '0 0% 91%',
    'sidebar-ring': '217.2 91.2% 59.8%',
};

/** @param {string} hex */
function hexToRgb(hex) {
    const value = hex.replace('#', '');
    const full = value.length === 3 ? [...value].map((digit) => digit + digit).join('') : value;

    return [0, 2, 4].map((offset) => parseInt(full.slice(offset, offset + 2), 16));
}

/** @param {number[]} rgb */
function rgbToHex(rgb) {
    return '#' + rgb.map((channel) => Math.round(channel).toString(16).padStart(2, '0')).join('');
}

/**
 * The colour `amount` of the way from `base` to `color`.
 *
 * @param {string} color
 * @param {string} base
 * @param {number} amount
 */
function mix(color, base, amount) {
    const from = hexToRgb(base);
    const to = hexToRgb(color);

    return rgbToHex(from.map((channel, index) => channel + (to[index] - channel) * amount));
}

/** @param {string} hex */
function channels(hex) {
    return hexToRgb(hex).join(' ');
}

/** @param {string} hex */
function hsl(hex) {
    const [r, g, b] = hexToRgb(hex).map((channel) => channel / 255);
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const lightness = (max + min) / 2;
    let hue = 0;
    let saturation = 0;

    if (max !== min) {
        const delta = max - min;
        saturation = lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min);
        hue = max === r ? (g - b) / delta + (g < b ? 6 : 0) : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4;
        hue *= 60;
    }

    const round = (/** @type {number} */ number) => Math.round(number * 10) / 10;

    return `${round(hue)} ${round(saturation * 100)}% ${round(lightness * 100)}%`;
}

/** @param {Record<string, string>} scale */
function pick(scale) {
    return Object.fromEntries(SHADES.map((shade) => [shade, scale[shade]]));
}

/**
 * @typedef {Record<string, string>} Scale
 * @typedef {{
 *     dark: boolean,
 *     surface: Record<string, Scale | string>,
 *     text: Record<string, Scale | string>,
 *     canvas: string,
 *     canvasStrong: string,
 *     inverseText: string,
 *     tokens: Record<string, string>,
 * }} Look
 */

/**
 * @param {Record<string, Scale>} accentOverrides
 * @param {string} name
 * @returns {Scale}
 */
function accentScale(accentOverrides, name) {
    return accentOverrides[name] ?? pick(tailwindColors[name]);
}

/**
 * @param {{ grays?: Scale, accents?: Record<string, Scale>, white: string, canvas: string, canvasStrong: string, tokens?: Record<string, string> }} options
 * @returns {Look}
 */
function lightLook({ grays, accents = {}, white, canvas, canvasStrong, tokens }) {
    /** @type {Record<string, Scale | string>} */
    const surface = { white, black: '#000000' };
    /** @type {Record<string, Scale | string>} */
    const text = { white: '#ffffff', black: grays ? grays['950'] : '#000000' };

    for (const gray of GRAY_PALETTES) {
        surface[gray] = grays ?? pick(tailwindColors[gray]);
        text[gray] = surface[gray];
    }
    for (const accent of ACCENT_PALETTES) {
        surface[accent] = accentScale(accents, accent);
        text[accent] = surface[accent];
    }

    const gray = grays ?? pick(tailwindColors.slate);
    const look = { dark: false, surface, text, canvas, canvasStrong, inverseText: '#ffffff', tokens: {} };
    look.tokens = tokens ?? semanticTokens({ ...look, gray, textGray: gray, white, ring: accentScale(accents, 'indigo')['500'] });

    return look;
}

/**
 * @param {typeof STANDARD_DARK} grays
 * @param {Record<string, Scale>} accents
 * @returns {Look}
 */
function darkLook(grays, accents = {}) {
    /** @type {Record<string, Scale | string>} */
    const surface = { white: grays.white, black: '#000000' };
    /** @type {Record<string, Scale | string>} */
    const text = { white: '#ffffff', black: grays.text['950'] };

    for (const gray of GRAY_PALETTES) {
        surface[gray] = grays.surface;
        text[gray] = grays.text;
    }

    for (const accent of ACCENT_PALETTES) {
        const scale = accentScale(accents, accent);

        // Soft tints for the light shades that colour backgrounds and borders; solid shades stay.
        surface[accent] = {
            ...scale,
            50: mix(scale['500'], grays.white, 0.13),
            100: mix(scale['500'], grays.white, 0.2),
            200: mix(scale['500'], grays.white, 0.3),
            300: mix(scale['500'], grays.white, 0.45),
        };

        // Accent text gets lighter where it was darker, so it reads on the dark surfaces.
        text[accent] = {
            ...scale,
            500: scale['400'],
            600: scale['400'],
            700: scale['300'],
            800: scale['200'],
            900: scale['200'],
            950: scale['100'],
        };
    }

    const look = { dark: true, surface, text, canvas: grays.canvas, canvasStrong: grays.canvasStrong, inverseText: grays.inverseText, tokens: {} };
    look.tokens = semanticTokens({ ...look, gray: grays.surface, textGray: grays.text, white: grays.white, ring: accentScale(accents, 'indigo')['400'] });

    return look;
}

/**
 * The shadcn tokens (bg-background, text-muted-foreground...) of a look, from its greys.
 *
 * @param {{ dark: boolean, gray: Scale, textGray: Scale, white: string, canvas: string, inverseText: string, ring: string }} look
 */
function semanticTokens({ dark, gray, textGray, white, canvas, inverseText, ring }) {
    const tokens = {
        background: canvas,
        foreground: textGray['900'],
        card: white,
        'card-foreground': textGray['900'],
        popover: white,
        'popover-foreground': textGray['900'],
        primary: textGray['900'],
        'primary-foreground': dark ? inverseText : white,
        secondary: gray['100'],
        'secondary-foreground': textGray['900'],
        muted: gray['100'],
        'muted-foreground': textGray['500'],
        accent: gray['100'],
        'accent-foreground': textGray['900'],
        destructive: dark ? '#f05252' : '#ef4444',
        'destructive-foreground': '#fafafa',
        border: gray['200'],
        input: dark ? gray['200'] : gray['300'],
        ring,
        'sidebar-background': dark ? canvas : gray['50'],
        'sidebar-foreground': textGray['700'],
        'sidebar-primary': textGray['900'],
        'sidebar-primary-foreground': dark ? inverseText : white,
        'sidebar-accent': gray['100'],
        'sidebar-accent-foreground': textGray['800'],
        'sidebar-border': gray['200'],
        'sidebar-ring': ring,
    };

    const hslTokens = Object.fromEntries(Object.entries(tokens).map(([name, hex]) => [name, hsl(hex)]));

    return {
        ...hslTokens,
        'chart-1': dark ? '220 70% 50%' : '12 76% 61%',
        'chart-2': dark ? '160 60% 45%' : '173 58% 39%',
        'chart-3': dark ? '30 80% 55%' : '197 37% 24%',
        'chart-4': dark ? '280 65% 60%' : '43 74% 66%',
        'chart-5': dark ? '340 75% 55%' : '27 87% 67%',
    };
}

const WARM_ACCENTS = { indigo: CLAY, violet: KRAFT, purple: KRAFT };

/** The four looks, with every colour as a hex value. */
export const LOOKS = {
    standard: lightLook({ white: '#ffffff', canvas: '#f5f4ff', canvasStrong: '#f0efff', tokens: STANDARD_LIGHT_TOKENS }),
    warm: lightLook({ grays: WARM_LIGHT_GRAYS, accents: WARM_ACCENTS, white: '#fdfcf8', canvas: '#f3f1ea', canvasStrong: '#ece9df' }),
    'standard-dark': darkLook(STANDARD_DARK),
    'warm-dark': darkLook(WARM_DARK, WARM_ACCENTS),
};

/**
 * Every CSS variable of a look: surface colours (--c-*), text colours (--t-*) and shadcn tokens.
 *
 * @param {Look} look
 * @returns {Record<string, string>}
 */
export function lookVariables(look) {
    /** @type {Record<string, string>} */
    const variables = {
        '--c-white': channels(/** @type {string} */ (look.surface.white)),
        '--c-black': channels(/** @type {string} */ (look.surface.black)),
        '--t-white': channels(/** @type {string} */ (look.text.white)),
        '--t-black': channels(/** @type {string} */ (look.text.black)),
        '--c-canvas': channels(look.canvas),
        '--c-canvas-strong': channels(look.canvasStrong),
        '--t-inverse': channels(look.inverseText),
    };

    for (const palette of [...GRAY_PALETTES, ...ACCENT_PALETTES]) {
        for (const shade of SHADES) {
            variables[`--c-${palette}-${shade}`] = channels(/** @type {Scale} */ (look.surface[palette])[shade]);
            variables[`--t-${palette}-${shade}`] = channels(/** @type {Scale} */ (look.text[palette])[shade]);
        }
    }

    for (const [name, value] of Object.entries(look.tokens)) {
        variables[`--${name}`] = value;
    }

    return variables;
}

/**
 * The CSS of the looks. :root holds the standard light look in full; the other looks, and the
 * light scopes inside them, declare every variable that any look changes, so nothing leaks from
 * one look into another.
 */
export function lookStyles() {
    const base = lookVariables(LOOKS.standard);
    const others = {
        '[data-theme="warm"]': lookVariables(LOOKS.warm),
        '.dark': lookVariables(LOOKS['standard-dark']),
        '[data-theme="warm"].dark': lookVariables(LOOKS['warm-dark']),
    };

    const changed = new Set();
    for (const variables of Object.values(others)) {
        for (const [name, value] of Object.entries(variables)) {
            if (base[name] !== value) {
                changed.add(name);
            }
        }
    }

    /** @param {Record<string, string>} variables */
    const onlyChanged = (variables) => Object.fromEntries([...changed].map((name) => [name, variables[name]]));
    const light = { ...onlyChanged(base), colorScheme: 'light' };

    /** @type {Record<string, Record<string, string> | Record<string, Record<string, string>>>} */
    const styles = { ':root': { ...base, colorScheme: 'light' } };
    for (const [selector, variables] of Object.entries(others)) {
        styles[selector] = { ...onlyChanged(variables), colorScheme: selector.includes('.dark') ? 'dark' : 'light' };
    }

    // Exam pages and form designs keep the look their teacher designed; paper is always light.
    styles['.theme-light'] = light;
    styles['.af-designed'] = onlyChanged(base);
    styles['@media print'] = { ':root, :root.dark, :root[data-theme="warm"], :root[data-theme="warm"].dark': light };

    return styles;
}

/**
 * The white text of dark buttons, toasts and tooltips (bg-slate-900 text-white) turns dark in the
 * dark looks, where those surfaces become light.
 */
export function invertedTextStyles() {
    // Written out instead of :is(), which the old school tablets (iOS 12, Chrome 73) do not know.
    const surfaces = GRAY_PALETTES.flatMap((gray) => ['600', '700', '800', '900'].map((shade) => `.bg-${gray}-${shade}`));
    const hoverSurfaces = GRAY_PALETTES.flatMap((gray) => ['700', '800', '900'].map((shade) => `.hover\\:bg-${gray}-${shade}`));

    const selectors = [
        ...surfaces.map((surface) => `.dark ${surface}[class*="text-white"]`),
        ...surfaces.map((surface) => `.dark ${surface} [class*="text-white"]:not([class*="bg-"])`),
        ...hoverSurfaces.map((surface) => `.dark ${surface}.hover\\:text-white:hover`),
    ];

    return { [selectors.join(', ')]: { color: 'rgb(var(--t-inverse))' } };
}

/**
 * @param {'c' | 't'} kind
 * @returns {Record<string, string | Record<string, string>>}
 */
function paletteColors(kind) {
    /** @param {string} name */
    const color = (name) => `rgb(var(--${kind}-${name}) / <alpha-value>)`;

    /** @type {Record<string, string | Record<string, string>>} */
    const colors = {
        inherit: 'inherit',
        current: 'currentColor',
        transparent: 'transparent',
        white: color('white'),
        black: color('black'),
        // The page behind the cards (the canvas variables are surface colours in every look).
        canvas: { DEFAULT: `rgb(var(--c-canvas) / <alpha-value>)`, strong: `rgb(var(--c-canvas-strong) / <alpha-value>)` },
    };

    for (const palette of [...GRAY_PALETTES, ...ACCENT_PALETTES]) {
        colors[palette] = Object.fromEntries(SHADES.map((shade) => [shade, color(`${palette}-${shade}`)]));
    }

    return colors;
}

/** Tailwind's colours as the variables of the looks: for backgrounds, borders, rings and shadows. */
export const surfaceColors = paletteColors('c');

/** Tailwind's colours as the variables of the looks: for text, icons, placeholders and carets. */
export const textColors = paletteColors('t');

export const looksPlugin = plugin(({ addBase }) => {
    addBase(lookStyles());
    addBase(invertedTextStyles());
});
