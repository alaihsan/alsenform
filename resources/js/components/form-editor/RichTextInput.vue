<script setup lang="ts">
import { numberedLine, stripRichFormatting, visibleLength } from '@/utils/rich-content';
import { selectOffsets, selectionOffsets, serializeEditor, toEditorHtml } from '@/utils/rich-editor';
import { renumberLists, toggleNumbering } from '@/utils/rich-numbering';
import { onMounted, ref, watch } from 'vue';

/*
 * A text field of the editor that keeps bold, italic, underline and highlighter formatting (given
 * with the selection toolbar or Ctrl+B / Ctrl+I / Ctrl+U), stored as plain text with those tags.
 * Formulas ($...$) and Arabic are typed as before; pasting keeps the text only.
 * With `numbering`, lines are numbered with the toolbar or Ctrl+Shift+7, or by typing "1. ": Enter
 * goes on to the next number, and the numbers of a list stay in order whatever is added or removed.
 */
const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        /** Single-line fields (options, grid rows) never get a line break. */
        multiline?: boolean;
        /** What Enter does: a line break, or tell the parent (a new option; Shift+Enter is a line break). */
        enter?: 'newline' | 'emit';
        placeholder?: string;
        label?: string;
        /** The most characters of text, the formatting not counted: typing stops and a paste is cut there. */
        maxLength?: number;
        /** Numbered lists (multi-line fields only). */
        numbering?: boolean;
    }>(),
    { modelValue: '', multiline: true, enter: 'newline', placeholder: '', label: undefined, maxLength: undefined, numbering: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    enter: [event: KeyboardEvent];
}>();

const field = ref<HTMLDivElement | null>(null);
/** The value the field shows, so a change from the parent is told from the field's own. */
let shown = '';

const render = (value: string) => {
    shown = value;
    if (field.value) {
        field.value.innerHTML = toEditorHtml(value);
    }
};

onMounted(() => render(props.modelValue ?? ''));
watch(
    () => props.modelValue ?? '',
    (value) => {
        if (value !== shown) {
            render(value);
        }
    },
);

/** The characters selected in the field, which what is typed replaces. */
const selectedLength = (): number => {
    const selection = window.getSelection();
    if (!field.value || !selection || selection.rangeCount === 0 || !field.value.contains(selection.anchorNode)) {
        return 0;
    }

    return [...selection.toString()].length;
};

/** How many more characters fit in the field. */
const room = (): number => (props.maxLength === undefined ? Infinity : Math.max(0, props.maxLength - (visibleLength(shown) - selectedLength())));

const placeCaretAtEnd = () => {
    const selection = window.getSelection();
    if (!field.value || !selection) {
        return;
    }
    const range = document.createRange();
    range.selectNodeContents(field.value);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
};

/* Typing and dropping past the limit is refused (Enter and pasting are handled on their own). */
const onBeforeinput = (beforeinput: Event) => {
    const event = beforeinput as InputEvent;
    if (props.maxLength === undefined || !event.inputType?.startsWith('insert') || event.isComposing) {
        return;
    }
    const text = event.data ?? event.dataTransfer?.getData('text/plain') ?? '';
    const length = event.inputType === 'insertParagraph' || event.inputType === 'insertLineBreak' ? 1 : [...text].length;
    if (length > room()) {
        event.preventDefault();
    }
};

const commit = (value: string) => {
    shown = value;
    if (value !== (props.modelValue ?? '')) {
        emit('update:modelValue', value);
    }
};

const canNumber = () => props.numbering && props.multiline;

/** The lines of a value as the field shows them, formatting aside. */
const visibleLines = (value: string): string[] => stripRichFormatting(value).split('\n');

const lineAt = (lines: string[], offset: number): { line: number; column: number } => {
    let line = 0;
    let start = 0;
    while (line < lines.length - 1 && offset > start + lines[line].length) {
        start += lines[line].length + 1;
        line++;
    }

    return { line, column: offset - start };
};

const offsetAt = (lines: string[], line: number, column: number): number =>
    lines.slice(0, line).reduce((sum, text) => sum + text.length + 1, 0) + Math.max(0, Math.min(column, lines[line]?.length ?? 0));

/**
 * Where an offset goes when the start of its line changed (a number put on, taken off or counted
 * again): it stays on the same text.
 */
const followChange = (before: string[], after: string[], offset: number): number => {
    const { line, column } = lineAt(before, offset);
    const old = before[line] ?? '';
    const now = after[line] ?? '';
    let same = 0;
    while (same < old.length && same < now.length && old[old.length - 1 - same] === now[now.length - 1 - same]) {
        same++;
    }

    return offsetAt(after, line, column >= old.length - same ? column + now.length - old.length : Math.min(column, now.length - same));
};

/** Shows a new value of the field's own, the caret or the selection kept on the same text. */
const showOwn = (previous: string, next: string) => {
    if (!field.value) {
        return;
    }
    const selection = selectionOffsets(field.value);
    const before = visibleLines(previous);
    const after = visibleLines(next);
    render(next);
    if (selection) {
        selectOffsets(field.value, followChange(before, after, selection.start), followChange(before, after, selection.end));
    }
};

