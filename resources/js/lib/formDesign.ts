import { onBeforeUnmount, watchEffect, type Ref } from 'vue';

/**
 * The look of an exam from the Desainer Form add-on (like Pretty Forms Designer for Google Forms).
 * The server (App\Support\FormDesign) stores it normalised; the same design is drawn in the
 * teacher's editor and on every page the student sees.
 */
export type FormDesign = {
    preset: string | null;
    accent: string;
    background: string;
    backgroundImage: string | null;
    backgroundFit: 'cover' | 'tile';
    backgroundDim: number;
    card: string;
    cardOpacity: number;
    text: string;
    headerImage: string | null;
    logo: string | null;
    logoPosition: 'left' | 'center' | 'right';
    titleAlign: 'left' | 'center';
    titleFont: string;
    bodyFont: string;
    titleSize: 'sm' | 'md' | 'lg' | 'xl';
    textSize: 'sm' | 'md' | 'lg';
    radius: 'none' | 'sm' | 'md' | 'lg' | 'xl';
    shadow: 'none' | 'soft' | 'strong';
    cardBorder: boolean;
    width: 'narrow' | 'normal' | 'wide';
    buttonShape: 'square' | 'rounded' | 'pill';
    customCss: string;
};

/** What a dashboard card shows of a design. */
export type CardDesign = Pick<FormDesign, 'accent' | 'background' | 'card' | 'text' | 'headerImage'>;

export const DEFAULT_DESIGN: FormDesign = {
    preset: 'klasik',
    accent: '#4f46e5',
    background: '#f0efff',
    backgroundImage: null,
    backgroundFit: 'cover',
    backgroundDim: 0,
    card: '#ffffff',
    cardOpacity: 100,
    text: '#0f172a',
    headerImage: null,
    logo: null,
    logoPosition: 'left',
    titleAlign: 'left',
    titleFont: "'Inter', sans-serif",
    bodyFont: "'Inter', sans-serif",
    titleSize: 'md',
    textSize: 'md',
    radius: 'lg',
    shadow: 'soft',
    cardBorder: true,
    width: 'normal',
    buttonShape: 'rounded',
    customCss: '',
};

export type DesignPreset = {
    key: string;
    name: string;
    description: string;
    design: Partial<FormDesign>;
};

