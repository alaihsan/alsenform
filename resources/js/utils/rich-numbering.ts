import { numberedLine, stripRichFormatting } from '@/utils/rich-content';

/*
 * Numbered lists in a text field of the editor: "1. ", "2. " (or "1) ") at the start of lines of
 * the stored text, given to selected lines with the toolbar or typed by hand. A list counts on from
 * its first number, so adding, removing or moving a line puts the numbers in order again.
 */

/** The formatting tags a line of stored text begins with. */
const LEADING_TAGS = /^(?:<\/?(?:b|i|u)>|<mark data-color="[a-z]+">|<\/mark>)*/;

const isBlank = (line: string): boolean => stripRichFormatting(line).trim() === '';

/** Every list counted on from its first number; a line that is not numbered (a blank one too) ends it. */
export function renumberLists(value: string): string {
    let next: number | null = null;

    return value
        .split('\n')
        .map((line) => {
            const item = numberedLine(line);
            if (!item) {
                next = null;

                return line;
            }
            const number = next ?? item.number;
            next = number + 1;

            return number === item.number ? line : `${item.prefix}${number}${item.delimiter} ${line.slice(item.length)}`;
        })
        .join('\n');
}

/**
 * Numbers the lines from `first` to `last` (1, 2, 3 …, or on from a list right above them), or takes
 * their numbers off when they all have one. Blank lines are left as they are.
 */
export function toggleNumbering(value: string, first: number, last: number): string {
    const lines = value.split('\n');
    const chosen = lines.slice(first, last + 1).filter((line) => !isBlank(line));
    if (chosen.length === 0) {
        return value;
    }
    const isNumbered = chosen.every((line) => numberedLine(line) !== null);
    let number = first > 0 ? (numberedLine(lines[first - 1])?.number ?? 0) + 1 : 1;

    for (let index = first; index <= last && index < lines.length; index++) {
        const line = lines[index];
        const item = numberedLine(line);
        if (isNumbered) {
            lines[index] = item ? item.prefix + line.slice(item.length) : line;
            continue;
        }
        if (isBlank(line)) {
            continue;
        }
        if (item) {
            lines[index] = `${item.prefix}${number}${item.delimiter} ${line.slice(item.length)}`;
        } else {
            const tags = LEADING_TAGS.exec(line)?.[0] ?? '';
            lines[index] = `${tags}${number}. ${line.slice(tags.length).replace(/^\s+/, '')}`;
        }
        number++;
    }

    return renumberLists(lines.join('\n'));
}
