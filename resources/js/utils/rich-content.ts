import katex from 'katex';

// Unicode range for Arabic characters, Quranic diacritics, recitation marks, and ligatures
export const ARABIC_REGEX = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;

// Match continuous Arabic words, numbers, and Quranic punctuation
export const ARABIC_BLOCK_REGEX = /([\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\u0660-\u0669\s\(\)\[\]\{\}\uFD3E\uFD3F\u06DD\u06DE\u06D6-\u06ED]+)/g;

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
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
            trust: true,
        });
    } catch {
        return `<span class="katex-error text-red-500 font-mono text-xs">${escapeHtml(latex)}</span>`;
    }
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

        // Display math: $$...$$
        if (part.startsWith('$$') && part.endsWith('$$') && part.length >= 4) {
            const math = part.slice(2, -2);
            return `<div class="my-2.5 overflow-x-auto text-center">${renderLatex(math, true)}</div>`;
        }

        // Display math: \[...\]
        if (part.startsWith('\\[') && part.endsWith('\\]') && part.length >= 4) {
            const math = part.slice(2, -2);
            return `<div class="my-2.5 overflow-x-auto text-center">${renderLatex(math, true)}</div>`;
        }

        // Inline math: $...$
        if (part.startsWith('$') && part.endsWith('$') && part.length >= 2) {
            const math = part.slice(1, -1);
            return `<span class="inline-math px-0.5">${renderLatex(math, false)}</span>`;
        }

        // Inline math: \(...\)
        if (part.startsWith('\\(') && part.endsWith('\\)') && part.length >= 4) {
            const math = part.slice(2, -2);
            return `<span class="inline-math px-0.5">${renderLatex(math, false)}</span>`;
        }

        // It's regular text or HTML: process Arabic Quran characters
        if (hasArabic(part)) {
            // If the whole block is predominantly Arabic, wrap whole block or words in font-quran
            return processArabicText(part);
        }

        return part;
    });

    return formattedParts.join('');
}

/**
 * Wrap Arabic segments in authentic Quran Madinah font typography.
 */
function processArabicText(text: string): string {
    // If text already has HTML tags, avoid breaking tags while wrapping Arabic text
    const htmlTagRegex = /(<[^>]+>)/g;
    const tokens = text.split(htmlTagRegex);

    return tokens
        .map((token) => {
            if (!token) return '';
            // Don't touch existing HTML tags
            if (token.startsWith('<') && token.endsWith('>')) {
                return token;
            }

            // Wrap Arabic character sequences with .font-quran
            if (hasArabic(token)) {
                return token.replace(
                    ARABIC_BLOCK_REGEX,
                    (match) => {
                        const trimmed = match.trim();
                        if (!trimmed || !hasArabic(trimmed)) return match;
                        return `<span class="font-quran text-[1.25em] leading-[2.2] tracking-wide inline-block px-1 align-baseline select-text" dir="rtl">${match}</span>`;
                    }
                );
            }

            return token;
        })
        .join('');
}
