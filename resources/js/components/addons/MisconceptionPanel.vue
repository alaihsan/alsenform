<script setup lang="ts">
import { AlertTriangle, Brain, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Category = 'paham' | 'menebak' | 'belumPaham' | 'miskonsepsi';

export type MisconceptionMap = {
    respondents: number;
    withoutData: number;
    usedDoubt: number;
    usageShare: number;
    trustworthy: boolean;
    flagged: number;
    questions: ({
        id: number | string;
        number: number;
        title: string;
        takers: number;
        shares: Record<Category, number>;
        flagged: boolean;
        commonWrong: { answer: string; count: number } | null;
        note: string | null;
    } & Record<Category, number>)[];
    students: { name: string; nis: string; kelas: string; miskonsepsi: number[]; belumPaham: number[] }[];
};

/*
 * Peta Miskonsepsi add-on, on the Responses tab: per question how many students understood, guessed,
 * did not understand, or were sure of a wrong answer (a misconception), from the "Ragu-ragu" marks.
 */
const props = defineProps<{
    map: MisconceptionMap;
}>();

const CATEGORIES: { key: Category; label: string; hint: string; bar: string; dot: string }[] = [
    { key: 'paham', label: 'Paham', hint: 'benar & yakin', bar: 'bg-emerald-500', dot: 'bg-emerald-500' },
    { key: 'menebak', label: 'Menebak', hint: 'benar tapi ragu', bar: 'bg-sky-400', dot: 'bg-sky-400' },
    { key: 'belumPaham', label: 'Belum paham', hint: 'salah & ragu, atau kosong', bar: 'bg-amber-400', dot: 'bg-amber-400' },
    { key: 'miskonsepsi', label: 'Miskonsepsi', hint: 'salah tapi yakin', bar: 'bg-rose-500', dot: 'bg-rose-500' },
];

const SHOWN_AT_FIRST = 8;
const isShowingAll = ref(false);

// Questions with a misconception first, the strongest at the top; then in the exam's order.
const ordered = computed(() =>
    [...props.map.questions].sort(
        (a, b) => Number(b.flagged) - Number(a.flagged) || (a.flagged ? b.shares.miskonsepsi - a.shares.miskonsepsi : 0) || a.number - b.number,
    ),
);
const shown = computed(() => (isShowingAll.value ? ordered.value : ordered.value.slice(0, SHOWN_AT_FIRST)));
const studentsWithMisconceptions = computed(() => props.map.students.filter((student) => student.miskonsepsi.length > 0).slice(0, 6));
const percent = (share: number) => `${Math.round(share * 100)}%`;
</script>

<template>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white" aria-labelledby="misconception-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <Brain class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h3 id="misconception-heading" class="text-base font-extrabold text-slate-900">Peta Miskonsepsi</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ map.respondents }} siswa (percobaan pertama) · {{ map.usedDoubt }} memakai tombol Ragu-ragu
                        <template v-if="map.withoutData"> · {{ map.withoutData }} jawaban lama tanpa data ragu tidak dihitung</template>
                    </p>
                </div>
            </div>
            <span
                :class="['rounded-full px-2.5 py-1 text-xs font-bold', map.flagged ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700']"
                >{{ map.flagged ? `${map.flagged} soal terindikasi miskonsepsi` : 'Tidak ada miskonsepsi menonjol' }}</span
            >
        </div>

        <div class="space-y-4 px-4 py-4 sm:px-5">
            <p
                v-if="map.respondents && !map.trustworthy"
                class="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-900"
                role="note"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                <span>
                    Baru {{ percent(map.usageShare) }} siswa memakai tombol Ragu-ragu. Siswa yang tidak pernah menandai dianggap yakin pada semua
                    jawabannya, jadi angka miskonsepsi bisa terlalu tinggi. Ingatkan siswa untuk menandai soal yang belum mereka yakini.
                </span>
            </p>
            <p v-if="!map.respondents" class="text-sm text-slate-500">
                Belum ada jawaban dengan tanda Ragu-ragu. Peta muncul setelah siswa mengerjakan dengan halaman ujian versi terbaru.
            </p>

            <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[11px] text-slate-600">
                <span v-for="category in CATEGORIES" :key="category.key" class="inline-flex items-center gap-1.5">
                    <span :class="['h-2.5 w-2.5 rounded-sm', category.dot]"></span>
                    <b>{{ category.label }}</b> {{ category.hint }}
                </span>
            </div>

            <ul class="space-y-3">
                <li
                    v-for="question in shown"
                    :key="question.id"
                    :class="['rounded-xl border px-3.5 py-3', question.flagged ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200']"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-sm text-slate-800">
                            <span class="font-extrabold">{{ question.number }}.</span> {{ question.title }}
                        </p>
                        <span
                            v-if="question.flagged"
                            class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-extrabold uppercase text-rose-700"
                            >Miskonsepsi {{ percent(question.shares.miskonsepsi) }}</span
                        >
                    </div>
                    <div
                        v-if="question.takers"
                        class="mt-2 flex h-3 overflow-hidden rounded-full bg-slate-100"
                        role="img"
                        :aria-label="CATEGORIES.map((category) => `${category.label} ${question[category.key]}`).join(', ')"
                    >
                        <div
                            v-for="category in CATEGORIES"
                            :key="category.key"
                            :class="category.bar"
                            :style="{ width: percent(question.shares[category.key]) }"
                            :title="`${category.label}: ${question[category.key]} siswa`"
                        ></div>
                    </div>
                    <p class="mt-1.5 text-[11px] tabular-nums text-slate-500">
                        <template v-for="(category, index) in CATEGORIES" :key="category.key"
                            >{{ index ? ' · ' : '' }}{{ category.label }} {{ question[category.key] }}</template
                        >
                    </p>
                    <p v-if="question.commonWrong && question.flagged" class="mt-1.5 text-xs text-rose-800">
                        Jawaban keliru yang paling diyakini: <b>{{ question.commonWrong.answer }}</b> ({{ question.commonWrong.count }} siswa)
                    </p>
                    <p v-if="question.note" class="mt-1 text-xs text-slate-600">{{ question.note }}</p>
                </li>
            </ul>

            <button
                v-if="ordered.length > SHOWN_AT_FIRST"
                type="button"
                class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800"
                @click="isShowingAll = !isShowingAll"
            >
                {{ isShowingAll ? 'Tampilkan lebih sedikit' : `Tampilkan semua ${ordered.length} soal` }}
                <ChevronDown :class="['h-3.5 w-3.5 transition', isShowingAll ? 'rotate-180' : '']" />
            </button>

            <div v-if="studentsWithMisconceptions.length" class="rounded-xl bg-slate-50 px-3.5 py-3">
                <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Siswa dengan miskonsepsi terbanyak</p>
                <ul class="mt-1.5 space-y-1 text-xs text-slate-700">
                    <li v-for="student in studentsWithMisconceptions" :key="`${student.nis}-${student.name}`">
                        <b>{{ student.name }}</b>
                        <span v-if="student.kelas !== '-'" class="text-slate-500"> ({{ student.kelas }})</span>: soal
                        {{ student.miskonsepsi.join(', ') }}
                    </li>
                </ul>
                <p class="mt-1.5 text-[11px] text-slate-500">Daftar lengkap per siswa ada di lembar "Peta Miskonsepsi" pada laporan Excel.</p>
            </div>
        </div>
    </section>
</template>
