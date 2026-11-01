<script setup lang="ts">
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';

/*
 * Long text (a form description holds up to 2000 characters) shown in its first lines, with
 * "Tampilkan selengkapnya" to read all of it and "Ciutkan" to fold it again. Text that fits
 * shows as it is, with no button.
 */
const props = withDefaults(
    defineProps<{
        /** The height of the folded text, in pixels. */
        collapsedHeight?: number;
        /** Unfold when something inside gets the focus (a field being written in). */
        expandOnFocus?: boolean;
        /** Folded at first. */
        startCollapsed?: boolean;
    }>(),
    { collapsedHeight: 120, expandOnFocus: false, startCollapsed: true },
);

const id = useId();
const content = ref<HTMLElement | null>(null);
const isExpanded = ref(!props.startCollapsed);
const isLong = ref(false);
const isCollapsed = computed(() => isLong.value && !isExpanded.value);

/* A line or so more than the folded height is shown in full: folding it would hide too little. */
const measure = () => {
    isLong.value = (content.value?.scrollHeight ?? 0) > props.collapsedHeight + 24;
};

let observer: ResizeObserver | null = null;

onMounted(() => {
    measure();
    if (content.value && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(measure);
        observer.observe(content.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

const onFocusin = () => {
    if (props.expandOnFocus) {
        isExpanded.value = true;
    }
};
</script>

<template>
    <div>
        <div
            :id="id"
            :class="['collapsible-content', isCollapsed ? 'is-collapsed' : '']"
            :style="isCollapsed ? { maxHeight: `${collapsedHeight}px` } : undefined"
            @focusin="onFocusin"
        >
            <div ref="content">
                <slot />
            </div>
        </div>
        <!-- The button, and beside it what the parent adds (a character counter) -->
        <div v-if="isLong || $slots.footer" class="mt-2 flex items-center justify-between gap-3">
            <button
                v-if="isLong"
                type="button"
                :aria-expanded="isExpanded"
                :aria-controls="id"
                data-testid="collapsible-toggle"
                class="-ml-2.5 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                @click="isExpanded = !isExpanded"
            >
                <component :is="isExpanded ? ChevronUp : ChevronDown" class="h-4 w-4" />
                {{ isExpanded ? 'Ciutkan' : 'Tampilkan selengkapnya' }}
            </button>
            <span v-else></span>
            <slot name="footer" />
        </div>
    </div>
</template>

<style>
.collapsible-content.is-collapsed {
    overflow: hidden;
    -webkit-mask-image: linear-gradient(to bottom, #000 55%, transparent);
    mask-image: linear-gradient(to bottom, #000 55%, transparent);
}
</style>
