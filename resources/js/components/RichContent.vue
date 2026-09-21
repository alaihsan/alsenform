<script setup lang="ts">
import { computed } from 'vue';
import { formatRichContent, isPredominantlyArabic } from '@/utils/rich-content';

const props = withDefaults(
    defineProps<{
        content?: string | null;
        as?: string;
        dir?: 'auto' | 'ltr' | 'rtl';
    }>(),
    {
        content: '',
        as: 'span',
        dir: 'auto',
    }
);

const formattedContent = computed(() => formatRichContent(props.content));

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
            'rich-content break-words',
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

/* Quranic verse bracket and symbol enhancements */
.rich-content .font-quran {
    font-family: 'KFGQPC Uthmanic Script HAFS', 'Amiri Quran', 'Scheherazade New', 'Traditional Arabic', serif !important;
    font-feature-settings: 'cv01', 'cv02';
    line-height: 2.2 !important;
}
</style>
