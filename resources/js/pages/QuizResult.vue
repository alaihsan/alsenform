<script setup lang="ts">
import RichContent from '@/components/RichContent.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock, Lightbulb, MessageSquare, MinusCircle, XCircle } from 'lucide-vue-next';
import { designVariables, logoJustify, pageBackgroundStyle, useDesignCss, withDesignDefaults, type FormDesign } from '@/lib/formDesign';
import { computed, ref } from 'vue';

type ResultQuestion = {
    number: number;
    title: string;
    type: string;
    media: { type: 'image' | 'video'; url: string }[];
    answer: string;
    correctAnswer: string | null;
    isCorrect: boolean | null;
    earned: number | null;
    points: number;
    waiting: boolean;
    explanation: string | null;
    feedback: string | null;
};

type Attempt = {
    number: number;
    submittedAt: string | null;
    score: number;
    max: number;
    grade: number;
    ungraded: number;
    passed: boolean;
    questions: ResultQuestion[];
};

/*
 * A student's results, once the teacher shows them: the grade of each attempt and, shown in full,
 * every question with their answer, the right answer, the pembahasan and the teacher's comment.
 */
const props = defineProps<{
    quiz: { title: string; publicUrl: string; themeColorClass: string; design?: Partial<FormDesign> | null };
    isShown: boolean;
    isFull: boolean;
    kkm: number;
    attempts: Attempt[];
    hasAttempts: boolean;
}>();

const shownNumber = ref(props.attempts.length ? props.attempts[props.attempts.length - 1].number : 0);
const shown = computed(() => props.attempts.find((attempt) => attempt.number === shownNumber.value) ?? null);
const plainTitle = computed(() => new DOMParser().parseFromString(props.quiz.title, 'text/html').body.textContent?.trim() ?? '');
const formatNumber = (value: number) => value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
const formatTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
// Desainer Form add-on: the exam's design here too.
const design = computed<FormDesign | null>(() => (props.quiz.design ? withDesignDefaults(props.quiz.design) : null));
useDesignCss(design);

const correctCount = computed(() => shown.value?.questions.filter((question) => question.isCorrect === true).length ?? 0);
</script>

