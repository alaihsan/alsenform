<script setup lang="ts">
import { Tag } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

export type QuestionLabels = {
    subject?: string | null;
    grade?: string | null;
    topic?: string | null;
    difficulty?: string | null;
};

export type LabelSuggestions = { subjects: string[]; grades: string[]; topics: string[] };

const props = defineProps<{
    labels: QuestionLabels | undefined;
    suggestions: LabelSuggestions;
    questionId: number | string;
    isSaving: boolean;
    error: string;
}>();

const emit = defineEmits<{
    save: [labels: QuestionLabels];
}>();

const DIFFICULTY_LABELS: Record<string, string> = { mudah: 'Mudah', sedang: 'Sedang', sulit: 'Sulit' };

const isOpen = ref(false);
const draft = reactive<Required<QuestionLabels>>({ subject: '', grade: '', topic: '', difficulty: '' });

const fillDraft = () => {
    draft.subject = props.labels?.subject ?? '';
    draft.grade = props.labels?.grade ?? '';
    draft.topic = props.labels?.topic ?? '';
    draft.difficulty = props.labels?.difficulty ?? '';
};

watch(isOpen, (open) => {
    if (open) {
        fillDraft();
    }
});

const summary = computed(() =>
    [
        props.labels?.difficulty ? DIFFICULTY_LABELS[props.labels.difficulty] : null,
        props.labels?.subject,
        props.labels?.grade,
        props.labels?.topic,
    ].filter((part): part is string => Boolean(part)),
);

const save = () => {
    emit('save', { subject: draft.subject, grade: draft.grade, topic: draft.topic, difficulty: draft.difficulty });
};

// Saved without an error: the panel closes.
watch(
    () => props.isSaving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !props.error) {
            isOpen.value = false;
        }
    },
);
</script>

<template>
    <div class="relative mr-auto" @click.stop>
        <button
            type="button"
            :class="[
                'flex max-w-[16rem] items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-bold transition',
                summary.length
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
            ]"
            :aria-expanded="isOpen"
            @click="isOpen = !isOpen"
        >
            <Tag class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ summary.length ? summary.join(' · ') : 'Label' }}</span>
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 top-full z-30 mt-2 w-72 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-xl"
        >
            <p class="text-xs font-semibold text-slate-500">Label membantu mencari soal di Bank Soal dan mengacak soal per tingkat kesulitan.</p>
            <label class="block text-xs font-bold text-slate-700">
                Tingkat kesulitan
                <select v-model="draft.difficulty" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-sm font-normal">
                    <option value="">Belum ditentukan</option>
                    <option value="mudah">Mudah</option>
                    <option value="sedang">Sedang</option>
                    <option value="sulit">Sulit</option>
                </select>
            </label>
            <label class="block text-xs font-bold text-slate-700">
                Mapel
                <input
                    v-model="draft.subject"
                    :list="`label-subjects-${questionId}`"
                    maxlength="100"
                    placeholder="Contoh: IPA"
                    class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2 text-sm font-normal"
                />
            </label>
            <label class="block text-xs font-bold text-slate-700">
                Kelas
                <input
                    v-model="draft.grade"
                    :list="`label-grades-${questionId}`"
                    maxlength="50"
                    placeholder="Contoh: 8"
                    class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2 text-sm font-normal"
                />
            </label>
            <label class="block text-xs font-bold text-slate-700">
                Bab / Tujuan Pembelajaran
                <input
                    v-model="draft.topic"
                    :list="`label-topics-${questionId}`"
                    maxlength="150"
                    placeholder="Contoh: Fotosintesis"
                    class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2 text-sm font-normal"
                />
            </label>
            <datalist :id="`label-subjects-${questionId}`"><option v-for="value in suggestions.subjects" :key="value" :value="value" /></datalist>
            <datalist :id="`label-grades-${questionId}`"><option v-for="value in suggestions.grades" :key="value" :value="value" /></datalist>
            <datalist :id="`label-topics-${questionId}`"><option v-for="value in suggestions.topics" :key="value" :value="value" /></datalist>

            <p v-if="error" class="rounded-lg bg-rose-50 p-2 text-xs font-semibold text-rose-700">{{ error }}</p>

            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100" @click="isOpen = false">
                    Batal
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="isSaving"
                    @click="save"
                >
                    {{ isSaving ? 'Menyimpan...' : 'Simpan label' }}
                </button>
            </div>
        </div>
    </div>
</template>
