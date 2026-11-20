<script setup lang="ts">
import type { PreviewColors } from '@/components/appearance/previewColors';

/** A small drawing of a page in one look: the header, a card with text and a button. */
defineProps<{
    panes: PreviewColors[];
}>();
</script>

<template>
    <span class="flex h-[4.5rem] w-full overflow-hidden rounded-xl border border-slate-200" aria-hidden="true">
        <span
            v-for="(pane, index) in panes"
            :key="index"
            class="flex min-w-0 flex-1 flex-col gap-1.5 p-2"
            :style="{ backgroundColor: pane.page }"
        >
            <span class="flex h-2.5 items-center gap-1 rounded-[4px] px-1" :style="{ backgroundColor: pane.card }">
                <span class="h-1 w-1 rounded-full" :style="{ backgroundColor: pane.accent }" />
                <span class="h-1 w-6 rounded-full" :style="{ backgroundColor: pane.line }" />
            </span>
            <span class="flex flex-1 flex-col justify-center gap-1 rounded-[5px] px-1.5" :style="{ backgroundColor: pane.card }">
                <span class="h-1 w-3/4 rounded-full" :style="{ backgroundColor: pane.text }" />
                <span class="h-1 w-1/2 rounded-full" :style="{ backgroundColor: pane.muted }" />
                <span class="mt-0.5 h-1.5 w-5 rounded-full" :style="{ backgroundColor: pane.accent }" />
            </span>
        </span>
    </span>
</template>
