<script setup lang="ts">
import { serializeEditor, toEditorHtml } from '@/utils/rich-editor';
import { onMounted, ref, watch } from 'vue';

/*
 * A text field of the editor that keeps bold, italic, underline and highlighter formatting (given
 * with the selection toolbar or Ctrl+B / Ctrl+I / Ctrl+U), stored as plain text with those tags.
 * Formulas ($...$) and Arabic are typed as before; pasting keeps the text only.
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
    }>(),
    { modelValue: '', multiline: true, enter: 'newline', placeholder: '', label: undefined },
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

const onInput = () => {
    if (!field.value) {
        return;
    }
    let value = serializeEditor(field.value);
    if (!props.multiline) {
        value = value.replace(/\n/g, ' ');
    }
    // Empty again: no stray line break, so the placeholder shows.
    if (value === '' && field.value.innerHTML !== '') {
        field.value.innerHTML = '';
    }
    shown = value;
    if (value !== (props.modelValue ?? '')) {
        emit('update:modelValue', value);
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }
    event.preventDefault();
    if (!props.multiline || (props.enter === 'emit' && !event.shiftKey)) {
        emit('enter', event);

        return;
    }
    document.execCommand('insertLineBreak');
};

const insertPlainText = (text: string) => {
    document.execCommand('insertText', false, props.multiline ? text.replace(/\r\n?/g, '\n') : text.replace(/\s*[\r\n]+\s*/g, ' '));
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
        spellcheck="true"
        class="rich-input"
        @input="onInput"
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
    color: #94a3b8;
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
