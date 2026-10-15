<script setup lang="ts">
import type { AddonEntry } from '@/components/addons/AddonGallery.vue';
import axios from 'axios';
import { KeyRound, Maximize2, Puzzle, X } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

/*
 * Token Ujian add-on, on the Keamanan tab: the token the proctor shows in the room, when it
 * changes, and a full-screen view for a projector.
 */
const props = defineProps<{
    addon: AddonEntry;
    isOn: boolean;
    tokenUrl: string;
    examTitle: string;
}>();

const emit = defineEmits<{
    toggle: [on: boolean];
    openGallery: [];
}>();

type TokenState = {
    token: string;
    changesAt: string;
    previousToken: string | null;
    previousAcceptedUntil: string | null;
    serverTime: string;
    rotateMinutes: number;
};

const state = ref<TokenState | null>(null);
const loadError = ref('');
const now = ref(Date.now());
const isProjecting = ref(false);
const projection = ref<HTMLElement | null>(null);
let clockOffset = 0;
let ticker: ReturnType<typeof setInterval> | null = null;
let retryTimer: ReturnType<typeof setTimeout> | null = null;
let isFetching = false;

const isShown = computed(() => props.isOn && Boolean(props.addon.installedForOwner));

const fetchToken = async () => {
    if (isFetching) {
        return;
    }
    isFetching = true;
    try {
        const response = await axios.get<TokenState>(props.tokenUrl, { timeout: 10000 });
        clockOffset = new Date(response.data.serverTime).getTime() - Date.now();
        state.value = response.data;
        loadError.value = '';
    } catch (exception: unknown) {
        const response = (exception as { response?: { data?: { message?: string } } }).response;
        loadError.value = response?.data?.message ?? 'Token belum dapat dimuat. Mencoba lagi...';
        if (retryTimer) {
            clearTimeout(retryTimer);
        }
        retryTimer = setTimeout(() => {
            retryTimer = null;
            fetchToken();
        }, 5000);
    } finally {
        isFetching = false;
    }
};

