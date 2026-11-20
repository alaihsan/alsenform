<script setup lang="ts">
import axios from 'axios';
import { AlarmClockPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * Tambah Waktu: extra minutes for every student still working on the exam, for example after the
 * server was down while the timers kept running. Open exam pages pick up the new end within seconds.
 */
const props = defineProps<{
    /** The endpoint that gives the extra time. */
    url: string;
}>();

const MAX_MINUTES = 120;

const minutes = ref<number | ''>(10);
const isConfirming = ref(false);
const isSending = ref(false);
const result = ref<{ message: string; extended: number } | null>(null);
const errorMessage = ref('');

const validMinutes = computed<number | null>(() => {
    const value = Number(minutes.value);

    return Number.isInteger(value) && value >= 1 && value <= MAX_MINUTES ? value : null;
});

const askConfirmation = () => {
    result.value = null;
    errorMessage.value = validMinutes.value === null ? `Isi tambahan waktu 1 sampai ${MAX_MINUTES} menit.` : '';
    isConfirming.value = validMinutes.value !== null;
};

const extendTime = async () => {
    if (isSending.value || validMinutes.value === null) {
        return;
    }
    isSending.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(props.url, { minutes: validMinutes.value }, { timeout: 15000 });
        result.value = { message: response.data.message, extended: response.data.extended };
        isConfirming.value = false;
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.errors?.minutes?.[0] ??
            error?.response?.data?.message ??
            'Waktu belum dapat ditambah. Periksa koneksi, lalu coba lagi.';
    } finally {
        isSending.value = false;
    }
};
</script>

<template>
    <section class="rounded-xl border border-slate-300 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="extend-time-heading">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <AlarmClockPlus class="h-5 w-5" />
            </div>
            <div>
                <h2 id="extend-time-heading" class="text-base font-semibold text-slate-900">Tambah Waktu</h2>
                <p class="mt-0.5 text-sm text-slate-600">
                    Beri menit tambahan untuk semua siswa yang sedang mengerjakan, misalnya setelah server sempat mati. Sisa waktu di halaman ujian
                    siswa ikut bertambah dalam beberapa detik, tanpa dimuat ulang.
                </p>
            </div>
        </div>

        <form class="mt-4 flex flex-wrap items-center gap-2" @submit.prevent="askConfirmation">
            <label for="extend-time-minutes" class="sr-only">Menit tambahan</label>
            <input
                id="extend-time-minutes"
                v-model.number="minutes"
                type="number"
                min="1"
                :max="MAX_MINUTES"
                step="1"
                inputmode="numeric"
                class="h-10 w-24 rounded-lg border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                :disabled="isSending"
                @input="isConfirming = false"
            />
            <span class="text-sm text-slate-600">menit</span>
            <button
                v-if="!isConfirming"
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow transition hover:bg-indigo-700 disabled:opacity-60"
                :disabled="isSending"
            >
                <AlarmClockPlus class="h-3.5 w-3.5" />
                Tambah waktu
            </button>
        </form>

        <div
            v-if="isConfirming"
            class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"
            role="alertdialog"
            aria-labelledby="extend-time-confirm"
        >
            <p id="extend-time-confirm" class="text-sm font-semibold text-amber-900">
                Tambah {{ validMinutes }} menit untuk semua siswa yang sedang mengerjakan ujian ini?
            </p>
            <p class="mt-1 text-xs text-amber-800">
                Siswa yang sudah mengirim jawaban, atau yang waktunya habis lebih dari 30 menit lalu, tidak ikut. Siswa yang baru mulai sesudah ini
                mendapat waktu penuh seperti biasa.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow transition hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="isSending"
                    @click="extendTime"
                >
                    {{ isSending ? 'Menambah...' : `Ya, tambah ${validMinutes} menit` }}
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-slate-700 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 disabled:opacity-60"
                    :disabled="isSending"
                    @click="isConfirming = false"
                >
                    Batal
                </button>
            </div>
        </div>

        <p
            v-if="result"
            :class="[
                'mt-3 rounded-xl border px-3 py-2 text-sm font-semibold',
                result.extended > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700',
            ]"
            role="status"
        >
            {{ result.message }}
        </p>
        <p v-if="errorMessage" class="mt-3 text-sm font-semibold text-rose-700" role="alert">{{ errorMessage }}</p>
    </section>
</template>
