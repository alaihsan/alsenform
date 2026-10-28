<script setup lang="ts">
import type { ExamDelivery } from '@/types/quiz';
import { Gauge } from 'lucide-vue-next';
import { computed } from 'vue';

/*
 * Pengiriman Jawaban: whether the answers of this exam got to the server, in three figures that
 * tell what to improve (the Wi-Fi, the server, or the devices).
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
        hint: 'Tinggi: lihat Laporan → aktivitas "Halaman gagal dimuat" dan "Galat server" untuk perangkat dan penyebabnya.',
    },
]);
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
    </section>
</template>