<template>
    <Head :title="`Hasil: ${plainTitle}`" />

    <div
        :class="['af-page min-h-screen px-4 py-6 sm:py-10', design ? 'af-designed' : 'bg-[#f0efff]']"
        :style="design ? { ...designVariables(design), ...pageBackgroundStyle(design) } : undefined"
    >
        <main class="mx-auto max-w-3xl space-y-4">
            <Link
                :href="$page.props.auth?.user ? route('dashboard') : quiz.publicUrl"
                :class="['inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-indigo-700', design ? 'af-on-page' : '']"
            >
                <ArrowLeft class="h-4 w-4" /> {{ $page.props.auth?.user ? 'Kembali ke Beranda' : 'Kembali ke ujian' }}
            </Link>

            <img v-if="design?.headerImage" :src="design.headerImage" alt="" class="af-header-image" />
            <section class="af-card af-header overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div :class="['af-header-bar h-2', design ? 'af-accent-bg' : quiz.themeColorClass]"></div>
                <div class="p-5 sm:p-7">
                    <div v-if="design?.logo" class="mb-3 flex" :style="{ justifyContent: logoJustify(design) }">
                        <img :src="design.logo" alt="Logo" class="af-logo" />
                    </div>
                    <p class="text-xs font-extrabold uppercase tracking-widest text-indigo-600">Hasil Ujian</p>
                    <h1 :class="['mt-1 text-2xl font-black tracking-tight text-slate-900', design ? 'af-title' : '']"><RichContent :content="quiz.title" /></h1>

                    <p v-if="!isShown" class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Guru belum menampilkan hasil ujian ini. Buka lagi halaman ini nanti.
                    </p>
                    <p v-else-if="!hasAttempts" class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Belum ada jawaban Anda di ujian ini{{ $page.props.auth?.user ? '' : ' dari perangkat ini' }}.
                    </p>

                    <template v-else-if="shown">
                        <div v-if="attempts.length > 1" class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Percobaan">
                            <button
                                v-for="attempt in attempts"
                                :key="attempt.number"
                                type="button"
                                role="tab"
                                :aria-selected="attempt.number === shownNumber"
                                :class="[
                                    'rounded-full px-3 py-1 text-xs font-bold transition',
                                    attempt.number === shownNumber ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                ]"
                                @click="shownNumber = attempt.number"
                            >
                                Percobaan {{ attempt.number }} · {{ formatNumber(attempt.grade) }}
                            </button>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-1">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai</p>
                                <p :class="['mt-1 text-4xl font-black', shown.passed ? 'text-emerald-600' : 'text-rose-600']">{{ formatNumber(shown.grade) }}</p>
                                <p class="mt-0.5 text-xs font-semibold" :class="shown.passed ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ shown.passed ? 'Tuntas' : 'Belum tuntas' }} (KKM {{ formatNumber(kkm) }})
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Poin</p>
                                <p class="mt-1 text-2xl font-black text-slate-900">{{ formatNumber(shown.score) }} / {{ shown.max }}</p>
                                <p v-if="isFull" class="mt-0.5 text-xs text-slate-500">{{ correctCount }} dari {{ shown.questions.length }} soal benar</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Dikirim</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">{{ formatTime(shown.submittedAt) }}</p>
                            </div>
                        </div>

                        <p v-if="shown.ungraded" class="mt-3 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <Clock class="mt-0.5 h-4 w-4 shrink-0" />
                            {{ shown.ungraded }} jawaban esai/berkas belum dinilai guru, jadi nilai ini masih sementara.
                        </p>
                        <p v-if="!isFull" class="mt-3 text-sm text-slate-500">Guru menampilkan nilai saja; kunci jawaban tetap dirahasiakan.</p>
                    </template>
                </div>
            </section>

            <template v-if="isShown && isFull && shown">
                <article
                    v-for="question in shown.questions"
                    :key="question.number"
                    :class="[
                        'af-card af-question rounded-3xl border bg-white p-5 shadow-sm',
                        question.isCorrect === true ? 'border-emerald-200' : question.isCorrect === false ? 'border-rose-200' : 'border-slate-200',
                    ]"
                    data-testid="result-question"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">Soal {{ question.number }}</span>
                        <span
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold',
                                question.waiting
                                    ? 'bg-amber-50 text-amber-800'
                                    : question.isCorrect === true
                                      ? 'bg-emerald-50 text-emerald-700'
                                      : question.isCorrect === false
                                        ? 'bg-rose-50 text-rose-700'
                                        : 'bg-slate-100 text-slate-600',
                            ]"
                        >
                            <Clock v-if="question.waiting" class="h-3.5 w-3.5" />
                            <CheckCircle2 v-else-if="question.isCorrect === true" class="h-3.5 w-3.5" />
                            <XCircle v-else-if="question.isCorrect === false" class="h-3.5 w-3.5" />
                            <MinusCircle v-else class="h-3.5 w-3.5" />
                            {{
                                question.waiting
                                    ? 'Menunggu dinilai'
                                    : question.earned !== null
                                      ? `${formatNumber(question.earned)} / ${question.points} poin`
                                      : 'Tidak dinilai'
                            }}
                        </span>
                    </div>
                    <RichContent :content="question.title" class="mt-3 text-base text-slate-900" />
                    <div v-if="question.media?.length" class="mt-3 space-y-2">
                        <img v-for="(media, index) in question.media.filter((item) => item.type === 'image')" :key="index" :src="media.url" alt="" class="max-h-64 rounded-xl" />
                    </div>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Jawaban Anda</dt>
                            <dd class="mt-0.5 whitespace-pre-wrap text-slate-800">{{ question.answer || '(tidak dijawab)' }}</dd>
                        </div>
                        <div v-if="question.correctAnswer && question.isCorrect !== true" class="rounded-xl bg-emerald-50 px-3 py-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Jawaban benar</dt>
                            <dd class="mt-0.5 whitespace-pre-wrap text-emerald-900">{{ question.correctAnswer }}</dd>
                        </div>
                        <div v-if="question.explanation" class="rounded-xl bg-indigo-50 px-3 py-2">
                            <dt class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-indigo-700">
                                <Lightbulb class="h-3.5 w-3.5" /> Pembahasan
                            </dt>
                            <dd class="mt-0.5 whitespace-pre-wrap text-indigo-950">{{ question.explanation }}</dd>
                        </div>
                        <div v-if="question.feedback" class="rounded-xl bg-amber-50 px-3 py-2">
                            <dt class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-amber-800">
                                <MessageSquare class="h-3.5 w-3.5" /> Komentar guru
                            </dt>
                            <dd class="mt-0.5 whitespace-pre-wrap text-amber-950">{{ question.feedback }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </main>
    </div>
</template>
