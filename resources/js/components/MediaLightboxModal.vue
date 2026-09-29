<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { ZoomIn, ZoomOut, RotateCcw, X, Maximize2 } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    media: {
        url: string;
        type: 'image' | 'video';
        caption?: string;
    } | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const zoom = ref(1);
const translateX = ref(0);
const translateY = ref(0);
const isDragging = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);

const getYoutubeEmbedUrl = (url?: string): string | undefined => {
    if (!url) return undefined;
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return match && match[2].length === 11 ? `https://www.youtube.com/embed/${match[2]}?autoplay=1` : undefined;
};

const isYoutube = computed(() => {
    if (!props.media || props.media.type !== 'video') return false;
    return !!getYoutubeEmbedUrl(props.media.url);
});

const youtubeEmbedUrl = computed(() => {
    if (!props.media) return undefined;
    return getYoutubeEmbedUrl(props.media.url);
});

const resetTransform = () => {
    zoom.value = 1;
    translateX.value = 0;
    translateY.value = 0;
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        resetTransform();
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

const handleZoomIn = () => {
    zoom.value = Math.min(Number((zoom.value + 0.25).toFixed(2)), 4);
};

const handleZoomOut = () => {
    zoom.value = Math.max(Number((zoom.value - 0.25).toFixed(2)), 0.5);
    if (zoom.value <= 1) {
        translateX.value = 0;
        translateY.value = 0;
    }
};

const handleWheel = (event: WheelEvent) => {
    if (props.media?.type !== 'image') return;
    event.preventDefault();
    if (event.deltaY < 0) {
        handleZoomIn();
    } else {
        handleZoomOut();
    }
};

const handleMouseDown = (event: MouseEvent) => {
    if (zoom.value <= 1 || props.media?.type !== 'image') return;
    isDragging.value = true;
    dragStartX.value = event.clientX - translateX.value;
    dragStartY.value = event.clientY - translateY.value;
};

const handleMouseMove = (event: MouseEvent) => {
    if (!isDragging.value) return;
    translateX.value = event.clientX - dragStartX.value;
    translateY.value = event.clientY - dragStartY.value;
};

const handleMouseUp = () => {
    isDragging.value = false;
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (!props.show) return;
    if (event.key === 'Escape') {
        emit('close');
    } else if (event.key === '+' || event.key === '=') {
        handleZoomIn();
    } else if (event.key === '-') {
        handleZoomOut();
    } else if (event.key === '0') {
        resetTransform();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('mouseup', handleMouseUp);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('mouseup', handleMouseUp);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show && media"
                class="fixed inset-0 z-[100] flex flex-col bg-slate-950/92 backdrop-blur-md select-none"
                @click.self="emit('close')"
            >
                <!-- Top Toolbar -->
                <div class="relative z-10 flex items-center justify-between border-b border-white/10 bg-slate-900/60 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="rounded-lg bg-indigo-600/30 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-indigo-300 border border-indigo-500/30">
                            {{ media.type === 'image' ? 'Gambar' : 'Video' }}
                        </span>
                        <span v-if="media.caption" class="truncate text-xs font-medium text-slate-300 max-w-xs sm:max-w-md">
                            {{ media.caption }}
                        </span>
                    </div>

                    <!-- Image Zoom Controls -->
                    <div class="flex items-center gap-2">
                        <template v-if="media.type === 'image'">
                            <div class="flex items-center gap-1 rounded-xl bg-white/10 p-1 border border-white/10">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white"
                                    title="Perkecil (-)"
                                    @click="handleZoomOut"
                                >
                                    <ZoomOut class="h-4 w-4" />
                                </button>
                                <span class="min-w-12 text-center text-xs font-bold font-mono text-slate-200">
                                    {{ Math.round(zoom * 100) }}%
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white"
                                    title="Perbesar (+)"
                                    @click="handleZoomIn"
                                >
                                    <ZoomIn class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white border-l border-white/10 ml-0.5"
                                    title="Reset Ukuran (0)"
                                    @click="resetTransform"
                                >
                                    <RotateCcw class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </template>

                        <!-- Close Button -->
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-red-500 hover:text-white border border-white/10"
                            title="Tutup (Esc)"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                            <span class="hidden sm:inline">Tutup</span>
                        </button>
                    </div>
                </div>

                <!-- Main Viewport Area -->
                <div
                    class="relative flex flex-1 items-center justify-center overflow-hidden p-4 sm:p-8"
                    @wheel="handleWheel"
                    @mousedown="handleMouseDown"
                    @mousemove="handleMouseMove"
                >
                    <!-- Image Display with Pan & Zoom -->
                    <div
                        v-if="media.type === 'image'"
                        class="flex items-center justify-center transition-transform select-none"
                        :class="[
                            zoom > 1 ? (isDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default'
                        ]"
                        :style="{
                            transform: `translate(${translateX}px, ${translateY}px) scale(${zoom})`,
                            transformOrigin: 'center center',
                            transition: isDragging ? 'none' : 'transform 0.12s ease-out',
                        }"
                    >
                        <img
                            :src="media.url"
                            :alt="media.caption ?? 'Media kuis'"
                            class="max-h-[82vh] max-w-[92vw] rounded-xl object-contain shadow-2xl pointer-events-none select-none"
                            draggable="false"
                        />
                    </div>

                    <!-- Video Display -->
                    <div
                        v-else-if="media.type === 'video'"
                        class="w-full max-w-4xl overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl"
                    >
                        <iframe
                            v-if="isYoutube && youtubeEmbedUrl"
                            :src="youtubeEmbedUrl"
                            class="aspect-video w-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                        <video
                            v-else
                            :src="media.url"
                            controls
                            autoplay
                            class="max-h-[80vh] w-full bg-black"
                        ></video>
                    </div>
                </div>

                <!-- Bottom Helper Notice -->
                <div class="py-2 text-center text-[11px] text-slate-400">
                    <span v-if="media.type === 'image'">
                        💡 Tip: Gunakan scroll mouse atau tombol (+) (-) untuk zoom in/out. Geser gambar saat diperbesar. Tekan <kbd class="rounded bg-white/10 px-1 py-0.5 font-mono text-[10px] text-slate-300">ESC</kbd> untuk menutup.
                    </span>
                    <span v-else>
                        Tekan <kbd class="rounded bg-white/10 px-1 py-0.5 font-mono text-[10px] text-slate-300">ESC</kbd> atau klik di luar untuk menutup.
                    </span>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
