<script setup lang="ts">
import {
    AUDIO_ACCEPT,
    canRecordAudio,
    durationLabel,
    formatAudioTime,
    microphoneErrorMessage,
    RECORDER_BITS_PER_SECOND,
    recorderMimeType,
    recordingFileName,
} from '@/lib/audio';
import { FileAudio, LoaderCircle, Mic, Square } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

/*
 * Recording from the microphone, or choosing a sound file: what comes out is a file, which the
 * page sends to the server (where it is compressed). Browsers record on https and localhost only;
 * on the school's http address a phone opens its own voice recorder instead ("Rekam dengan HP").
 */
const props = withDefaults(
    defineProps<{
        /** The longest recording in seconds: it stops by itself then. */
        maxSeconds?: number | null;
        /** The page is sending the last sound. */
        busy?: boolean;
        /** Percent sent, while busy. */
        progress?: number | null;
        error?: string;
        disabled?: boolean;
        /** Small buttons: for a choice. */
        compact?: boolean;
        /** Words on the record button. */
        recordLabel?: string;
    }>(),
    { maxSeconds: null, busy: false, progress: null, error: '', disabled: false, compact: false, recordLabel: 'Rekam suara' },
);

const emit = defineEmits<{
    recorded: [file: File];
}>();

const canRecord = canRecordAudio();
const isRecording = ref(false);
const isStarting = ref(false);
const elapsed = ref(0);
const level = ref(0);
const localError = ref('');

let recorder: MediaRecorder | null = null;
let stream: MediaStream | null = null;
let chunks: Blob[] = [];
let startedAt = 0;
let timer: number | undefined;
let meterFrame: number | undefined;
let audioContext: AudioContext | null = null;
let discard = false;

const message = computed(() => localError.value || props.error);
const remaining = computed(() => (props.maxSeconds ? Math.max(0, props.maxSeconds - elapsed.value) : null));

const stopMeter = () => {
    if (meterFrame !== undefined) {
        cancelAnimationFrame(meterFrame);
        meterFrame = undefined;
    }
    audioContext?.close().catch(() => {});
    audioContext = null;
    level.value = 0;
};

/** How loud the microphone is, for the bar beside the timer: it shows the recording works. */
const startMeter = (source: MediaStream) => {
    try {
        const Context = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!Context) {
            return;
        }
        audioContext = new Context();
        const analyser = audioContext.createAnalyser();
        analyser.fftSize = 512;
        audioContext.createMediaStreamSource(source).connect(analyser);
        const samples = new Uint8Array(analyser.fftSize);
        const tick = () => {
            analyser.getByteTimeDomainData(samples);
            let sum = 0;
            for (const sample of samples) {
                const centered = (sample - 128) / 128;
                sum += centered * centered;
            }
            level.value = Math.min(1, Math.sqrt(sum / samples.length) * 4);
            meterFrame = requestAnimationFrame(tick);
        };
        tick();
    } catch {
        stopMeter();
    }
};

const releaseMicrophone = () => {
    window.clearInterval(timer);
    timer = undefined;
    stopMeter();
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    recorder = null;
    isRecording.value = false;
};

const stop = () => {
    if (recorder && recorder.state !== 'inactive') {
        recorder.stop();
    } else {
        releaseMicrophone();
    }
};

const start = async () => {
    if (!canRecord || isRecording.value || isStarting.value || props.disabled || props.busy) {
        return;
    }
    localError.value = '';
    isStarting.value = true;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        });
    } catch (error) {
        isStarting.value = false;
        localError.value = microphoneErrorMessage(error);
        return;
    }

    const mimeType = recorderMimeType();
    try {
        recorder = new MediaRecorder(stream, { ...(mimeType ? { mimeType } : {}), audioBitsPerSecond: RECORDER_BITS_PER_SECOND });
    } catch {
        recorder = new MediaRecorder(stream);
    }

    chunks = [];
    discard = false;
    recorder.addEventListener('dataavailable', (event) => {
        if (event.data.size > 0) {
            chunks.push(event.data);
        }
    });
    recorder.addEventListener('stop', () => {
        const type = recorder?.mimeType || mimeType || 'audio/webm';
        const seconds = (Date.now() - startedAt) / 1000;
        releaseMicrophone();
        if (discard) {
            return;
        }
        const blob = new Blob(chunks, { type });
        chunks = [];
        if (blob.size === 0 || seconds < 0.5) {
            localError.value = 'Rekaman terlalu pendek. Tekan rekam, bicara, lalu tekan berhenti.';
            return;
        }
        emit('recorded', new File([blob], recordingFileName(type), { type }));
    });

    recorder.start(1000);
    startedAt = Date.now();
    elapsed.value = 0;
    isRecording.value = true;
    isStarting.value = false;
    startMeter(stream);

    timer = window.setInterval(() => {
        elapsed.value = (Date.now() - startedAt) / 1000;
        if (props.maxSeconds && elapsed.value >= props.maxSeconds) {
            stop();
        }
    }, 200);
};

const pickFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || props.disabled || props.busy) {
        return;
    }
    localError.value = '';
    emit('recorded', file);
};

onBeforeUnmount(() => {
    discard = true;
    stop();
});
</script>

<template>
    <div class="af-audio-recorder space-y-2" data-testid="audio-recorder">
        <!-- Sending: the server compresses the sound -->
        <div
            v-if="busy"
            :class="[
                'flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50/70 font-bold text-indigo-700',
                compact ? 'px-2.5 py-1.5 text-xs' : 'px-3.5 py-3 text-sm',
            ]"
            role="status"
        >
            <LoaderCircle class="h-4 w-4 shrink-0 animate-spin" />
            <span>{{ progress !== null && progress < 100 ? `Mengirim suara... ${progress}%` : 'Mengompres suara...' }}</span>
        </div>

        <!-- Recording -->
        <div
            v-else-if="isRecording"
            :class="['flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50', compact ? 'px-2 py-1.5' : 'px-3 py-2.5']"
            role="status"
        >
            <button
                type="button"
                :class="[
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full bg-rose-600 font-bold text-white shadow-sm transition hover:bg-rose-700 active:scale-95',
                    compact ? 'px-2.5 py-1.5 text-xs' : 'px-4 py-2 text-sm',
                ]"
                data-testid="audio-recorder-stop"
                @click.stop="stop"
            >
                <Square class="h-3.5 w-3.5 fill-current" />
                Berhenti
            </button>
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-rose-600"></span>
            </span>
            <span :class="['font-bold tabular-nums text-rose-700', compact ? 'text-xs' : 'text-sm']">
                {{ formatAudioTime(elapsed) }}<template v-if="maxSeconds"> / {{ formatAudioTime(maxSeconds) }}</template>
            </span>
            <div class="h-1.5 min-w-8 flex-1 overflow-hidden rounded-full bg-rose-100" aria-hidden="true">
                <div class="h-full rounded-full bg-rose-500 transition-[width] duration-75" :style="{ width: `${Math.round(level * 100)}%` }"></div>
            </div>
            <span v-if="!compact && remaining !== null && remaining <= 10" class="shrink-0 text-xs font-bold text-rose-700">
                {{ Math.ceil(remaining) }} dtk lagi
            </span>
        </div>

        <!-- Ready -->
        <div v-else :class="['flex flex-wrap items-center', compact ? 'gap-1.5' : 'gap-2']">
            <button
                v-if="canRecord"
                type="button"
                :disabled="disabled || isStarting"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-full font-bold transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-50',
                    compact ? 'bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700 hover:bg-rose-100' : 'bg-rose-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-rose-700',
                ]"
                data-testid="audio-recorder-start"
                @click.stop="start"
            >
                <LoaderCircle v-if="isStarting" class="h-4 w-4 animate-spin" />
                <Mic v-else class="h-4 w-4" />
                {{ isStarting ? 'Membuka mikrofon...' : compact ? 'Rekam' : recordLabel }}
            </button>
            <!-- No recording in this page (http): a phone records with its own app -->
            <label
                v-else
                :class="[
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-full font-bold transition active:scale-95',
                    compact ? 'bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700 hover:bg-rose-100' : 'bg-rose-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-rose-700',
                    disabled ? 'pointer-events-none opacity-50' : '',
                ]"
                @click.stop
            >
                <Mic class="h-4 w-4" />
                {{ compact ? 'Rekam (HP)' : 'Rekam dengan HP' }}
                <input type="file" class="sr-only" accept="audio/*" capture="user" :disabled="disabled" @change="pickFile" />
            </label>
            <label
                :class="[
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-full border font-bold transition active:scale-95',
                    compact ? 'border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50' : 'border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 hover:border-indigo-400',
                    disabled ? 'pointer-events-none opacity-50' : '',
                ]"
                @click.stop
            >
                <FileAudio class="h-4 w-4" />
                {{ compact ? 'File' : 'Pilih file suara' }}
                <input type="file" class="sr-only" :accept="AUDIO_ACCEPT" :disabled="disabled" data-testid="audio-recorder-file" @change="pickFile" />
            </label>
            <span v-if="!compact && maxSeconds" class="text-xs font-semibold text-slate-500">Paling lama {{ durationLabel(maxSeconds) }}</span>
        </div>

        <p v-if="!compact && !canRecord && !busy && !isRecording" class="text-xs leading-relaxed text-slate-500">
            Rekam langsung di halaman ini butuh alamat https. Di HP, <span class="font-semibold">Rekam dengan HP</span> membuka perekam suara;
            di laptop, pilih file suara.
        </p>
        <p v-if="message" :class="['font-semibold text-rose-700', compact ? 'text-xs' : 'text-sm']" role="alert">{{ message }}</p>
    </div>
</template>
