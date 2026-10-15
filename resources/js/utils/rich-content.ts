import katex from 'katex';

// Unicode range for Arabic characters, Quranic diacritics, recitation marks, and ligatures
export const ARABIC_REGEX = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;

// Match continuous Arabic words, numbers, and Quranic punctuation
export const ARABIC_BLOCK_REGEX =
    /([\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\u0660-\u0669\s\(\)\[\]\{\}\uFD3E\uFD3F\u06DD\u06DE\u06D6-\u06ED]+)/g;

/**
 * Checks if a string contains any Arabic or Quranic script.
 */
export function hasArabic(text?: string | null): boolean {
    if (!text) return false;
    return ARABIC_REGEX.test(text);
}

/**
 * Checks if a string contains math syntax or Arabic script.
 */
export function hasMathOrArabic(text?: string | null): boolean {
    if (!text) return false;
    const str = String(text);
    return str.includes('$') || str.includes('\\(') || str.includes('\\[') || hasArabic(str);
}

/**
 * Checks if the content is predominantly Arabic (for setting text direction to RTL).
 */
export function isPredominantlyArabic(text?: string | null): boolean {
    if (!text) return false;
    const clean = text.replace(/<[^>]+>/g, '').trim();
    if (!clean) return false;

    // Count Arabic characters vs Latin characters
    const arabicMatches = clean.match(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/g) || [];
    const latinMatches = clean.match(/[a-zA-Z]/g) || [];

    return arabicMatches.length > latinMatches.length;
}

/**
 * Escape HTML special characters for safe rendering.
 */
function escapeHtml(str: string): string {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/**
 * Render a LaTeX formula string into KaTeX HTML.
 */
export function renderLatex(latex: string, displayMode: boolean = false): string {
    try {
        return katex.renderToString(latex.trim(), {
            displayMode,
            throwOnError: false,
            output: 'htmlAndMathml',
            strict: false,
            trust: false,
        });
    } catch {
        return `<span class="katex-error text-red-500 font-mono text-xs">${escapeHtml(latex)}</span>`;
    }
}

/**
 * Formatting a teacher gives selected text in the editor: bold, italic, underline and a highlighter
 * colour. It is stored as these exact tags around plain text; any other markup stays text.
 */
export const HIGHLIGHT_COLORS = {
    yellow: { label: 'Kuning', hex: '#fde047' },
    green: { label: 'Hijau', hex: '#86efac' },
    blue: { label: 'Biru', hex: '#93c5fd' },
    pink: { label: 'Merah muda', hex: '#f9a8d4' },
    orange: { label: 'Oranye', hex: '#fdba74' },
} as const;

export type HighlightColor = keyof typeof HIGHLIGHT_COLORS;

const COLOR_NAMES = Object.keys(HIGHLIGHT_COLORS).join('|');

/** One formatting tag of the stored text (the capture holds the highlighter colour). */
export const FORMAT_TAG_REGEX = new RegExp(`<\\/?(?:b|i|u)>|<mark data-color="(${COLOR_NAMES})">|<\\/mark>`, 'g');

/** Splits stored text into text pieces and formatting tags (the tags at odd positions). */
function splitFormatting(text: string): string[] {
    return text.split(new RegExp(`(<\\/?(?:b|i|u)>|<mark data-color="(?:${COLOR_NAMES})">|<\\/mark>)`, 'g'));
}

/** The text without its formatting: for drop-downs, page titles and other plain places. */
export function stripRichFormatting(text?: string | null): string {
    return text ? String(text).replace(FORMAT_TAG_REGEX, '') : '';
}

/** Whether the text has bold, italic, underline or highlighter formatting. */
export function hasRichFormatting(text?: string | null): boolean {
    return !!text && new RegExp(FORMAT_TAG_REGEX.source).test(String(text));
}

/** The safe HTML of one formatting tag. */
function formatTagHtml(tag: string): string {
    if (tag.startsWith('<mark')) {
        const color = tag.match(/data-color="([a-z]+)"/)?.[1] ?? 'yellow';

        return `<mark class="rich-mark rich-mark-${color}">`;
    }

    return tag;
}

/** Plain text with its formatting: escaped, Arabic in the Mushaf font, the allowed tags kept. */
function formatText(text: string): string {
    return splitFormatting(text)
        .map((piece, index) => {
            if (index % 2 === 1) {
                return formatTagHtml(piece);
            }
            const escaped = escapeHtml(piece);

            return hasArabic(escaped) ? processArabicText(escaped) : escaped;
        })
        .join('');
}

/**
 * Parses and formats text containing:
 * 1. LaTeX math formulas ($...$, $$...$$, \(...\), \[...\])
 * 2. Arabic Quran text and verses (with proper Amiri Quran / Madinah font & RTL)
 * 3. Standard text and existing HTML tags.
 */
export function formatRichContent(rawContent?: string | null): string {
    if (!rawContent) return '';

    const content = String(rawContent);

    // Regex to detect:
    // 1. $$ ... $$ (display math)
    // 2. \[ ... \] (display math)
    // 3. $ ... $ (inline math) - avoid matching standalone currency like $50
    // 4. \( ... \) (inline math)
    const mathPattern = /(\$\$[\s\S]+?\$\$|\\\[[\s\S]+?\\\]|\$(?!\s)[^$\n]+(?<!\s)\$|\\\([\s\S]+?\\\))/g;

    const parts = content.split(mathPattern);

    const formattedParts = parts.map((part) => {
        if (!part) return '';

        // Formatting is not part of a formula.
        const math = stripRichFormatting(part);

        // Display math: $$...$$
        if (part.startsWith('$$') && part.endsWith('$$') && part.length >= 4) {
            return `<div class="my-2.5 overflow-x-auto text-center">${renderLatex(math.slice(2, -2), true)}</div>`;
        }

        // Display math: \\[...\\]
        if (part.startsWith('\\[') && part.endsWith('\\]') && part.length >= 4) {
            return `<div class="my-2.5 overflow-x-auto text-center">${renderLatex(math.slice(2, -2), true)}</div>`;
        }

        // Inline math: $...$
        if (part.startsWith('$') && part.endsWith('$') && part.length >= 2) {
            return `<span class="inline-math px-0.5">${renderLatex(math.slice(1, -1), false)}</span>`;
        }

        // Inline math: \\(...\\)
        if (part.startsWith('\\(') && part.endsWith('\\)') && part.length >= 4) {
            return `<span class="inline-math px-0.5">${renderLatex(math.slice(2, -2), false)}</span>`;
        }

        // User-provided text must never become HTML. Only KaTeX, the controlled Arabic wrappers
        // and the formatting tags (bold, italic, underline, highlighter) are rendered as markup.
        return formatText(part);
    });

    return formattedParts.join('');
}

/**
 * Wrap Arabic segments in authentic Quran Madinah font typography.
 */
function processArabicText(text: string): string {
    return text.replace(ARABIC_BLOCK_REGEX, (match) => {
        const trimmed = match.trim();
        if (!trimmed || !hasArabic(trimmed)) return match;

        return `<span class="font-quran text-[1.25em] leading-[2.2] tracking-wide inline-block px-1 align-baseline select-text" dir="rtl">${match}</span>`;
    });
}