const serverNow = computed(() => now.value + clockOffset);
const secondsUntil = (iso: string | null | undefined) => (iso ? Math.max(0, Math.ceil((new Date(iso).getTime() - serverNow.value) / 1000)) : 0);
const formatSeconds = (seconds: number) => `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

const secondsLeft = computed(() => secondsUntil(state.value?.changesAt));
const previousToken = computed(() =>
    state.value?.previousToken && secondsUntil(state.value.previousAcceptedUntil) > 0 ? state.value.previousToken : null,
);
const previousSecondsLeft = computed(() => secondsUntil(state.value?.previousAcceptedUntil));
const letters = computed(() => (state.value?.token ?? '······').split(''));
// Exam titles may hold formatting from the editor.
const plainTitle = computed(() => new DOMParser().parseFromString(props.examTitle, 'text/html').body.textContent?.trim() ?? '');

const start = () => {
    fetchToken();
    ticker ??= setInterval(() => {
        now.value = Date.now();
        // A new token at :00, :15, :30 and :45 of the server's clock (while the server cannot be
        // reached, the retry every 5 seconds asks for it instead of every tick).
        if (state.value && secondsLeft.value <= 0 && !retryTimer) {
            fetchToken();
        }
    }, 1000);
};

const stop = () => {
    if (ticker) {
        clearInterval(ticker);
        ticker = null;
    }
    if (retryTimer) {
        clearTimeout(retryTimer);
        retryTimer = null;
    }
};

watch(isShown, (shown) => (shown ? start() : stop()), { immediate: true });

/** Big letters for the classroom projector; full screen where the browser allows it. */
const project = async () => {
    isProjecting.value = true;
    await nextTick();
    try {
        await projection.value?.requestFullscreen?.();
    } catch {
        // The overlay alone fills the window.
    }
};

const stopProjecting = () => {
    isProjecting.value = false;
    if (document.fullscreenElement) {
        document.exitFullscreen?.().catch(() => {});
    }
};

const onFullscreenChange = () => {
    if (!document.fullscreenElement && isProjecting.value) {
        isProjecting.value = false;
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isProjecting.value) {
        stopProjecting();
    }
};

document.addEventListener('fullscreenchange', onFullscreenChange);
document.addEventListener('keydown', onKeydown);

onUnmounted(() => {
    stop();
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div>
        <!-- Not installed for the exam's owner: a quiet pointer to the add-on -->
        <div
            v-if="!addon.installedForOwner"
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-amber-200 bg-amber-50/40 px-4 py-3"
        >
            <Puzzle class="h-5 w-5 shrink-0 text-amber-600" />
            <p class="min-w-0 flex-1 text-sm text-slate-700">
                Ingin siswa hanya bisa mulai dengan token dari pengawas, seperti ANBK? Pasang add-on Token Ujian.
            </p>
            <button
                type="button"
                class="rounded-xl border border-amber-200 bg-white px-3 py-1.5 text-xs font-bold text-amber-800 transition hover:bg-amber-50"
                @click="emit('openGallery')"
            >
                Lihat add-on
            </button>
        </div>

        <section v-else class="rounded-xl border border-slate-300 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                        <KeyRound class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900">Token Ujian</h2>
                        <p class="mt-0.5 text-sm text-slate-600">
                            <template v-if="isOn">Aktif: siswa harus mengetik token ini sebelum mulai. Tampilkan atau bacakan di kelas.</template>
                            <template v-else
                                >Mati: siswa dapat mulai tanpa token. Nyalakan agar ujian hanya dapat dimulai dengan token dari pengawas.</template
                            >
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="isOn"
                    aria-label="Wajibkan token ujian"
                    :class="['relative mt-1 h-5 w-9 shrink-0 rounded-full transition', isOn ? 'bg-amber-500' : 'bg-slate-300']"
                    @click="emit('toggle', !isOn)"
                >
                    <span :class="['absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition', isOn ? 'left-[18px]' : 'left-0.5']"></span>
                </button>
            </div>

            <div v-if="isOn" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-widest text-amber-800">Token saat ini</p>
                        <p
                            class="mt-2 flex gap-1.5 sm:gap-2"
                            aria-live="polite"
                            :aria-label="state ? `Token ${state.token}` : 'Memuat token'"
                            data-testid="exam-token"
                        >
                            <span
                                v-for="(letter, index) in letters"
                                :key="index"
                                class="flex h-12 w-10 items-center justify-center rounded-xl border border-amber-300 bg-white font-mono text-3xl font-black text-slate-900 shadow-sm sm:h-14 sm:w-12 sm:text-4xl"
                                >{{ letter }}</span
                            >
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-60"
                        :disabled="!state"
                        @click="project"
                    >
                        <Maximize2 class="h-3.5 w-3.5" /> Tampilkan layar penuh
                    </button>
                </div>
                <p v-if="state" class="mt-3 text-sm text-slate-700">
                    Berganti dalam <span class="font-mono font-bold tabular-nums">{{ formatSeconds(secondsLeft) }}</span>
                    <span class="text-slate-500"> · token baru setiap {{ state.rotateMinutes }} menit</span>
                </p>
                <p v-if="previousToken" class="mt-1 text-xs text-slate-500">
                    Token sebelumnya <span class="font-mono font-bold text-slate-700">{{ previousToken }}</span> masih diterima
                    {{ formatSeconds(previousSecondsLeft) }} lagi.
                </p>
                <p v-if="loadError" class="mt-2 text-xs font-semibold text-rose-700" role="alert">{{ loadError }}</p>
                <p class="mt-3 text-xs text-slate-500">
                    Siswa yang sudah mulai tidak perlu token lagi saat memuat ulang halaman. Anda dan kolaborator dapat mencoba ujian tanpa token.
                </p>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="isProjecting"
                ref="projection"
                class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-8 bg-slate-950 p-6 text-center text-white"
                role="dialog"
                aria-modal="true"
                aria-label="Token ujian layar penuh"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-full p-2 text-slate-400 transition hover:bg-white/10 hover:text-white"
                    aria-label="Tutup layar penuh"
                    @click="stopProjecting"
                >
                    <X class="h-7 w-7" />
                </button>
                <p class="max-w-4xl text-xl font-bold text-slate-300 sm:text-3xl">{{ plainTitle }}</p>
                <p class="text-sm font-extrabold uppercase tracking-[0.4em] text-amber-400 sm:text-lg">Token Ujian</p>
                <p class="flex gap-2 sm:gap-4">
                    <span
                        v-for="(letter, index) in letters"
                        :key="index"
                        class="flex h-24 w-16 items-center justify-center rounded-2xl bg-white font-mono text-6xl font-black text-slate-950 sm:h-40 sm:w-28 sm:text-9xl"
                        >{{ letter }}</span
                    >
                </p>
                <p v-if="state" class="text-xl text-slate-300 sm:text-3xl">
                    Berganti dalam <span class="font-mono font-bold tabular-nums text-white">{{ formatSeconds(secondsLeft) }}</span>
                </p>
                <p v-if="previousToken" class="text-base text-slate-400 sm:text-xl">
                    Token sebelumnya <span class="font-mono font-bold text-slate-200">{{ previousToken }}</span> masih berlaku
                    {{ formatSeconds(previousSecondsLeft) }} lagi
                </p>
            </div>
        </Teleport>
    </div>
</template>
