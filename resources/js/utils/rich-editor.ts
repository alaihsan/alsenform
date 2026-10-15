import { HIGHLIGHT_COLORS, type HighlightColor } from '@/utils/rich-content';

/*
 * The text fields of the editor are contenteditable so that bold, italic, underline and the
 * highlighter show as they will to students. These convert between the stored text (plain text
 * with <b>, <i>, <u> and <mark data-color="..."> only) and the field's HTML, where a highlight is a
 * background colour so the browser's own commands (execCommand) can add and remove it.
 */

const TAG_SPLIT = new RegExp(`(<\\/?(?:b|i|u)>|<mark data-color="(?:${Object.keys(HIGHLIGHT_COLORS).join('|')})">|<\\/mark>)`, 'g');

const escapeHtml = (text: string) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** The stored text as the field's HTML. */
export function toEditorHtml(value: string): string {
    const html = value
        .split(TAG_SPLIT)
        .map((piece, index) => {
            if (index % 2 === 0) {
                return escapeHtml(piece).replace(/\n/g, '<br>');
            }
            if (piece.startsWith('<mark')) {
                const color = (piece.match(/data-color="([a-z]+)"/)?.[1] ?? 'yellow') as HighlightColor;

                return `<span style="background-color: ${HIGHLIGHT_COLORS[color].hex};">`;
            }

            return piece === '</mark>' ? '</span>' : piece;
        })
        .join('');

    // A last empty line shows only with a second line break.
    return value.endsWith('\n') ? `${html}<br>` : html;
}

type Formats = { mark: HighlightColor | null; b: boolean; i: boolean; u: boolean };
type Run = { text: string; formats: Formats };

const PLAIN: Formats = { mark: null, b: false, i: false, u: false };
const SKIPPED = new Set(['SCRIPT', 'STYLE', 'IMG', 'VIDEO', 'AUDIO', 'IFRAME', 'OBJECT', 'SVG', 'TEMPLATE']);
const BLOCKS = new Set(['DIV', 'P', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'TR']);

/** The highlighter colour nearest to a CSS colour (hex or rgb), or null for none (transparent). */
export function nearestHighlight(css: string): HighlightColor | null {
    const value = css.trim().toLowerCase();
    const channels = /^#[0-9a-f]{6}$/.test(value)
        ? [1, 3, 5].map((offset) => parseInt(value.slice(offset, offset + 2), 16))
        : (value.match(/[\d.]+/g)?.map(Number) ?? []);
    if (value === 'transparent' || channels.length < 3 || (channels.length === 4 && channels[3] === 0)) {
        return null;
    }

    let nearest: HighlightColor | null = null;
    let best = Infinity;
    for (const [name, { hex }] of Object.entries(HIGHLIGHT_COLORS) as [HighlightColor, { hex: string }][]) {
        const distance = [1, 3, 5].reduce((sum, offset, index) => sum + (parseInt(hex.slice(offset, offset + 2), 16) - channels[index]) ** 2, 0);
        if (distance < best) {
            best = distance;
            nearest = name;
        }
    }

    return nearest;
}

/** The formats an element gives its text, from its tag and its inline style. */
function formatsOf(element: HTMLElement, inherited: Formats): Formats {
    const formats = { ...inherited };
    const tag = element.tagName;
    if (tag === 'B' || tag === 'STRONG') {
        formats.b = true;
    }
    if (tag === 'I' || tag === 'EM') {
        formats.i = true;
    }
    if (tag === 'U') {
        formats.u = true;
    }
    if (tag === 'MARK') {
        formats.mark = (element.dataset.color as HighlightColor | undefined) ?? 'yellow';
    }

    const style = element.style;
    if (style.fontWeight) {
        formats.b = style.fontWeight === 'bold' || style.fontWeight === 'bolder' || Number(style.fontWeight) >= 600;
    }
    if (style.fontStyle) {
        formats.i = style.fontStyle === 'italic' || style.fontStyle === 'oblique';
    }
    const decoration = style.textDecorationLine || style.textDecoration;
    if (decoration) {
        formats.u = decoration.includes('underline');
    }
    if (style.backgroundColor) {
        formats.mark = nearestHighlight(style.backgroundColor);
    }

    return formats;
}

/** The field's content as runs of text with the same formatting. */
function runsOf(root: HTMLElement): Run[] {
    const runs: Run[] = [];
    const push = (text: string, formats: Formats) => {
        if (text) {
            runs.push({ text, formats });
        }
    };
    const endsWithNewline = () => runs.length === 0 || runs[runs.length - 1].text.endsWith('\n');

    const walk = (node: Node, formats: Formats) => {
        node.childNodes.forEach((child) => {
            if (child.nodeType === Node.TEXT_NODE) {
                push((child.textContent ?? '').replace(/ /g, ' ').replace(/​/g, ''), formats);

                return;
            }
            if (!(child instanceof HTMLElement) || SKIPPED.has(child.tagName)) {
                return;
            }
            if (child.tagName === 'BR') {
                push('\n', PLAIN);

                return;
            }
            const isBlock = BLOCKS.has(child.tagName);
            if (isBlock && !endsWithNewline()) {
                push('\n', PLAIN);
            }
            walk(child, formatsOf(child, formats));
        });
    };
    walk(root, PLAIN);

    return runs;
}

const keysOf = (formats: Formats): string[] =>
    [formats.mark ? `mark:${formats.mark}` : '', formats.b ? 'b' : '', formats.i ? 'i' : '', formats.u ? 'u' : ''].filter(Boolean);

const openTag = (key: string) => (key.startsWith('mark:') ? `<mark data-color="${key.slice(5)}">` : `<${key}>`);
const closeTag = (key: string) => (key.startsWith('mark:') ? '</mark>' : `</${key}>`);

/** The field's content as stored text: tags always nested highlighter, bold, italic, underline. */
export function serializeEditor(root: HTMLElement): string {
    let output = '';
    let open: string[] = [];

    for (const run of runsOf(root)) {
        // A line break carries no formatting of its own.
        const wanted = run.text === '\n' ? open : keysOf(run.formats);
        let shared = 0;
        while (shared < open.length && shared < wanted.length && open[shared] === wanted[shared]) {
            shared++;
        }
        output += open.slice(shared).reverse().map(closeTag).join('') + wanted.slice(shared).map(openTag).join('') + run.text;
        open = wanted;
    }
    output += [...open].reverse().map(closeTag).join('');

    // The browser keeps a last <br> that shows no line of its own.
    return output.replace(/\n((?:<\/[a-z]+>)*)$/, '$1');
}