/** Ready-made themes, a starting point the teacher then adjusts. */
export const DESIGN_PRESETS: DesignPreset[] = [
    { key: 'klasik', name: 'Klasik Alsen', description: 'Ungu lembut, bawaan Alsenform.', design: {} },
    {
        key: 'google',
        name: 'Ungu Tua',
        description: 'Ungu tua, judul di tengah, halaman ramping.',
        design: {
            accent: '#673ab7',
            background: '#f0ebf8',
            text: '#202124',
            titleFont: "'Roboto', sans-serif",
            bodyFont: "'Roboto', sans-serif",
            radius: 'md',
            shadow: 'none',
            buttonShape: 'square',
            titleSize: 'lg',
        },
    },
    {
        key: 'malam',
        name: 'Mode Malam',
        description: 'Gelap dan nyaman di mata.',
        design: {
            accent: '#22d3ee',
            background: '#0b1120',
            card: '#111827',
            text: '#e5e7eb',
            shadow: 'strong',
            titleFont: "'Outfit', sans-serif",
            bodyFont: "'Outfit', sans-serif",
        },
    },
    {
        key: 'madrasah',
        name: 'Madrasah',
        description: 'Hijau zamrud, judul huruf Amiri.',
        design: {
            accent: '#047857',
            background: '#e7f5ee',
            card: '#fffdf7',
            text: '#1c1917',
            titleFont: "'Amiri', serif",
            radius: 'md',
            titleSize: 'lg',
            titleAlign: 'center',
            logoPosition: 'center',
        },
    },
    {
        key: 'ceria',
        name: 'Ceria SD',
        description: 'Bulat, besar dan berwarna untuk anak SD.',
        design: {
            accent: '#f97316',
            background: '#fef3c7',
            text: '#1e293b',
            titleFont: "'Outfit', sans-serif",
            bodyFont: "'Outfit', sans-serif",
            radius: 'xl',
            shadow: 'strong',
            buttonShape: 'pill',
            titleSize: 'xl',
            textSize: 'lg',
            cardBorder: false,
        },
    },
    {
        key: 'kertas',
        name: 'Kertas Ujian',
        description: 'Seperti lembar soal cetak.',
        design: {
            accent: '#1f2937',
            background: '#e7e5e4',
            card: '#fffef8',
            text: '#111827',
            titleFont: "'Merriweather', serif",
            bodyFont: "'Lora', serif",
            radius: 'none',
            buttonShape: 'square',
            titleAlign: 'center',
            logoPosition: 'center',
            width: 'narrow',
        },
    },
    {
        key: 'anbk',
        name: 'Biru Tegas',
        description: 'Biru tegas, rapi untuk ujian resmi.',
        design: {
            accent: '#1d4ed8',
            background: '#e8f0fe',
            titleFont: "'Roboto', sans-serif",
            bodyFont: "'Roboto', sans-serif",
            radius: 'sm',
            shadow: 'none',
            buttonShape: 'square',
        },
    },
    {
        key: 'samudra',
        name: 'Samudra',
        description: 'Biru laut yang segar.',
        design: {
            accent: '#0891b2',
            background: '#cffafe',
            titleFont: "'Plus Jakarta Sans', sans-serif",
            bodyFont: "'Plus Jakarta Sans', sans-serif",
            radius: 'xl',
            cardOpacity: 94,
            cardBorder: false,
        },
    },
    {
        key: 'senja',
        name: 'Senja',
        description: 'Merah muda hangat dengan judul anggun.',
        design: { accent: '#e11d48', background: '#ffe4e6', text: '#3f0d1a', titleFont: "'Playfair Display', serif", titleSize: 'lg' },
    },
    {
        key: 'hutan',
        name: 'Hutan',
        description: 'Latar hijau tua, kartu terang.',
        design: {
            accent: '#15803d',
            background: '#14532d',
            card: '#f7fee7',
            text: '#1a2e05',
            shadow: 'strong',
            titleFont: "'Montserrat', sans-serif",
            bodyFont: "'Montserrat', sans-serif",
            cardBorder: false,
        },
    },
    {
        key: 'minimalis',
        name: 'Minimalis',
        description: 'Putih bersih tanpa hiasan.',
        design: { accent: '#0f172a', background: '#ffffff', shadow: 'none', radius: 'sm', buttonShape: 'square', width: 'narrow' },
    },
    {
        key: 'kontras',
        name: 'Kontras Tinggi',
        description: 'Untuk siswa yang sulit melihat.',
        design: {
            accent: '#ffd400',
            background: '#000000',
            card: '#000000',
            text: '#ffffff',
            textSize: 'lg',
            titleSize: 'lg',
            radius: 'sm',
            shadow: 'none',
        },
    },
];

/** A full design from whatever was stored (missing keys from the defaults). */
export function withDesignDefaults(design?: Partial<FormDesign> | null): FormDesign {
    return { ...DEFAULT_DESIGN, ...(design ?? {}) };
}

/** A preset applied over the current design: the images and custom CSS stay. */
export function applyPreset(current: FormDesign, preset: DesignPreset): FormDesign {
    return {
        ...DEFAULT_DESIGN,
        ...preset.design,
        preset: preset.key,
        backgroundImage: current.backgroundImage,
        headerImage: current.headerImage,
        logo: current.logo,
        customCss: current.customCss,
    };
}

type Rgb = [number, number, number];

function hexToRgb(hex: string): Rgb {
    const value = /^#[0-9a-f]{6}$/i.test(hex) ? hex.slice(1) : '000000';

    return [0, 2, 4].map((offset) => parseInt(value.slice(offset, offset + 2), 16)) as Rgb;
}

