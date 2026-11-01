<script setup lang="ts">
import type { ExamDelivery } from '@/types/quiz';
import { ChevronDown, ExternalLink, Gauge } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * Pengiriman Jawaban: whether the answers of this exam got to the server, in three figures that
 * tell what to improve (the Wi-Fi, the server, or the devices), and the errors themselves.
 */
const props = defineProps<{
    delivery: ExamDelivery;
}>();

type Tone = 'good' | 'warn' | 'bad' | 'none';

const TONES: Record<Tone, string> = {
    good: 'text-emerald-600',
    warn: 'text-amber-600',
    bad: 'text-rose-600',
    none: 'text-slate-400',
};

const toneOfShare = (percent: number | null): Tone => (percent === null ? 'none' : percent >= 95 ? 'good' : percent >= 80 ? 'warn' : 'bad');
const toneOfErrors = (perHundred: number | null): Tone => {
    if (perHundred === null) {
        return 'none';
    }
    return perHundred < 1 ? 'good' : perHundred < 5 ? 'warn' : 'bad';
};
const percent = (value: number | null) => (value === null ? '–' : `${value.toLocaleString('id-ID')}%`);

const figures = computed(() => [
    {
        label: 'Tersimpan di server ≤ 30 detik',
        value: percent(props.delivery.savedInTime),
        tone: toneOfShare(props.delivery.savedInTime),
        detail:
            props.delivery.savedInTimeOf > 0
                ? `dari ${props.delivery.savedInTimeOf} siswa · terlama ${props.delivery.slowestSaveSeconds ?? 0} detik`
                : 'belum ada data',
        hint: 'Rendah: Wi-Fi di ruang ujian lemah atau terlalu banyak perangkat per access point.',
    },
    {
        label: 'Terkirim pada percobaan pertama',
        value: percent(props.delivery.firstTry),
        tone: toneOfShare(props.delivery.firstTry),
        detail:
            props.delivery.firstTryOf > 0
                ? `dari ${props.delivery.firstTryOf} pengiriman${props.delivery.autoSubmitted ? ` · ${props.delivery.autoSubmitted} dikirim otomatis oleh server` : ''}`
                : 'belum ada data',
        hint: 'Rendah: server sibuk saat banyak siswa mengirim bersamaan, atau koneksi putus saat mengirim.',
    },
    {
        label: 'Galat per 100 siswa',
        value: props.delivery.errorsPer100 === null ? '–' : props.delivery.errorsPer100.toLocaleString('id-ID'),
        tone: toneOfErrors(props.delivery.errorsPer100),
        detail: `${props.delivery.errors} halaman gagal dimuat atau galat server`,
        hint: 'Tinggi: buka daftar galat di bawah untuk melihat siapa, di perangkat apa, dan penyebabnya.',
    },
]);

const errors = computed(() => props.delivery.recentErrors ?? []);
const isErrorListOpen = ref(false);
</script>

<template>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="delivery-heading" data-testid="delivery-quality">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <Gauge class="h-4 w-4" />
            </div>
            <div>
                <h3 id="delivery-heading" class="text-base font-bold text-slate-900">Pengiriman Jawaban</h3>
                <p class="text-xs text-slate-500">
                    Seberapa lancar jawaban {{ delivery.attempts }} pengerjaan sampai ke server. Pakai untuk memperbaiki ujian berikutnya.
                </p>
            </div>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div v-for="figure in figures" :key="figure.label" class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs font-bold text-slate-500">{{ figure.label }}</dt>
                <dd :class="['mt-1 text-3xl font-black tabular-nums', TONES[figure.tone]]">{{ figure.value }}</dd>
                <dd class="mt-0.5 text-[11px] text-slate-500">{{ figure.detail }}</dd>
                <dd v-if="figure.tone === 'warn' || figure.tone === 'bad'" class="mt-2 text-[11px] font-semibold text-slate-700">
                    {{ figure.hint }}
                </dd>
            </div>
        </dl>

        <div v-if="errors.length" class="mt-3 rounded-xl border border-slate-200">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-bold text-slate-800 hover:bg-slate-50"
                :aria-expanded="isErrorListOpen"
                aria-controls="delivery-errors"
                data-testid="delivery-errors-toggle"
                @click="isErrorListOpen = !isErrorListOpen"
            >
                <span>
                    Daftar galat
                    <span class="font-semibold text-slate-500"
                        >({{ delivery.errors > errors.length ? `${errors.length} terbaru dari ${delivery.errors}` : delivery.errors }})</span
                    >
                </span>
                <ChevronDown :class="['h-4 w-4 shrink-0 text-slate-500 transition', isErrorListOpen && 'rotate-180']" />
            </button>
            <div v-if="isErrorListOpen" id="delivery-errors" class="border-t border-slate-200">
                <ul class="divide-y divide-slate-100">
                    <li v-for="error in errors" :key="error.id" class="px-4 py-3 text-xs">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                            <p class="font-bold text-slate-800">
                                {{ error.person }}<span v-if="error.group" class="font-semibold text-slate-500"> · {{ error.group }}</span>
                            </p>
                            <p class="tabular-nums text-slate-500">{{ error.time }} · {{ error.kind }}</p>
                        </div>
                        <p class="mt-0.5 font-semibold text-rose-700">{{ error.cause }}</p>
                        <p class="mt-0.5 text-slate-600">{{ error.device }}</p>
                        <p v-if="error.detail" class="mt-1 break-words font-mono text-[11px] text-slate-500">{{ error.detail }}</p>
                    </li>
                </ul>
                <a
                    v-if="delivery.reportUrl"
                    :href="delivery.reportUrl"
                    class="flex items-center gap-1.5 border-t border-slate-200 px-4 py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50"
                >
                    <ExternalLink class="h-3.5 w-3.5" />
                    Semua aktivitas ujian ini di Laporan
                </a>
            </div>
        </div>
    </section>
</template>
