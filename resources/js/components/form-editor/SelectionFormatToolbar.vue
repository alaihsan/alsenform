<script setup lang="ts">
import { HIGHLIGHT_COLORS, type HighlightColor } from '@/utils/rich-content';
import { Bold, Eraser, Highlighter, Italic, RemoveFormatting, Underline } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

/*
 * The toolbar that pops up over text selected in a field of the editor (RichTextInput): bold,
 * italic, underline and a highlighter in five colours, like Google Docs. It works the same on
 * Arabic text. The buttons keep the selection, so several formats can be given in a row.
 */
const isShown = ref(false);
const toolbar = ref<HTMLElement | null>(null);
/** The toolbar's width, measured once it shows, to keep it inside the screen. */
let width = 340;
const position = reactive({ top: 0, left: 0, below: false });
const active = reactive({ bold: false, italic: false, underline: false });
const colors = Object.entries(HIGHLIGHT_COLORS) as [HighlightColor, { label: string; hex: string }][];

const fieldOf = (node: Node | null): HTMLElement | null => {
    const element = node instanceof Element ? node : (node?.parentElement ?? null);

    return element?.closest<HTMLElement>('[data-rich-input]') ?? null;
};

const queryState = (command: string): boolean => {
    try {
        return document.queryCommandState(command);
    } catch {
        return false;
    }
};

const update = () => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        isShown.value = false;

        return;
    }
    const field = fieldOf(selection.anchorNode);
    if (!field || field !== fieldOf(selection.focusNode)) {
        isShown.value = false;

        return;
    }
    const rect = selection.getRangeAt(0).getBoundingClientRect();
    if (rect.width === 0 && rect.height === 0) {
        isShown.value = false;

        return;
    }

    // On touch screens the phone's own menu sits above the selection: the toolbar goes below.
    const below = window.matchMedia('(pointer: coarse)').matches || rect.top < 72;
    const place = () => {
        const half = width / 2;
        position.left = Math.min(Math.max(rect.left + rect.width / 2, half + 8), Math.max(half + 8, window.innerWidth - half - 8));
    };
    position.top = below ? rect.bottom + 10 : rect.top - 10;
    position.below = below;
    place();
    active.bold = queryState('bold');
    active.italic = queryState('italic');
    active.underline = queryState('underline');
    isShown.value = true;
    nextTick(() => {
        if (toolbar.value && toolbar.value.offsetWidth !== width) {
            width = toolbar.value.offsetWidth;
            place();
        }
    });
};

let frame = 0;
const scheduleUpdate = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(update);
};

const run = (command: string, value?: string) => {
    // Bold, italic and underline as <b>, <i>, <u>; the highlighter as a background colour.
    document.execCommand('styleWithCSS', false, command === 'hiliteColor' ? 'true' : 'false');
    document.execCommand(command, false, value);
    document.execCommand('styleWithCSS', false, 'false');
    scheduleUpdate();
};

const highlight = (color: HighlightColor) => run('hiliteColor', HIGHLIGHT_COLORS[color].hex);
const removeHighlight = () => run('hiliteColor', 'transparent');
const clearFormatting = () => {
    run('removeFormat');
    run('hiliteColor', 'transparent');
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isShown.value) {
        isShown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('selectionchange', scheduleUpdate);
    window.addEventListener('scroll', scheduleUpdate, true);
    window.addEventListener('resize', scheduleUpdate);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    document.removeEventListener('selectionchange', scheduleUpdate);
    window.removeEventListener('scroll', scheduleUpdate, true);
    window.removeEventListener('resize', scheduleUpdate);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isShown"
            ref="toolbar"
            role="toolbar"
            aria-label="Format teks"
            data-testid="selection-toolbar"
            class="fixed z-[95] flex items-center gap-0.5 rounded-2xl border border-slate-200 bg-white p-1 shadow-[0_12px_32px_rgba(15,23,42,0.18)]"
            :style="{
                top: `${position.top}px`,
                left: `${position.left}px`,
                transform: position.below ? 'translateX(-50%)' : 'translate(-50%, -100%)',
            }"
            @mousedown.prevent
        >
            <button
                v-for="format in [
                    { command: 'bold', label: 'Tebal (Ctrl+B)', icon: Bold, on: active.bold },
                    { command: 'italic', label: 'Miring (Ctrl+I)', icon: Italic, on: active.italic },
                    { command: 'underline', label: 'Garis bawah (Ctrl+U)', icon: Underline, on: active.underline },
                ]"
                :key="format.command"
                type="button"
                :aria-label="format.label"
                :title="format.label"
                :aria-pressed="format.on"
                :class="[
                    'flex h-8 w-8 items-center justify-center rounded-xl transition sm:h-9 sm:w-9',
                    format.on ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-slate-100',
                ]"
                @mousedown.prevent
                @click="run(format.command)"
            >
                <component :is="format.icon" class="h-4 w-4" :stroke-width="2.5" />
            </button>

            <span class="mx-1 h-6 w-px bg-slate-200" aria-hidden="true"></span>
            <Highlighter class="mx-1 hidden h-4 w-4 text-slate-400 sm:block" aria-hidden="true" />
            <button
                v-for="[name, color] in colors"
                :key="name"
                type="button"
                :aria-label="`Spidol ${color.label.toLowerCase()}`"
                :title="`Spidol ${color.label.toLowerCase()}`"
                class="flex h-8 w-6 items-center justify-center rounded-xl transition hover:bg-slate-100 sm:h-9 sm:w-7"
                @mousedown.prevent
                @click="highlight(name)"
            >
                <span class="h-5 w-5 rounded-md border border-black/10" :style="{ backgroundColor: color.hex }"></span>
            </button>
            <button
                type="button"
                aria-label="Hapus warna spidol"
                title="Hapus warna spidol"
                class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 sm:h-9 sm:w-9"
                @mousedown.prevent
                @click="removeHighlight"
            >
                <Eraser class="h-4 w-4" />
            </button>
            <span class="mx-1 h-6 w-px bg-slate-200" aria-hidden="true"></span>
            <button
                type="button"
                aria-label="Hapus semua format"
                title="Hapus semua format"
                class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 sm:h-9 sm:w-9"
                @mousedown.prevent
                @click="clearFormatting"
            >
                <RemoveFormatting class="h-4 w-4" />
            </button>
        </div>
    </Teleport>
</template>
