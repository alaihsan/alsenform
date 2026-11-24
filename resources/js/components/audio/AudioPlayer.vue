<script setup lang="ts">
import { claimPlayback, formatAudioTime, playsUsed, recordPlay, releasePlayback } from '@/lib/audio';
import { LoaderCircle, Pause, Play, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/*
 * A light, steady player for the sound of a question, a choice or a spoken answer.
 *
 * Light: nothing is loaded until the sound is played (no <audio> exists before the first press),
 * and its length comes from the server. Steady: what the button shows follows the events of the
 * sound (playing, paused, buffering), never a guess; a pause pressed while the sound is still
 * starting wins; a sound that stops loading can be resumed where it stopped; and only one sound
 * plays at a time on the page.
 *
 * Batas putar, like the listening part of a TOEFL test: with maxPlays the sound can be started that
 * many times (pausing and resuming is the same play) and the bar cannot be dragged.
 */
const props = withDefaults(
    defineProps<{
        src: string;
        /** Seconds, from the server: shown before anything is loaded. */
        duration?: number | null;
        /** How many times the sound may be started; 0 is no limit. */
        maxPlays?: number;
        /** Where the plays used are counted (one key per attempt and sound); without it they are not kept. */
        playKey?: string | null;
        /** A round button and the time only: for a choice. */
        compact?: boolean;
        /** What the sound is, for screen readers. */
        label?: string;
    }>(),
    { duration: null, maxPlays: 0, playKey: null, compact: false, label: 'Audio' },
);

type Status = 'idle' | 'loading' | 'playing' | 'paused' | 'ended' | 'error';

const status = ref<Status>('idle');
const currentTime = ref(0);
const totalTime = ref<number | null>(props.duration && props.duration > 0 ? props.duration : null);
const errorMessage = ref('');
const plays = ref(props.playKey ? playsUsed(props.playKey) : 0);
/** Whether the sound exists (it is made on the first press). */
const isCreated = ref(false);

let element: HTMLAudioElement | null = null;
/** The next "playing" starts a new play (counted against the limit). */
let startsNewPlay = false;
/** Where to go on when a sound that failed to load is loaded again. */
let resumeAt = 0;

const isLimited = computed(() => props.maxPlays > 0);
const playsLeft = computed(() => (isLimited.value ? Math.max(0, props.maxPlays - plays.value) : Infinity));
const isBusy = computed(() => status.value === 'loading' || status.value === 'playing');
/** A new play needs one left; a paused play may always go on. */
const isExhausted = computed(() => isLimited.value && playsLeft.value === 0 && status.value !== 'paused' && !isBusy.value);
const canSeek = computed(() => !isLimited.value && isCreated.value && totalTime.value !== null && status.value !== 'error');
const progress = computed(() => (totalTime.value ? Math.min(100, (currentTime.value / totalTime.value) * 100) : 0));

const playsLabel = computed(() => {
    if (!isLimited.value) {
        return '';
    }
    if (isExhausted.value) {
        return props.maxPlays === 1 ? 'Sudah diputar' : `Sudah diputar ${props.maxPlays}×`;
    }

    return `Sisa putar ${playsLeft.value}×`;
});

const buttonLabel = computed(() => {
    if (isExhausted.value) {
        return `${props.label}: batas putar habis`;
    }

    return `${isBusy.value ? 'Jeda' : 'Putar'} ${props.label}`;
});

const updateTotal = () => {
    if (element && Number.isFinite(element.duration) && element.duration > 0) {
        totalTime.value = element.duration;
    }
};

const handlers: Record<string, () => void> = {
    playing: () => {
        status.value = 'playing';
        errorMessage.value = '';
        if (startsNewPlay) {
            startsNewPlay = false;
            plays.value += 1;
            if (props.playKey) {
                recordPlay(props.playKey, plays.value);
            }
        }
    },
    pause: () => {
        if (!element || element.ended || status.value === 'error') {
            return;
        }
        // Paused before the sound started: that play was not used.
        status.value = startsNewPlay ? 'idle' : 'paused';
        startsNewPlay = false;
    },
    waiting: () => {
        if (element && !element.paused) {
            status.value = 'loading';
        }
    },
    ended: () => {
        status.value = 'ended';
        currentTime.value = totalTime.value ?? currentTime.value;
        if (element) {
            releasePlayback(element);
        }
    },
    timeupdate: () => {
        if (element) {
            currentTime.value = element.currentTime;
        }
    },
    loadedmetadata: () => {
        updateTotal();
        if (resumeAt > 0 && element) {
            element.currentTime = resumeAt;
            resumeAt = 0;
        }
    },
    durationchange: updateTotal,
    error: () => {
        if (!element?.getAttribute('src')) {
            return;
        }
        resumeAt = element.currentTime || currentTime.value;
        status.value = 'error';
        errorMessage.value = navigator.onLine === false ? 'Koneksi terputus.' : 'Audio gagal dimuat.';
    },
};

const createElement = (): HTMLAudioElement => {
    const audio = new Audio();
    audio.preload = 'auto';
    Object.entries(handlers).forEach(([event, handler]) => audio.addEventListener(event, handler));
    audio.src = props.src;
    isCreated.value = true;

    return audio;
};

const play = () => {
    if (!element) {
        element = createElement();
    }
    if (status.value === 'error') {
        // Load it again and go on where it stopped (loadedmetadata seeks there).
        element.load();
    } else if (status.value === 'ended' || element.ended) {
        element.currentTime = 0;
        currentTime.value = 0;
    }

    const isNewPlay = status.value === 'idle' || status.value === 'ended';
    if (isNewPlay && isLimited.value && playsLeft.value === 0) {
        return;
    }
    startsNewPlay = isNewPlay;

    claimPlayback(element);
    status.value = 'loading';
    errorMessage.value = '';

    const started = element.play();
    started?.catch((error: DOMException) => {
        // A pause pressed before the sound started: not a failure.
        if (error?.name === 'AbortError') {
            return;
        }
        startsNewPlay = false;
        if (error?.name === 'NotAllowedError') {
            status.value = isNewPlay ? 'idle' : 'paused';
            errorMessage.value = 'Tekan tombol putar sekali lagi.';
        } else if (error?.name === 'NotSupportedError') {
            status.value = 'error';
            errorMessage.value = 'Browser ini tidak bisa memutar audio ini.';
        } else {
            status.value = 'error';
            errorMessage.value = 'Audio gagal diputar.';
        }
    });
};

/** The "pause" event sets what the button shows; a play still starting is cancelled (AbortError). */
const toggle = () => {
    if (isBusy.value) {
        element?.pause();
    } else if (!isExhausted.value) {
        play();
    }
};

const seek = (event: Event) => {
    if (!element || !canSeek.value || totalTime.value === null) {
        return;
    }
    const percent = Number((event.target as HTMLInputElement).value);
    element.currentTime = (percent / 100) * totalTime.value;
    currentTime.value = element.currentTime;
    if (status.value === 'ended') {
        status.value = 'paused';
    }
};

/** Free the sound and its connection: on leaving, or when the source changes. */
const teardown = () => {
    if (!element) {
        return;
    }
    element.pause();
    releasePlayback(element);
    Object.entries(handlers).forEach(([event, handler]) => element?.removeEventListener(event, handler));
    element.removeAttribute('src');
    element.load();
    element = null;
    isCreated.value = false;
};

watch(
    () => props.src,
    () => {
        teardown();
        status.value = 'idle';
        currentTime.value = 0;
        errorMessage.value = '';
        totalTime.value = props.duration && props.duration > 0 ? props.duration : null;
    },
);

watch(
    () => props.duration,
    (duration) => {
        if (duration && duration > 0 && !element) {
            totalTime.value = duration;
        }
    },
);

watch(
    () => props.playKey,
    (key) => {
        plays.value = key ? playsUsed(key) : 0;
    },
);

onBeforeUnmount(teardown);
</script>

<template>
    <div
        :class="[
            'af-audio inline-flex max-w-full items-center select-none',
            compact ? 'gap-2' : 'w-full gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-xs',
        ]"
        data-testid="audio-player"
        :data-status="status"
    >
        <button
            type="button"
            :class="[
                'flex shrink-0 items-center justify-center rounded-full transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
                compact ? 'h-9 w-9' : 'h-11 w-11',
                isExhausted
                    ? 'cursor-not-allowed bg-slate-200 text-slate-400'
                    : status === 'error'
                      ? 'bg-rose-600 text-white hover:bg-rose-700'
                      : 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 active:scale-95',
            ]"
            :disabled="isExhausted"
            :aria-label="buttonLabel"
            :aria-pressed="isBusy"
            data-testid="audio-toggle"
            @click="toggle"
        >
            <LoaderCircle v-if="status === 'loading'" :class="compact ? 'h-4 w-4' : 'h-5 w-5'" class="animate-spin" />
            <Pause v-else-if="status === 'playing'" :class="compact ? 'h-4 w-4' : 'h-5 w-5'" class="fill-current" />
            <RotateCcw v-else-if="status === 'error'" :class="compact ? 'h-4 w-4' : 'h-5 w-5'" />
            <Play v-else :class="compact ? 'h-4 w-4' : 'h-5 w-5'" class="translate-x-px fill-current" />
        </button>

        <div v-if="!compact" class="min-w-0 flex-1">
            <div class="relative flex h-5 items-center">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-200 ease-linear" :style="{ width: `${progress}%` }"></div>
                </div>
                <input
                    v-if="canSeek"
                    type="range"
                    min="0"
                    max="100"
                    step="0.1"
                    :value="progress"
                    :aria-label="`Posisi ${label}`"
                    class="absolute inset-0 h-5 w-full cursor-pointer opacity-0"
                    @input="seek"
                />
            </div>
            <div class="mt-0.5 flex items-center justify-between gap-2 text-xs tabular-nums text-slate-500">
                <span>{{ formatAudioTime(currentTime) }} / {{ totalTime ? formatAudioTime(totalTime) : '--:--' }}</span>
                <span v-if="errorMessage" class="truncate font-semibold text-rose-600" role="alert">{{ errorMessage }}</span>
                <span v-else-if="playsLabel" :class="['font-semibold', isExhausted ? 'text-slate-400' : 'text-amber-700']">{{ playsLabel }}</span>
            </div>
        </div>
        <span v-else class="text-xs font-semibold tabular-nums text-slate-500">
            <template v-if="errorMessage"><span class="text-rose-600">Gagal, coba lagi</span></template>
            <template v-else-if="isLimited && isExhausted">{{ playsLabel }}</template>
            <template v-else>{{ status === 'idle' ? (totalTime ? formatAudioTime(totalTime) : '') : formatAudioTime(currentTime) }}</template>
        </span>
    </div>
</template>
