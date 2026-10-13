<script setup lang="ts">
import RichContent from '@/components/RichContent.vue';
import axios from 'axios';
import { Check, EyeOff, FileText, Image as ImageIcon, PenLine, RefreshCw, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type GradingQuestion = {
    id: number | string;
    number: number;
    title: string;
    type: string;
    points: number;
    answers: number;
    graded: number;
    waiting: number;
};
type GradingAnswer = {
    responseId: number;
    name: string;
    kelas: string | null;
    attempt: number | null;
    answer: string | null;
    files: { id: string; name: string; size: number; type: string | null; url: string; isImage: boolean }[];
    autoCorrect: boolean | null;
    score: number | null;
    feedback: string | null;
    updateUrl: string;
};

/*
 * Grading by hand, one question for every student at a time: essays and files, which only the
 * teacher can grade, and short answers whose key judged too strictly. Each grade saves by itself.
 */
const props = defineProps<{
    url: string;
}>();

const emit = defineEmits<{
    close: [changed: boolean];
}>();

const questions = ref<GradingQuestion[]>([]);
const question = ref<(GradingQuestion & { description: string; answerKey: string | null; explanation: string | null }) | null>(null);
const answers = ref<GradingAnswer[]>([]);
const isLoading = ref(true);
const loadError = ref('');
const isBlind = ref(false);
const onlyWaiting = ref(false);
const hasChanged = ref(false);

/* Per answer: what is typed, and whether it is saving, saved or failed. */
const drafts = reactive<Record<number, { score: string; feedback: string; state: 'idle' | 'saving' | 'saved' | 'error'; error: string }>>({});
const timers: Record<number, ReturnType<typeof setTimeout>> = {};

const load = async (questionId?: number | string) => {
    isLoading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get(props.url, { params: questionId !== undefined ? { question: questionId } : {}, timeout: 15000 });
        questions.value = response.data.questions;
        question.value = response.data.question
            ? { ...response.data.questions.find((item: GradingQuestion) => item.id === response.data.question.id), ...response.data.question }
            : null;
        answers.value = response.data.answers;
        for (const answer of answers.value) {
            drafts[answer.responseId] = {
                score: answer.score === null ? '' : String(answer.score),
                feedback: answer.feedback ?? '',
                state: 'idle',
                error: '',
            };
        }
    } catch {
        loadError.value = 'Daftar jawaban belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => load());

const shownAnswers = computed(() => (onlyWaiting.value ? answers.value.filter((answer) => drafts[answer.responseId]?.score === '') : answers.value));
const studentLabel = (answer: GradingAnswer, index: number) =>
    isBlind.value
        ? `Siswa ${index + 1}`
        : `${answer.name}${answer.kelas ? ` · ${answer.kelas}` : ''}${answer.attempt ? ` · percobaan ${answer.attempt}` : ''}`;

const save = async (answer: GradingAnswer) => {
    const draft = drafts[answer.responseId];
    draft.state = 'saving';
    draft.error = '';
    try {
        const response = await axios.patch(answer.updateUrl, {
            question_id: question.value?.id,
            score: draft.score === '' ? null : Number(draft.score.replace(',', '.')),
            feedback: draft.feedback.trim() || null,
        });
        const wasGraded = answer.score !== null;
        answer.score = response.data.score;
        answer.feedback = response.data.feedback;
        draft.state = 'saved';
        hasChanged.value = true;
        // The counts of the question list follow.
        const summary = questions.value.find((item) => item.id === question.value?.id);
        if (summary && wasGraded !== (answer.score !== null)) {
            summary.graded += answer.score !== null ? 1 : -1;
            if (summary.type !== 'Short answer') {
                summary.waiting += answer.score !== null ? -1 : 1;
            }
        }
    } catch (exception: unknown) {
        const data = (exception as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
        draft.state = 'error';
        draft.error = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Belum tersimpan. Coba lagi.';
    }
};

const scheduleSave = (answer: GradingAnswer, delay = 600) => {
    clearTimeout(timers[answer.responseId]);
    drafts[answer.responseId].state = 'idle';
    timers[answer.responseId] = setTimeout(() => save(answer), delay);
};

const setScore = (answer: GradingAnswer, score: number) => {
    drafts[answer.responseId].score = String(score);
    scheduleSave(answer, 0);
};

const formatSize = (bytes: number) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const TYPE_LABEL: Record<string, string> = { Paragraph: 'Esai', 'File upload': 'Unggah berkas', 'Short answer': 'Isian singkat' };
</script>

<template>
    <div class="fixed inset-0 z-50 flex bg-slate-950/40 p-0 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="grading-title">
        <section class="mx-auto flex h-full w-full max-w-6xl flex-col overflow-hidden bg-white shadow-2xl sm:rounded-3xl">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><PenLine class="h-5 w-5" /></div>
                    <div>
                        <h2 id="grading-title" class="text-lg font-extrabold text-slate-900">Penilaian Esai & Isian</h2>
                        <p class="text-xs text-slate-500">Satu soal untuk semua siswa. Setiap nilai tersimpan sendiri.</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-full p-2 text-slate-400 hover:bg-slate-100"
                    aria-label="Tutup"
                    @click="emit('close', hasChanged)"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                <!-- The questions to grade -->
                <nav
                    class="flex shrink-0 gap-2 overflow-x-auto border-b border-slate-100 p-3 md:w-64 md:flex-col md:overflow-y-auto md:border-b-0 md:border-r"
                    aria-label="Soal"
                >
                    <button
                        v-for="item in questions"
                        :key="item.id"
                        type="button"
                        :class="[
                            'shrink-0 rounded-xl border px-3 py-2 text-left text-xs transition md:w-full',
                            question?.id === item.id ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50',
                        ]"
                        @click="load(item.id)"
                    >
                        <span class="block font-extrabold text-slate-800">Soal {{ item.number }} · {{ TYPE_LABEL[item.type] ?? item.type }}</span>
                        <span v-if="item.waiting" class="mt-0.5 block font-bold text-amber-700">{{ item.waiting }} belum dinilai</span>
                        <span v-else class="mt-0.5 block text-slate-500">{{ item.graded }}/{{ item.answers }} dinilai manual</span>
                    </button>
                    <p v-if="!isLoading && !questions.length" class="p-2 text-sm text-slate-500">
                        Ujian ini tidak punya soal esai, isian, atau unggah berkas.
                    </p>
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <p v-if="loadError" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ loadError }}</p>
                    <div v-if="isLoading" class="flex items-center gap-2 text-sm text-slate-500">
                        <RefreshCw class="h-4 w-4 animate-spin" /> Memuat jawaban...
                    </div>

                    <template v-else-if="question">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Soal {{ question.number }} · {{ TYPE_LABEL[question.type] ?? question.type }} · {{ question.points }} poin
                            </p>
                            <RichContent :content="question.title" class="mt-1 text-base text-slate-900" />
                            <p v-if="question.answerKey" class="mt-2 text-sm text-emerald-800">
                                Kunci: <b>{{ question.answerKey }}</b
                                >. Beri nilai bila jawaban benar tetapi ditulis berbeda dari kunci.
                            </p>
                            <p v-if="question.explanation" class="mt-2 text-sm text-slate-600"><b>Pembahasan:</b> {{ question.explanation }}</p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                :aria-pressed="isBlind"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold transition',
                                    isBlind ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                                ]"
                                @click="isBlind = !isBlind"
                            >
                                <EyeOff class="h-3.5 w-3.5" /> Sembunyikan nama (penilaian buta)
                            </button>
                            <button
                                type="button"
                                :aria-pressed="onlyWaiting"
                                :class="[
                                    'rounded-full px-3 py-1.5 text-xs font-bold transition',
                                    onlyWaiting ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                                ]"
                                @click="onlyWaiting = !onlyWaiting"
                            >
                                Hanya yang belum dinilai
                            </button>
                            <span class="text-xs text-slate-500">{{ answers.length }} jawaban</span>
                        </div>

                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="(answer, index) in shownAnswers"
                                :key="answer.responseId"
                                class="rounded-2xl border border-slate-200 p-4"
                                data-testid="grading-answer"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-bold text-slate-800">{{ studentLabel(answer, index) }}</p>
                                    <span
                                        v-if="answer.autoCorrect !== null && drafts[answer.responseId]?.score === ''"
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase',
                                            answer.autoCorrect ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700',
                                        ]"
                                    >
                                        Otomatis: {{ answer.autoCorrect ? 'benar' : 'salah' }}
                                    </span>
                                </div>

                                <p
                                    v-if="question.type !== 'File upload'"
                                    class="mt-2 whitespace-pre-wrap rounded-xl bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-800"
                                >
                                    {{ answer.answer || '(tidak dijawab)' }}
                                </p>
                                <div v-else class="mt-2 flex flex-wrap gap-2">
                                    <a
                                        v-for="file in answer.files"
                                        :key="file.id"
                                        :href="file.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="group flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs text-slate-700 hover:border-indigo-300"
                                    >
                                        <img
                                            v-if="file.isImage"
                                            :src="file.url"
                                            :alt="file.name"
                                           
                                            class="h-24 w-32 rounded-lg object-cover"
                                        />
                                        <component :is="file.isImage ? ImageIcon : FileText" v-else class="h-5 w-5 text-slate-400" />
                                        <span class="max-w-[10rem] truncate font-semibold group-hover:text-indigo-700">{{ file.name }}</span>
                                        <span class="text-slate-400">{{ formatSize(file.size) }}</span>
                                    </a>
                                    <p v-if="!answer.files.length" class="text-sm text-slate-500">(tidak ada berkas)</p>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <label class="sr-only" :for="`score-${answer.responseId}`">Nilai</label>
                                    <input
                                        :id="`score-${answer.responseId}`"
                                        v-model="drafts[answer.responseId].score"
                                        type="text"
                                        inputmode="decimal"
                                        :placeholder="`0–${question.points}`"
                                        class="w-20 rounded-xl border border-slate-300 px-3 py-1.5 text-center text-sm font-bold tabular-nums outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                        @input="scheduleSave(answer)"
                                    />
                                    <span class="text-sm text-slate-500">/ {{ question.points }}</span>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100"
                                        @click="setScore(answer, 0)"
                                    >
                                        0
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100"
                                        @click="setScore(answer, question.points / 2)"
                                    >
                                        ½
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100"
                                        @click="setScore(answer, question.points)"
                                    >
                                        Penuh
                                    </button>
                                    <span class="ml-auto text-xs" aria-live="polite">
                                        <span v-if="drafts[answer.responseId].state === 'saving'" class="text-slate-500">Menyimpan...</span>
                                        <span
                                            v-else-if="drafts[answer.responseId].state === 'saved'"
                                            class="inline-flex items-center gap-1 font-semibold text-emerald-700"
                                            ><Check class="h-3.5 w-3.5" /> Tersimpan</span
                                        >
                                        <span v-else-if="drafts[answer.responseId].state === 'error'" class="font-semibold text-rose-700">{{
                                            drafts[answer.responseId].error
                                        }}</span>
                                    </span>
                                </div>
                                <textarea
                                    v-model="drafts[answer.responseId].feedback"
                                    rows="1"
                                    maxlength="2000"
                                    placeholder="Komentar untuk siswa (opsional, tampil di halaman hasil)"
                                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleSave(answer, 900)"
                                ></textarea>
                            </li>
                        </ul>
                        <p v-if="!shownAnswers.length" class="mt-6 text-center text-sm text-slate-500">
                            {{ onlyWaiting ? 'Semua jawaban soal ini sudah dinilai.' : 'Belum ada jawaban untuk soal ini.' }}
                        </p>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>
