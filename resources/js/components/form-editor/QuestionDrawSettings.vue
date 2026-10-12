<script setup lang="ts">
import { Shuffle } from 'lucide-vue-next';
import { computed } from 'vue';

export type QuestionDraw = {
    enabled: boolean;
    mode: 'count' | 'difficulty';
    count: number;
    perDifficulty: { mudah: number; sedang: number; sulit: number };
};

const props = defineProps<{
    draw: QuestionDraw;
    /** Ids of the questions of the exam. */
    questionIds: (number | string)[];
    /** Difficulty label of each question (from the question bank), by question id. */
    difficulties: Record<string, string | null | undefined>;
}>();

const emit = defineEmits<{
    'update:draw': [draw: QuestionDraw];
}>();

const DIFFICULTIES = [
    { key: 'mudah', label: 'Mudah' },
    { key: 'sedang', label: 'Sedang' },
    { key: 'sulit', label: 'Sulit' },
] as const;

const update = (changes: Partial<QuestionDraw>) => emit('update:draw', { ...props.draw, ...changes });
const updateDifficulty = (key: keyof QuestionDraw['perDifficulty'], value: number) =>
    update({ perDifficulty: { ...props.draw.perDifficulty, [key]: Math.max(0, Math.floor(value || 0)) } });

const available = computed(() => {
    const counts = { mudah: 0, sedang: 0, sulit: 0, none: 0 };
    props.questionIds.forEach((id) => {
        const difficulty = props.difficulties[String(id)];
        if (difficulty === 'mudah' || difficulty === 'sedang' || difficulty === 'sulit') {
            counts[difficulty]++;
        } else {
            counts.none++;
        }
    });
    return counts;
});

const perStudent = computed(() => {
    if (props.draw.mode === 'count') {
        return Math.min(props.draw.count, props.questionIds.length);
    }
    return (
        available.value.none + DIFFICULTIES.reduce((total, { key }) => total + Math.min(props.draw.perDifficulty[key] ?? 0, available.value[key]), 0)
    );
});

const warnings = computed(() => {
    const messages: string[] = [];
    if (props.draw.mode === 'count' && props.draw.count > props.questionIds.length) {
        messages.push(`Ujian ini hanya punya ${props.questionIds.length} soal: semua soal diberikan.`);
    }
    if (props.draw.mode === 'difficulty') {
        DIFFICULTIES.forEach(({ key, label }) => {
            if ((props.draw.perDifficulty[key] ?? 0) > available.value[key]) {
                messages.push(`Soal ${label.toLowerCase()} hanya ada ${available.value[key]}: semuanya diberikan.`);
            }
        });
        if (available.value.mudah + available.value.sedang + available.value.sulit === 0) {
            messages.push('Belum ada soal berlabel tingkat kesulitan. Beri label lewat tombol "Label" di tiap soal atau di halaman Bank Soal.');
        }
    }
    return messages;
});
</script>

<template>
    <div class="space-y-4 sm:pl-11">
        <div class="flex items-center justify-between gap-4 sm:gap-8">
            <div>
                <span class="flex items-center gap-2 text-base font-normal text-slate-950 sm:text-xl"
                    ><Shuffle class="h-5 w-5 text-indigo-500" /> Soal acak per siswa</span
                >
                <span class="mt-1 block text-sm text-slate-500">
                    Tiap siswa mendapat sebagian soal yang diambil acak dari ujian ini, sehingga soal teman sebelahnya berbeda.
                </span>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="draw.enabled"
                aria-label="Soal acak per siswa"
                :class="['relative h-5 w-11 shrink-0 rounded-full transition', draw.enabled ? 'bg-indigo-500' : 'bg-slate-300']"
                @click="update({ enabled: !draw.enabled })"
            >
                <span
                    :class="[
                        'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                        draw.enabled ? 'left-5' : '-left-1',
                    ]"
                ></span>
            </button>
        </div>

        <div v-if="draw.enabled" class="space-y-4 rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
            <div class="inline-flex rounded-xl bg-white p-1 text-xs font-bold shadow-sm">
                <button
                    type="button"
                    :class="['rounded-lg px-3 py-1.5', draw.mode === 'count' ? 'bg-indigo-600 text-white' : 'text-slate-600']"
                    @click="update({ mode: 'count' })"
                >
                    Jumlah soal
                </button>
                <button
                    type="button"
                    :class="['rounded-lg px-3 py-1.5', draw.mode === 'difficulty' ? 'bg-indigo-600 text-white' : 'text-slate-600']"
                    @click="update({ mode: 'difficulty' })"
                >
                    Per tingkat kesulitan
                </button>
            </div>

            <label v-if="draw.mode === 'count'" class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                Ambil
                <input
                    type="number"
                    min="0"
                    :max="questionIds.length"
                    :value="draw.count"
                    class="h-10 w-20 rounded-xl border border-slate-300 bg-white px-3 text-center font-bold"
                    aria-label="Jumlah soal per siswa"
                    @input="update({ count: Math.max(0, Math.floor(Number(($event.target as HTMLInputElement).value) || 0)) })"
                />
                dari {{ questionIds.length }} soal untuk tiap siswa.
            </label>

            <div v-else class="space-y-2">
                <label v-for="difficulty in DIFFICULTIES" :key="difficulty.key" class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                    <span class="w-16 font-bold">{{ difficulty.label }}</span>
                    ambil
                    <input
                        type="number"
                        min="0"
                        :max="available[difficulty.key]"
                        :value="draw.perDifficulty[difficulty.key]"
                        class="h-10 w-20 rounded-xl border border-slate-300 bg-white px-3 text-center font-bold"
                        :aria-label="`Jumlah soal ${difficulty.label.toLowerCase()} per siswa`"
                        @input="updateDifficulty(difficulty.key, Number(($event.target as HTMLInputElement).value))"
                    />
                    dari {{ available[difficulty.key] }} soal
                </label>
                <p v-if="available.none" class="text-xs text-slate-500">
                    {{ available.none }} soal tanpa label tingkat kesulitan selalu muncul untuk semua siswa (misalnya soal uraian).
                </p>
            </div>

            <p class="rounded-xl bg-white px-3 py-2 text-sm font-semibold text-indigo-900">
                Tiap siswa mendapat {{ perStudent }} soal. Nilai dihitung dari soal yang didapat masing-masing siswa (skala 0–100).
            </p>
            <ul v-if="warnings.length" class="list-inside list-disc space-y-1 text-xs font-semibold text-amber-800">
                <li v-for="warning in warnings" :key="warning">{{ warning }}</li>
            </ul>
            <p class="text-xs text-slate-500">Siswa yang sudah mulai mengerjakan tetap memakai soal yang sudah didapatnya.</p>
        </div>
    </div>
</template>
