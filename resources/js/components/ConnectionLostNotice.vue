<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { RefreshCw, WifiOff, X } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * A visit inside the app that gets no answer at all (the LAN server is off or restarting, or the
 * device lost its Wi-Fi) shows no error page: Inertia silently stays on the current page. This
 * notice tells the user why nothing happens, and retries the visit when it was a plain page load.
 */
const visible = ref(false);
const retryUrl = ref<string | null>(null);
const retrying = ref(false);

let lastVisit: { url: URL; method: string } | null = null;
const removeListeners: Array<() => void> = [];

const retry = (): void => {
    if (!retryUrl.value) {
        return;
    }
    retrying.value = true;
    router.visit(retryUrl.value, { onFinish: () => (retrying.value = false) });
};

onMounted(() => {
    removeListeners.push(
        router.on('start', (event) => {
            lastVisit = event.detail.visit;
        }),
        router.on('exception', (event) => {
            const exception = event.detail.exception;
            // A server that answered (with an error page) is handled elsewhere.
            if (!axios.isAxiosError(exception) || exception.response) {
                return;
            }
            event.preventDefault();
            // Forms are not sent again automatically: the user submits them once the server is back.
            retryUrl.value = lastVisit?.method === 'get' ? lastVisit.url.href : null;
            visible.value = true;
        }),
        router.on('success', () => {
            visible.value = false;
        }),
    );
});

onUnmounted(() => removeListeners.forEach((remove) => remove()));
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-3 opacity-0"
    >
        <div
            v-if="visible"
            role="alert"
            class="fixed inset-x-4 bottom-4 z-[100] flex items-start gap-3 rounded-2xl border border-rose-100 bg-white p-4 text-left shadow-[0_12px_32px_rgba(15,23,42,0.18)] sm:left-auto sm:right-6 sm:w-[380px]"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                <WifiOff class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-black text-slate-900">Server tidak dapat dihubungi</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-600">
                    Periksa Wi-Fi atau kabel LAN perangkat ini. Jika server sedang dinyalakan ulang, tunggu sebentar lalu coba lagi.
                </p>
                <button
                    v-if="retryUrl"
                    type="button"
                    :disabled="retrying"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                    @click="retry"
                >
                    <RefreshCw :class="['h-3.5 w-3.5', retrying ? 'animate-spin' : '']" />
                    Coba Lagi
                </button>
            </div>
            <button
                type="button"
                class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                aria-label="Tutup"
                @click="visible = false"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </Transition>
</template>
