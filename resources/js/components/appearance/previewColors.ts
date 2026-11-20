import type { ColorTheme } from '@/composables/useAppearance';

export interface PreviewColors {
    page: string;
    card: string;
    line: string;
    text: string;
    muted: string;
    accent: string;
}

/**
 * The colours of the drawings in Profil → Tampilan, taken from resources/js/theme/palette.js:
 * each look is drawn in its own colours, whatever look the page itself shows.
 */
export const PREVIEW_COLORS: Record<ColorTheme, { light: PreviewColors; dark: PreviewColors }> = {
    standard: {
        light: { page: '#f5f4ff', card: '#ffffff', line: '#e2e8f0', text: '#0f172a', muted: '#94a3b8', accent: '#4f46e5' },
        dark: { page: '#111317', card: '#191c22', line: '#2f343c', text: '#e9ecf1', muted: '#8a93a1', accent: '#6366f1' },
    },
    warm: {
        light: { page: '#f3f1ea', card: '#fdfcf8', line: '#e4e0d5', text: '#24221e', muted: '#9f998b', accent: '#b5583a' },
        dark: { page: '#262624', card: '#2f2e2b', line: '#46443f', text: '#f0eee7', muted: '#98948b', accent: '#cc6b47' },
    },
};