const onInput = (event: Event) => {
    if (!field.value) {
        return;
    }
    let value = serializeEditor(field.value);
    if (!props.multiline) {
        value = value.replace(/\n/g, ' ');
    }
    // What still went past the limit (a word from the keyboard's suggestions) is undone.
    if (props.maxLength !== undefined && visibleLength(value) > props.maxLength && visibleLength(value) > visibleLength(shown)) {
        render(shown);
        placeCaretAtEnd();

        return;
    }
    // A list keeps its numbers in order (not while a word is still being composed).
    if (canNumber() && !(event as InputEvent).isComposing) {
        const renumbered = renumberLists(value);
        if (renumbered !== value) {
            showOwn(value, renumbered);
            value = renumbered;
        }
    }
    // Empty again: no stray line break, so the placeholder shows.
    if (value === '' && field.value.innerHTML !== '') {
        field.value.innerHTML = '';
    }
    commit(value);
};

/** Numbers the selected lines (or the caret's line), or takes their numbers off: the toolbar and Ctrl+Shift+7. */
const toggleSelectedNumbering = () => {
    if (!field.value || !canNumber()) {
        return;
    }
    const selection = selectionOffsets(field.value);
    if (!selection) {
        return;
    }
    const lines = visibleLines(shown);
    const first = lineAt(lines, selection.start).line;
    const end = lineAt(lines, selection.end);
    // A selection that ends at the very start of a line does not take that line in.
    const last = end.line > first && end.column === 0 ? end.line - 1 : end.line;
    const next = toggleNumbering(shown, first, last);
    if (next === shown || (props.maxLength !== undefined && visibleLength(next) > props.maxLength)) {
        return;
    }
    if (selection.start === selection.end) {
        showOwn(shown, next);
    } else {
        const after = visibleLines(next);
        render(next);
        selectOffsets(field.value, offsetAt(after, first, 0), offsetAt(after, last, after[last].length));
    }
    commit(next);
};

/**
 * Enter on a numbered line: the next number on a new line, or, on a line with only its number,
 * the end of the list (the number is taken off). False when Enter is a plain line break.
 */
const continueList = (): boolean => {
    if (!field.value || !canNumber()) {
        return false;
    }
    const selection = selectionOffsets(field.value);
    if (!selection || selection.start !== selection.end) {
        return false;
    }
    const lines = shown.split('\n');
    const { line, column } = lineAt(visibleLines(shown), selection.start);
    const item = numberedLine(lines[line] ?? '');
    if (!item || column < `${item.number}${item.delimiter} `.length) {
        return false;
    }
    const rest = lines[line].slice(item.length);
    if (stripRichFormatting(rest).trim() === '') {
        lines[line] = item.prefix + rest;
        const next = lines.join('\n');
        showOwn(shown, next);
        commit(next);

        return true;
    }
    const marker = `${item.number + 1}${item.delimiter} `;
    if (room() < marker.length + 1) {
        return false;
    }
    document.execCommand('insertLineBreak');
    document.execCommand('insertText', false, marker);

    return true;
};

const onKeydown = (event: KeyboardEvent) => {
    if (canNumber() && (event.ctrlKey || event.metaKey) && event.shiftKey && event.code === 'Digit7') {
        event.preventDefault();
        toggleSelectedNumbering();

        return;
    }
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }
    event.preventDefault();
    if (!props.multiline || (props.enter === 'emit' && !event.shiftKey)) {
        emit('enter', event);

        return;
    }
    if (continueList() || room() < 1) {
        return;
    }
    document.execCommand('insertLineBreak');
};

const insertPlainText = (text: string) => {
    const plain = props.multiline ? text.replace(/\r\n?/g, '\n') : text.replace(/\s*[\r\n]+\s*/g, ' ');
    const fitting = [...plain].slice(0, room()).join('');
    if (fitting !== '') {
        document.execCommand('insertText', false, fitting);
    }
};

const onPaste = (event: ClipboardEvent) => {
    event.preventDefault();
    insertPlainText(event.clipboardData?.getData('text/plain') ?? '');
};

const onDrop = (event: DragEvent) => {
    const text = event.dataTransfer?.getData('text/plain');
    // Files and formatted content are not dropped in; text is, without its formatting.
    if (event.dataTransfer?.files.length || text === undefined) {
        event.preventDefault();
    }
};

defineExpose({
    focus: () => field.value?.focus(),
    element: field,
});
</script>

<template>
    <div
        ref="field"
        contenteditable="true"
        role="textbox"
        data-rich-input
        :aria-multiline="multiline"
        :aria-label="label ?? placeholder"
        :data-placeholder="placeholder"
        :data-numbering="canNumber() ? '' : undefined"
        spellcheck="true"
        class="rich-input"
        @beforeinput="onBeforeinput"
        @input="onInput"
        @rich-numbering="toggleSelectedNumbering"
        @keydown="onKeydown"
        @paste="onPaste"
        @drop="onDrop"
    ></div>
</template>

<style>
.rich-input {
    min-height: 1.5em;
    cursor: text;
    outline: none;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.rich-input:empty::before {
    content: attr(data-placeholder);
    color: rgb(var(--t-slate-400));
    pointer-events: none;
}
.rich-input b,
.rich-input strong {
    font-weight: 700;
}
/* The highlighter keeps dark text, also on a dark design. */
.rich-input span[style*='background-color'] {
    color: #111827;
    border-radius: 0.2em;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}
</style>
