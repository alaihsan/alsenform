<script setup lang="ts">
import RichContent from '@/components/RichContent.vue';
import { ChevronDown, FlaskConical, Tag } from 'lucide-vue-next';
import { ref } from 'vue';

type Category = { key: string; label: string } | null;

export type ItemAnalysis = {
    respondents: number;
    reliable: boolean;
    summary: Record<string, number>;
    questions: {
        id: number | string;
        number: number;
        title: string;
        type: string;
        takers: number;
        analyzed: boolean;
        correct: number | null;
        p: number | null;
        d: number | null;
        difficulty: Category;
        discrimination: Category;
        options: { letter: string; text: string; picked: number; share: number; isKey: boolean; works: boolean | null }[];
        weakDistractors: string[];
        decision: Category;
        note: string | null;
    }[];
};

defineProps<{
    analysis: ItemAnalysis;
    isApplying: boolean;
    applyMessage: string;
}>();

defineEmits<{
    applyDifficulty: [];
}>();

const open = ref<Set<number | string>>(new Set());
const toggle = (id: number | string) => {
    const next = new Set(open.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    open.value = next;
};

const TONES: Record<string, string> = {
    mudah: 'bg-emerald-50 text-emerald-700',
    sedang: 'bg-amber-50 text-amber-700',
    sukar: 'bg-rose-50 text-rose-700',
    baik_sekali: 'bg-emerald-50 text-emerald-700',
    baik: 'bg-emerald-50 text-emerald-700',
    cukup: 'bg-amber-50 text-amber-700',
    jelek: 'bg-rose-50 text-rose-700',
    negatif: 'bg-rose-100 text-rose-800',
    dipakai: 'bg-emerald-600 text-white',
    revisi: 'bg-amber-500 text-white',
    ganti: 'bg-rose-600 text-white',
};
const tone = (category: Category) => (category ? (TONES[category.key] ?? 'bg-slate-100 text-slate-600') : 'bg-slate-100 text-slate-400');
const decimal = (value: number | null) => (value === null ? '-' : value.toFixed(2).replace('.', ','));
const percent = (value: number) => `${Math.round(value * 100)}%`;
</script>

<template>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                    <FlaskConical class="h-4 w-4 text-indigo-500" /> Analisis Butir Soal
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Tingkat kesukaran (P), daya pembeda (D, kelompok atas – bawah 27%) dan pengecoh dari jawaban {{ analysis.respondents }} siswa.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-60"
                :disabled="isApplying || analysis.respondents === 0"
                title="Label tingkat kesulitan di Bank Soal diisi dari hasil analisis"
                @click="$emit('applyDifficulty')"
            >
                <Tag class="h-3.5 w-3.5" />
                {{ isApplying ? 'Menerapkan...' : 'Jadikan label kesulitan' }}
            </button>
        </div>

        <div class="space-y-3 px-4 py-4 sm:px-5">
            <p v-if="applyMessage" class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800" role="status">
                {{ applyMessage }}
            </p>
            <p v-if="analysis.respondents > 0 && !analysis.reliable" class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                Baru {{ analysis.respondents }} siswa: tingkat kesukaran sudah terlihat, tetapi daya pembeda, pengecoh dan keputusan baru muncul
                setelah minimal 10 siswa mengerjakan soalnya.
            </p>
            <p v-if="analysis.respondents === 0" class="py-6 text-center text-sm text-slate-400">
                Analisis muncul setelah ada siswa yang mengirim jawaban.
            </p>

            <template v-else>
                <div class="flex flex-wrap gap-1.5 text-[11px] font-bold">
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">Mudah {{ analysis.summary.mudah }}</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">Sedang {{ analysis.summary.sedang }}</span>
                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">Sukar {{ analysis.summary.sukar }}</span>
                    <span class="mx-1 border-l border-slate-200"></span>
                    <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-white">Dipakai {{ analysis.summary.dipakai }}</span>
                    <span class="rounded-full bg-amber-500 px-2.5 py-1 text-white">Revisi {{ analysis.summary.revisi }}</span>
                    <span class="rounded-full bg-rose-600 px-2.5 py-1 text-white">Ganti {{ analysis.summary.ganti }}</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[720px] text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3 py-2.5">No</th>
                                <th class="px-3 py-2.5">Soal</th>
                                <th class="px-3 py-2.5 text-center" title="Siswa yang menjawab benar dari siswa yang mendapat soal ini">Benar</th>
                                <th class="px-3 py-2.5 text-center">Kesukaran (P)</th>
                                <th class="px-3 py-2.5 text-center">Daya pembeda (D)</th>
                                <th class="px-3 py-2.5 text-center">Pengecoh lemah</th>
                                <th class="px-3 py-2.5 text-center">Keputusan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="question in analysis.questions" :key="question.id">
                                <tr class="align-top">
                                    <td class="px-3 py-2.5 font-bold text-slate-500">{{ question.number }}</td>
                                    <td class="px-3 py-2.5">
                                        <button
                                            v-if="question.options.length"
                                            type="button"
                                            class="flex items-start gap-1 text-left font-semibold text-slate-800 hover:text-indigo-700"
                                            :aria-expanded="open.has(question.id)"
                                            @click="toggle(question.id)"
                                        >
                                            <ChevronDown
                                                :class="['mt-0.5 h-3.5 w-3.5 shrink-0 transition', open.has(question.id) ? 'rotate-180' : '']"
                                            />
                                            <RichContent :content="question.title" />
                                        </button>
                                        <RichContent v-else :content="question.title" class="font-semibold text-slate-800" />
                                        <span v-if="question.note" class="mt-0.5 block text-[11px] text-slate-500">{{ question.note }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-slate-600">
                                        <template v-if="question.analyzed">{{ question.correct }}/{{ question.takers }}</template>
                                        <template v-else>-</template>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="font-mono font-bold text-slate-700">{{ decimal(question.p) }}</span>
                                        <span
                                            v-if="question.difficulty"
                                            :class="['ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold', tone(question.difficulty)]"
                                            >{{ question.difficulty.label }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="font-mono font-bold text-slate-700">{{ decimal(question.d) }}</span>
                                        <span
                                            v-if="question.discrimination"
                                            :class="['ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold', tone(question.discrimination)]"
                                            >{{ question.discrimination.label }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-semibold text-amber-700">
                                        {{ question.weakDistractors.join(', ') || '-' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span
                                            v-if="question.decision"
                                            :class="['rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase', tone(question.decision)]"
                                            >{{ question.decision.label }}</span
                                        >
                                        <span v-else class="text-slate-400" :title="question.analyzed ? 'Perlu minimal 10 siswa' : undefined">-</span>
                                    </td>
                                </tr>
                                <tr v-if="open.has(question.id)" class="bg-slate-50/60">
                                    <td></td>
                                    <td colspan="6" class="px-3 pb-3 pt-1">
                                        <div class="space-y-1.5">
                                            <div v-for="option in question.options" :key="option.letter" class="flex items-center gap-2">
                                                <span class="w-5 font-bold text-slate-500">{{ option.letter }}.</span>
                                                <span :class="['w-56 truncate', option.isKey ? 'font-bold text-emerald-700' : 'text-slate-700']"
                                                    ><RichContent :content="option.text" class="!whitespace-nowrap"
                                                /></span>
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">
                                                    <div
                                                        :class="[
                                                            'h-full rounded-full',
                                                            option.isKey
                                                                ? 'bg-emerald-500'
                                                                : option.works === null
                                                                  ? 'bg-slate-400'
                                                                  : option.works
                                                                    ? 'bg-indigo-400'
                                                                    : 'bg-amber-400',
                                                        ]"
                                                        :style="{ width: percent(option.share) }"
                                                    ></div>
                                                </div>
                                                <span class="w-24 text-right text-slate-600"
                                                    >{{ option.picked }} siswa ({{ percent(option.share) }})</span
                                                >
                                                <span
                                                    class="w-24 text-[10px] font-bold"
                                                    :class="
                                                        option.isKey
                                                            ? 'text-emerald-700'
                                                            : option.works === false
                                                              ? 'text-amber-700'
                                                              : 'text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        option.isKey
                                                            ? 'Kunci'
                                                            : option.works === null
                                                              ? ''
                                                              : option.works
                                                                ? 'Berfungsi'
                                                                : 'Tidak berfungsi'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] leading-relaxed text-slate-500">
                    P &gt; 0,70 mudah, 0,31–0,70 sedang, ≤ 0,30 sukar. D &gt; 0,40 baik, 0,21–0,40 cukup (revisi), ≤ 0,20 jelek (ganti); D negatif
                    berarti siswa bernilai tinggi lebih sering salah. Pengecoh berfungsi bila dipilih minimal 5% siswa. Tiap soal dihitung dari siswa
                    yang mendapatnya (minimal 10 siswa untuk D, pengecoh dan keputusan); kelompok atas dan bawah masing-masing 27% dari mereka, dan
                    siswa bernilai sama di batas kelompok dihitung sebagian, sehingga hasilnya tidak bergantung pada urutan pengiriman. Siswa yang
                    mengerjakan lebih dari sekali dihitung dari percobaan pertamanya. Hasil yang sama ada di lembar ketiga file Excel.
                </p>
            </template>
        </div>
    </section>
</template>