function rgbToHex(rgb: Rgb): string {
    return `#${rgb
        .map((channel) =>
            Math.round(Math.max(0, Math.min(255, channel)))
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

/** `amount` of the way from a to b. */
function mix(a: Rgb, b: Rgb, amount: number): Rgb {
    return [0, 1, 2].map((index) => a[index] + (b[index] - a[index]) * amount) as Rgb;
}

function luminance(rgb: Rgb): number {
    const [r, g, b] = rgb.map((channel) => {
        const value = channel / 255;

        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a: string, b: string): number {
    const [lighter, darker] = [luminance(hexToRgb(a)), luminance(hexToRgb(b))].sort((x, y) => y - x);

    return (lighter + 0.05) / (darker + 0.05);
}

export function isDark(hex: string): boolean {
    return luminance(hexToRgb(hex)) < 0.25;
}

/** White or near-black, whichever reads better on the colour. */
export function readableOn(hex: string): string {
    return contrast(hex, '#ffffff') >= contrast(hex, '#0f172a') ? '#ffffff' : '#0f172a';
}

/** The colour moved towards `toward` until it reads on `on` (WCAG AA for normal text). */
function readableColor(color: Rgb, on: string, toward: Rgb): string {
    let candidate = color;
    for (let step = 0; step < 10 && contrast(rgbToHex(candidate), on) < 4.5; step++) {
        candidate = mix(candidate, toward, 0.2);
    }

    return rgbToHex(candidate);
}

function rgba(rgb: Rgb, alpha: number): string {
    return alpha >= 1 ? rgbToHex(rgb) : `rgba(${rgb.map(Math.round).join(', ')}, ${alpha})`;
}

const RADIUS = { none: ['0px', '0px'], sm: ['8px', '6px'], md: ['14px', '10px'], lg: ['24px', '16px'], xl: ['32px', '22px'] } as const;
const BUTTON_RADIUS = { square: '6px', rounded: '16px', pill: '9999px' } as const;
const SHADOW = {
    none: 'none',
    soft: '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
    strong: '0 22px 48px -18px rgba(15, 23, 42, 0.45)',
} as const;
const WIDTH = { narrow: '640px', normal: '768px', wide: '1024px' } as const;
const TITLE_SIZE = { sm: '1.5rem', md: '1.875rem', lg: '2.25rem', xl: '2.75rem' } as const;
const QUESTION_SIZE = { sm: '1rem', md: '1.125rem', lg: '1.3125rem' } as const;
const ANSWER_SIZE = { sm: '0.9375rem', md: '1rem', lg: '1.125rem' } as const;

/**
 * The design as CSS variables, read by resources/css/form-design.css inside `.af-designed`.
 * Every shade (hover, soft backgrounds, borders, muted text) is worked out from the four colours.
 */
export function designVariables(design: FormDesign): Record<string, string> {
    const accent = hexToRgb(design.accent);
    const card = hexToRgb(design.card);
    const text = hexToRgb(design.text);
    const alpha = Math.max(0.5, Math.min(1, design.cardOpacity / 100));
    const darkCard = isDark(design.card);
    const pageText = readableOn(design.background);

    return {
        '--af-accent': design.accent,
        '--af-accent-hover': rgbToHex(mix(accent, darkCard ? [255, 255, 255] : [0, 0, 0], 0.15)),
        '--af-on-accent': readableOn(design.accent),
        '--af-accent-soft': rgba(mix(card, accent, darkCard ? 0.2 : 0.1), alpha),
        '--af-accent-border': rgbToHex(mix(card, accent, 0.45)),
        '--af-accent-text': readableColor(accent, design.card, text),
        '--af-bg': design.background,
        '--af-on-bg': pageText,
        '--af-on-bg-muted': rgbToHex(mix(hexToRgb(pageText), hexToRgb(design.background), 0.3)),
        '--af-card': rgba(card, alpha),
        '--af-surface': rgba(mix(card, text, darkCard ? 0.08 : 0.035), alpha),
        '--af-surface-hover': rgba(mix(card, text, darkCard ? 0.14 : 0.07), alpha),
        '--af-surface-strong': rgba(mix(card, text, darkCard ? 0.2 : 0.11), alpha),
        '--af-text': design.text,
        '--af-muted': rgbToHex(mix(text, card, 0.3)),
        '--af-faint': rgbToHex(mix(text, card, 0.5)),
        '--af-border': rgbToHex(mix(card, text, darkCard ? 0.22 : 0.13)),
        '--af-border-strong': rgbToHex(mix(card, text, darkCard ? 0.32 : 0.22)),
        '--af-card-border': design.cardBorder ? rgbToHex(mix(card, text, darkCard ? 0.22 : 0.13)) : 'transparent',
        '--af-radius': RADIUS[design.radius][0],
        '--af-radius-inner': RADIUS[design.radius][1],
        '--af-button-radius': BUTTON_RADIUS[design.buttonShape],
        '--af-shadow': SHADOW[design.shadow],
        '--af-width': WIDTH[design.width],
        '--af-title-font': design.titleFont,
        '--af-body-font': design.bodyFont,
        '--af-title-size': TITLE_SIZE[design.titleSize],
        '--af-question-size': QUESTION_SIZE[design.textSize],
        '--af-answer-size': ANSWER_SIZE[design.textSize],
        '--af-title-align': design.titleAlign,
        '--af-color-scheme': darkCard ? 'dark' : 'light',
    };
}

/** The page behind the cards: a colour, or a photo dimmed by the chosen amount. */
export function pageBackgroundStyle(design: FormDesign): Record<string, string> {
    if (!design.backgroundImage) {
        return { backgroundColor: design.background };
    }

    const dim = Math.max(0, Math.min(80, design.backgroundDim)) / 100;
    const cover = design.backgroundFit === 'cover';

    return {
        backgroundColor: design.background,
        backgroundImage: `linear-gradient(rgba(0, 0, 0, ${dim}), rgba(0, 0, 0, ${dim})), url("${design.backgroundImage}")`,
        backgroundSize: cover ? 'cover' : 'auto',
        backgroundRepeat: cover ? 'no-repeat' : 'repeat',
        backgroundPosition: 'center',
        backgroundAttachment: cover ? 'fixed' : 'scroll',
    };
}

/** Where the logo sits above the title. */
export function logoJustify(design: FormDesign): string {
    return { left: 'flex-start', center: 'center', right: 'flex-end' }[design.logoPosition];
}

/**
 * The teacher's custom CSS limited to the exam's own elements: every selector is put inside
 * `scope` (html, body and :root mean the exam itself), also inside @media and @supports.
 */
export function scopeCss(css: string, scope = '.af-designed'): string {
    const source = css.replace(/\/\*[\s\S]*?(\*\/|$)/g, '').replace(/[<\\]/g, '');
    let output = '';
    let index = 0;

    while (index < source.length) {
        const open = source.indexOf('{', index);
        if (open === -1) {
            break;
        }

        const prelude = source.slice(index, open).trim();
        let depth = 1;
        let close = open + 1;
        while (close < source.length && depth > 0) {
            if (source[close] === '{') {
                depth++;
            } else if (source[close] === '}') {
                depth--;
            }
            close++;
        }
        const body = source.slice(open + 1, depth === 0 ? close - 1 : close);
        index = close;

        if (/^@(media|supports|container)\b/i.test(prelude)) {
            output += `${prelude}{${scopeCss(body, scope)}}\n`;
        } else if (/^@(keyframes|-webkit-keyframes|font-face)\b/i.test(prelude)) {
            output += `${prelude}{${body}}\n`;
        } else if (!prelude.startsWith('@') && prelude !== '') {
            const selectors = prelude
                .split(',')
                .map((selector) => selector.trim())
                .filter(Boolean)
                .map((selector) => {
                    const own = selector.replace(/^(:root|html|body)(\s+(html|body))*/i, scope);

                    return own.startsWith(scope) ? own : `${scope} ${selector}`;
                });
            output += `${selectors.join(', ')}{${body}}\n`;
        }
    }

    return output;
}

/** The custom CSS of the design on the page while the component lives (one shared style element). */
export function useDesignCss(design: Ref<FormDesign | null>): void {
    if (typeof document === 'undefined') {
        return;
    }

    const element = document.createElement('style');
    element.dataset.afDesign = '';
    document.head.appendChild(element);

    watchEffect(() => {
        element.textContent = design.value?.customCss ? scopeCss(design.value.customCss) : '';
    });

    onBeforeUnmount(() => element.remove());
}

/** The class names the custom CSS can style, shown in the designer. */
export const DESIGN_HOOKS: { selector: string; label: string }[] = [
    { selector: '.af-page', label: 'Seluruh halaman (latar)' },
    { selector: '.af-card', label: 'Semua kartu' },
    { selector: '.af-header', label: 'Kartu judul ujian' },
    { selector: '.af-header-bar', label: 'Garis warna di atas kartu judul' },
    { selector: '.af-header-image', label: 'Gambar header' },
    { selector: '.af-logo', label: 'Logo' },
    { selector: '.af-title', label: 'Judul ujian' },
    { selector: '.af-description', label: 'Deskripsi ujian' },
    { selector: '.af-section', label: 'Kartu bagian & judul-deskripsi' },
    { selector: '.af-question', label: 'Kartu soal' },
    { selector: '.af-question-title', label: 'Teks soal' },
    { selector: '.af-option', label: 'Pilihan jawaban' },
    { selector: '.af-button', label: 'Tombol utama (Mulai, Selanjutnya, Kirim)' },
    { selector: '.af-nav', label: 'Panel Nomor Soal' },
    { selector: '.af-progress', label: 'Bilah kemajuan' },
];
