<script setup lang="ts">
import { computed } from 'vue';
import { formatRichContent, isPredominantlyArabic } from '@/utils/rich-content';

const props = withDefaults(
    defineProps<{
        content?: string | null;
        as?: string;
        dir?: 'auto' | 'ltr' | 'rtl';
        /** Numbered lines as a list, a long one continuing under its text (a form description). */
        lists?: boolean;
    }>(),
    {
        content: '',
        as: 'span',
        dir: 'auto',
        lists: false,
    }
);

const formattedContent = computed(() => formatRichContent(props.content, { lists: props.lists }));

const isRtl = computed(() => {
    if (props.dir === 'rtl') return true;
    if (props.dir === 'ltr') return false;
    return isPredominantlyArabic(props.content);
});
</script>

<template>
    <component
        :is="as"
        :dir="isRtl ? 'rtl' : 'ltr'"
        :class="[
            'rich-content break-words whitespace-pre-line',
            isRtl ? 'text-right' : '',
        ]"
        v-html="formattedContent"
    />
</template>

<style>
/* KaTeX and Math enhancements */
.rich-content .katex-display {
    margin: 0.5rem 0 !important;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 2px;
}

.rich-content .katex {
    font-size: 1.1em;
    text-rendering: auto;
}

/* Highlighter (spidol) from the editor's selection toolbar: dark text on a marker colour, also on dark themes */
mark.rich-mark {
    color: #111827;
    border-radius: 0.25em;
    padding: 0 0.12em;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}
mark.rich-mark-yellow {
    background-color: #fde047;
}
mark.rich-mark-green {
    background-color: #86efac;
}
mark.rich-mark-blue {
    background-color: #93c5fd;
}
mark.rich-mark-pink {
    background-color: #f9a8d4;
}
mark.rich-mark-orange {
    background-color: #fdba74;
}

/* A numbered line: the number in a column of its own, the text continuing under the text. */
.rich-content .rich-list-item {
    display: flex;
    gap: 0.4em;
}
.rich-content .rich-list-number {
    flex: none;
    text-align: end;
    font-variant-numeric: tabular-nums;
}
.rich-content .rich-list-text {
    flex: 1 1 0%;
    min-width: 0;
}

/* Arabic words are inline blocks, which an underline around them does not reach by itself. */
.rich-content u .font-quran {
    text-decoration: underline;
    text-underline-offset: 0.35em;
}

/* Quranic verse bracket and symbol enhancements */
.rich-content .font-quran {
    font-family: 'KFGQPC Uthmanic Script HAFS', 'Amiri Quran', 'Scheherazade New', 'Traditional Arabic', serif !important;
    font-feature-settings: 'cv01', 'cv02';
    line-height: 2.2 !important;
}
</style>
