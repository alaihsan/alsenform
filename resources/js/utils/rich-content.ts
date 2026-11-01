import katex from 'katex';
// \ce{...} and \pu{...} for chemical formulas and units, e.g. from the Rumus IPA add-on.
import 'katex/contrib/mhchem';

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

/** How many characters students read, the formatting not counted (as App\Rules\MaxVisibleCharacters counts). */
export function visibleLength(text?: string | null): number {
    return [...stripRichFormatting(text)].length;
}

/**
 * The start of a numbered line of stored text: its formatting tags, a number ("1." or "1)") and a
 * space. Up to three digits, so a line that begins with a year is not taken for a list.
 */
const NUMBERED_LINE = /^((?:<\/?(?:b|i|u)>|<mark data-color="[a-z]+">|<\/mark>)*)(\d{1,3})([.)]) /;

export type NumberedLine = { prefix: string; number: number; delimiter: string; length: number };

/** The number of a line of stored text, or null when it is not numbered. */
export function numberedLine(line: string): NumberedLine | null {
    const match = NUMBERED_LINE.exec(line);

    return match ? { prefix: match[1], number: Number(match[2]), delimiter: match[3], length: match[0].length } : null;
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

/** The name of a formatting tag: b, i, u or mark. */
const tagName = (tag: string): string => tag.replace(/^<\/?([a-z]+)[\s\S]*$/, '$1');

/** A piece of stored text: a formatting tag, Arabic, the spaces and brackets between Arabic words, or other text. */
type TextPiece = { kind: 'tag' | 'arabic' | 'between' | 'other'; text: string };

/**
 * Plain text with its formatting: escaped, Arabic in the Mushaf font, the allowed tags kept.
 *
 * An Arabic phrase is one right-to-left box, also when part of it is formatted: a word highlighted
 * inside "بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ" must not split the phrase into boxes laid out left to right, which
 * would put its words in the wrong order. Tags open around such a phrase are closed before its box
 * and opened again inside it, so the markup stays well nested.
 */
function formatText(text: string): string {
    const pieces: TextPiece[] = [];
    splitFormatting(text).forEach((part, index) => {
        if (index % 2 === 1) {
            pieces.push({ kind: 'tag', text: part });

            return;
        }
        part.split(ARABIC_BLOCK_REGEX).forEach((segment, at) => {
            if (segment) {
                pieces.push({ kind: at % 2 === 0 ? 'other' : hasArabic(segment) ? 'arabic' : 'between', text: segment });
            }
        });
    });

    const open: string[] = [];
    const track = (tag: string) => {
        if (!tag.startsWith('</')) {
            open.push(tag);

            return;
        }
        const at = open.map(tagName).lastIndexOf(tagName(tag));
        if (at >= 0) {
            open.splice(at, 1);
        }
    };
    const closeOpen = () =>
        [...open]
            .reverse()
            .map((tag) => `</${tagName(tag)}>`)
            .join('');
    const reopen = () => open.map(formatTagHtml).join('');

    let html = '';
    for (let index = 0; index < pieces.length; index++) {
        const piece = pieces[index];
        if (piece.kind === 'tag') {
            html += formatTagHtml(piece.text);
            track(piece.text);
            continue;
        }
        if (piece.kind !== 'arabic') {
            html += escapeHtml(piece.text);
            continue;
        }

        // The phrase runs over tags and spaces up to its last Arabic word.
        let end = index;
        for (let next = index + 1; next < pieces.length && pieces[next].kind !== 'other'; next++) {
            if (pieces[next].kind === 'arabic') {
                end = next;
            }
        }
        if (end === index) {
            html += `${ARABIC_OPEN}${escapeHtml(piece.text)}</span>`;
            continue;
        }

        html += closeOpen() + ARABIC_OPEN + reopen();
        for (const inside of pieces.slice(index, end + 1)) {
            if (inside.kind === 'tag') {
                html += formatTagHtml(inside.text);
                track(inside.text);
            } else {
                html += escapeHtml(inside.text);
            }
        }
        html += `${closeOpen()}</span>${reopen()}`;
        index = end;
    }

    return html;
}

/** Keeps the list of open tags up to date with one more tag. */
function trackTag(open: string[], tag: string): void {
    if (!tag.startsWith('</')) {
        open.push(tag);

        return;
    }
    const at = open.map(tagName).lastIndexOf(tagName(tag));
    if (at >= 0) {
        open.splice(at, 1);
    }
}

const closeTags = (open: string[]): string =>
    [...open]
        .reverse()
        .map((tag) => `</${tagName(tag)}>`)
        .join('');

/** The lines of stored text, each closing the formatting it leaves open and the next opening it again. */
function balancedLines(text: string): string[] {
    const lines = [''];
    const open: string[] = [];
    splitFormatting(text).forEach((part, index) => {
        if (index % 2 === 1) {
            lines[lines.length - 1] += part;
            trackTag(open, part);

            return;
        }
        part.split('\n').forEach((piece, at) => {
            if (at > 0) {
                lines[lines.length - 1] += closeTags(open);
                lines.push(open.join(''));
            }
            lines[lines.length - 1] += piece;
        });
    });
    lines[lines.length - 1] += closeTags(open);

    return lines;
}

/** A formula over more than one line, which lines cannot be taken apart around. */
const hasFormulaOverLines = (lines: string[]): boolean =>
    lines.some(
        (line) =>
            (line.match(/\$\$/g)?.length ?? 0) % 2 === 1 ||
            line.includes('\\[') !== line.includes('\\]') ||
            line.includes('\\(') !== line.includes('\\)'),
    );

/**
 * Text with numbered lines ("1. …", "2) …") as a list: the numbers in a column of their own, and a
 * line too long for the width continuing under its text rather than under its number.
 */
function formatWithLists(lines: string[]): string {
    const items = lines.map(numberedLine);
    const digits = Math.max(...items.map((item) => (item ? String(item.number).length : 1)));

    return lines
        .map((line, index) => {
            const item = items[index];
            if (!item) {
                return formatRichContent(line) + (index < lines.length - 1 ? '\n' : '');
            }
            // The number is plain: formatting carried over from the line above is the text's, not the number's.
            return (
                `<span class="rich-list-item"><span class="rich-list-number" style="min-width: ${digits + 0.6}ch">${item.number}${item.delimiter}</span>` +
                `<span class="rich-list-text">${formatRichContent(item.prefix + line.slice(item.length))}</span></span>`
            );
        })
        .join('');
}

/**
 * Parses and formats text containing:
 * 1. LaTeX math formulas ($...$, $$...$$, \(...\), \[...\])
 * 2. Arabic Quran text and verses (with proper Amiri Quran / Madinah font & RTL)
 * 3. Standard text and existing HTML tags.
 * With `lists`, numbered lines show as a list (see formatWithLists).
 */
export function formatRichContent(rawContent?: string | null, options: { lists?: boolean } = {}): string {
    if (!rawContent) return '';

    const content = String(rawContent);

    if (options.lists) {
        const lines = balancedLines(content);
        if (lines.some((line) => numberedLine(line)) && !hasFormulaOverLines(lines)) {
            return formatWithLists(lines);
        }
    }

    // Regex to detect:
    // 1. $$ ... $$ (display math)
    // 2. \[ ... \] (display math)
    // 3. $ ... $ (inline math) - avoid matching standalone currency like $50
    // 4. \( ... \) (inline math)
    // Inline math ends on a character that is not a space without a lookbehind, which Safari before
    // 16.4 cannot read: the whole page then failed to load there (blank white).
    const mathPattern = /(\$\$[\s\S]+?\$\$|\\\[[\s\S]+?\\\]|\$(?!\s)[^$\n]*[^\s$]\$|\\\([\s\S]+?\\\))/g;

    const parts = content.split(mathPattern);

    const formattedParts = parts.map((part) => {
        if (!part) return '';

        // Formatting is not part of a formula.
        const math = stripRichFormatting(part);

        // Display math: $$...$$
        if (part.startsWith('$$') && part.endsWith('$$') && part.length >= 4) {
            return `${DISPLAY_MATH_OPEN}${renderLatex(math.slice(2, -2), true)}</div>`;
        }

        // Display math: \\[...\\]
        if (part.startsWith('\\[') && part.endsWith('\\]') && part.length >= 4) {
            return `${DISPLAY_MATH_OPEN}${renderLatex(math.slice(2, -2), true)}</div>`;
        }

        // Inline math: $...$
        if (part.startsWith('$') && part.endsWith('$') && part.length >= 2) {
            return `${INLINE_MATH_OPEN}${renderLatex(math.slice(1, -1), false)}</span>`;
        }

        // Inline math: \\(...\\)
        if (part.startsWith('\\(') && part.endsWith('\\)') && part.length >= 4) {
            return `${INLINE_MATH_OPEN}${renderLatex(math.slice(2, -2), false)}</span>`;
        }

        // User-provided text must never become HTML. Only KaTeX, the controlled Arabic wrappers
        // and the formatting tags (bold, italic, underline, highlighter) are rendered as markup.
        return formatText(part);
    });

    return formattedParts.join('');
}

/**
 * The boxes of a formula: always left to right. Inside right-to-left Arabic text KaTeX would
 * otherwise be laid out mirrored, a² + b² = c² showing as ²c = ²b + ²a.
 */
const DISPLAY_MATH_OPEN = '<div class="my-2.5 overflow-x-auto text-center" dir="ltr">';
const INLINE_MATH_OPEN = '<span class="inline-math px-0.5" dir="ltr">';

/** The box of an Arabic phrase: the Quran Madinah font, right to left. */
const ARABIC_OPEN = '<span class="font-quran text-[1.25em] leading-[2.2] tracking-wide inline-block px-1 align-baseline select-text" dir="rtl">';
