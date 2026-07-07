<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Star, Lock, Unlock, Clock, Key, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

type Question = {
    id: number;
    title: string;
    description: string;
    type: string;
    options: string[];
    rows?: string[];
    columns?: string[];
    answer?: any;
    required: boolean;
    media?: { type: 'image' | 'video'; url: string }[];
    points?: number;
};

const props = defineProps<{
    quizForm: {
        id: number;
        slug: string;
        title: string;
        description: string;
        questions: Question[];
        settings: {
            isQuiz?: boolean;
            collectEmail: boolean;
            showProgress: boolean;
            shuffleQuestions: boolean;
            confirmationMessage?: string;
            showSubmitAnotherResponse?: boolean;
            questionFont?: string;
            answerFont?: string;
            themeColorClass?: string;
            backgroundColorClass?: string;
            backgroundPatternClass?: string;
            lockOnBlur?: boolean;
            timeLimit?: number;
        };
        submitUrl: string;
    };
}>();

const answers = ref<Record<number, any>>({});
const email = ref('');
const displayQuestions = ref<Question[]>([]);
const isSubmitted = ref(false);
const isSubmitting = ref(false);

// Anti-Cheat (Focus Lock) Refs & Logic
const isLocked = ref(false);
const unlockRequestEmail = ref('');
const isRequestingUnlock = ref(false);
const hasRequestedUnlock = ref(false);
const unlockRequestStatus = ref<'none' | 'pending' | 'approved'>('none');
const manualUnlockCode = ref('');
const unlockError = ref('');
const showRequestSuccess = ref(false);

const getRespondentIdentifier = () => {
    let id = localStorage.getItem(`respondent_id_${props.quizForm.id}`);
    if (!id) {
        id = 'resp_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
        localStorage.setItem(`respondent_id_${props.quizForm.id}`, id);
    }
    return id;
};
const respondentIdentifier = getRespondentIdentifier();

// Time Limiter Refs & Logic
const timeRemaining = ref(0);
const formattedTime = ref('');
let timerInterval: any = null;
let pollInterval: any = null;

const checkLockStatus = async () => {
    try {
        const response = await axios.get(`/forms/${props.quizForm.slug}/unlock-requests/status/${respondentIdentifier}`);
        if (response.data.status === 'approved') {
            unlockQuiz();
        } else {
            unlockRequestStatus.value = response.data.status;
            if (response.data.status === 'pending') {
                hasRequestedUnlock.value = true;
            }
        }
    } catch (err) {
        console.error('Failed to check unlock status', err);
    }
};

const requestUnlock = async () => {
    if (isRequestingUnlock.value) {
        return;
    }
    isRequestingUnlock.value = true;
    unlockError.value = '';
    try {
        await axios.post(`/forms/${props.quizForm.slug}/unlock-requests`, {
            respondent_identifier: respondentIdentifier,
            email: unlockRequestEmail.value || email.value || null,
        });
        hasRequestedUnlock.value = true;
        unlockRequestStatus.value = 'pending';
        showRequestSuccess.value = true;
    } catch (err: any) {
        unlockError.value = err.response?.data?.message || 'Gagal mengirim permintaan buka kunci.';
    } finally {
        isRequestingUnlock.value = false;
    }
};

const verifyCode = async () => {
    if (!manualUnlockCode.value) {
        return;
    }
    unlockError.value = '';
    try {
        const response = await axios.post(`/forms/${props.quizForm.slug}/unlock`, {
            respondent_identifier: respondentIdentifier,
            code: manualUnlockCode.value,
        });
        if (response.data.success) {
            unlockQuiz();
        }
    } catch (err: any) {
        unlockError.value = err.response?.data?.message || 'Kode salah. Silakan coba lagi.';
    }
};

const lockQuiz = () => {
    if (isSubmitted.value || isSubmitting.value) {
        return;
    }
    isLocked.value = true;
    localStorage.setItem(`is_locked_${props.quizForm.id}`, 'true');
    checkLockStatus();
    if (!pollInterval) {
        pollInterval = setInterval(checkLockStatus, 5000);
    }
};

const unlockQuiz = () => {
    isLocked.value = false;
    localStorage.setItem(`is_locked_${props.quizForm.id}`, 'false');
    manualUnlockCode.value = '';
    unlockError.value = '';
    showRequestSuccess.value = false;
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const handleBlur = () => {
    if (props.quizForm.settings?.lockOnBlur) {
        lockQuiz();
    }
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden' && props.quizForm.settings?.lockOnBlur) {
        lockQuiz();
    }
};

onMounted(() => {
    let qs = [...props.quizForm.questions];
    if (props.quizForm.settings.shuffleQuestions) {
        for (let i = qs.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [qs[i], qs[j]] = [qs[j], qs[i]];
        }
    }
    displayQuestions.value = qs;

    // Focus Lock (Anti-Cheat) initialization
    if (props.quizForm.settings?.lockOnBlur) {
        window.addEventListener('blur', handleBlur);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        if (localStorage.getItem(`is_locked_${props.quizForm.id}`) === 'true') {
            lockQuiz();
        }
    }

    // Time Limiter Initialization
    const timeLimit = props.quizForm.settings?.timeLimit;
    if (timeLimit && timeLimit > 0) {
        let startTime = localStorage.getItem(`form_start_time_${props.quizForm.id}`);
        if (!startTime) {
            startTime = Date.now().toString();
            localStorage.setItem(`form_start_time_${props.quizForm.id}`, startTime);
        }
        
        const endTime = parseInt(startTime) + timeLimit * 60 * 1000;
        
        const updateTimer = () => {
            const now = Date.now();
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                timeRemaining.value = 0;
                formattedTime.value = '00:00';
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                if (!isSubmitted.value && !isSubmitting.value) {
                    submitResponse();
                }
            } else {
                timeRemaining.value = remaining;
                
                const totalSeconds = Math.floor(remaining / 1000);
                const hrs = Math.floor(totalSeconds / 3600);
                const mins = Math.floor((totalSeconds % 3600) / 60);
                const secs = totalSeconds % 60;
                
                if (hrs > 0) {
                    formattedTime.value = `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                } else {
                    formattedTime.value = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }
            }
        };
        
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
    }
});

onUnmounted(() => {
    window.removeEventListener('blur', handleBlur);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

const getYoutubeEmbedUrl = (url: string): string | undefined => {
    if (!url) {
        return undefined;
    }
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return match && match[2].length === 11 ? `https://www.youtube.com/embed/${match[2]}` : undefined;
};

const progress = computed(() => {
    if (!props.quizForm.questions.length) {
        return 0;
    }
    const answeredCount = props.quizForm.questions.filter((q) => {
        const ans = answers.value[q.id];
        if (Array.isArray(ans)) {
            return ans.length > 0;
        }
        return ans !== undefined && ans !== '';
    }).length;
    return Math.round((answeredCount / props.quizForm.questions.length) * 100);
});

const toggleCheckbox = (questionId: number, option: string) => {
    if (!Array.isArray(answers.value[questionId])) {
        answers.value[questionId] = [];
    }
    const idx = answers.value[questionId].indexOf(option);
    if (idx >= 0) {
        answers.value[questionId].splice(idx, 1);
    } else {
        answers.value[questionId].push(option);
    }
};

const isCheckboxChecked = (questionId: number, option: string) => {
    return Array.isArray(answers.value[questionId]) && answers.value[questionId].includes(option);
};

const validationErrors = ref<Record<number, string>>({});

const validateForm = (): boolean => {
    validationErrors.value = {};
    let isValid = true;

    props.quizForm.questions.forEach((q) => {
        if (!q.required) {
            return;
        }

        const ans = answers.value[q.id];

        if (q.type === 'Multiple-choice grid' || q.type === 'Tick box grid') {
            const rows = q.rows || [];
            const rowAnswers = ans || {};
            const missingRows = (rows as string[]).filter((row: string, rIndex: number) => {
                const rowAns = rowAnswers[rIndex];
                if (Array.isArray(rowAns)) {
                    return rowAns.length === 0;
                }
                return rowAns === undefined || rowAns === '';
            });

            if (missingRows.length > 0) {
                validationErrors.value[q.id] = 'Pertanyaan ini memerlukan satu tanggapan di setiap baris.';
                isValid = false;
            }
        } else if (q.type === 'Checkboxes') {
            if (!Array.isArray(ans) || ans.length === 0) {
                validationErrors.value[q.id] = 'Pertanyaan ini wajib diisi.';
                isValid = false;
            }
        } else {
            if (ans === undefined || ans === '' || ans === null) {
                validationErrors.value[q.id] = 'Pertanyaan ini wajib diisi.';
                isValid = false;
            }
        }
    });

    return isValid;
};

const selectGridAnswer = (questionId: number, rowIndex: number, colIndex: number, mode: 'single' | 'multiple') => {
    if (!answers.value[questionId] || typeof answers.value[questionId] !== 'object' || Array.isArray(answers.value[questionId])) {
        answers.value[questionId] = {};
    }
    if (mode === 'single') {
        answers.value[questionId][rowIndex] = colIndex;
    } else {
        if (!Array.isArray(answers.value[questionId][rowIndex])) {
            answers.value[questionId][rowIndex] = [];
        }
        const idx = answers.value[questionId][rowIndex].indexOf(colIndex);
        if (idx >= 0) {
            answers.value[questionId][rowIndex].splice(idx, 1);
        } else {
            answers.value[questionId][rowIndex].push(colIndex);
        }
    }
};

const submitResponse = () => {
    if (isSubmitting.value) {
        return;
    }

    if (!validateForm()) {
        const firstErrorQuestion = props.quizForm.questions.find((q) => validationErrors.value[q.id]);
        if (firstErrorQuestion) {
            const el = document.getElementById(`question-card-${firstErrorQuestion.id}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        return;
    }

    isSubmitting.value = true;
    router.post(
        props.quizForm.submitUrl,
        {
            email: email.value || null,
            answers: answers.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitted.value = true;
                localStorage.removeItem(`is_locked_${props.quizForm.id}`);
                localStorage.removeItem(`form_start_time_${props.quizForm.id}`);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const submitAnotherResponse = () => {
    answers.value = {};
    email.value = '';
    isSubmitted.value = false;
    validationErrors.value = {};
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
</script>

<template>
    <Head :title="quizForm.title" />

    <!-- Floating Countdown Timer -->
    <div
        v-if="quizForm.settings.timeLimit && quizForm.settings.timeLimit > 0 && !isSubmitted"
        class="fixed right-4 top-16 z-40 flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-3 shadow-lg backdrop-blur-md transition-all sm:right-8 sm:top-8"
        :class="{ 'animate-pulse border-red-200 bg-red-50/80 text-red-600': timeRemaining < 60000 }"
    >
        <Clock class="h-5 w-5" :class="{ 'text-red-500 animate-spin': timeRemaining < 60000, 'text-indigo-600': timeRemaining >= 60000 }" />
        <div>
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400" :class="{ 'text-red-400': timeRemaining < 60000 }">Sisa Waktu</span>
            <span class="block font-mono text-lg font-black leading-none">{{ formattedTime }}</span>
        </div>
    </div>

    <!-- Progress bar at the top of the page if enabled -->
    <div v-if="quizForm.settings.showProgress" class="fixed left-0 right-0 top-0 z-50 h-2 bg-slate-100">
        <div
            :class="['h-full transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"
            :style="{ width: `${progress}%` }"
        ></div>
    </div>

    <main
        :class="[
            'min-h-screen px-4 py-8 text-slate-900 transition-all duration-300 sm:px-6',
            quizForm.settings.backgroundColorClass ?? 'bg-violet-50',
            quizForm.settings.backgroundPatternClass ?? 'pattern-none',
        ]"
    >
        <section v-if="isSubmitted" class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div :class="['h-3 rounded-t-3xl transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"></div>
            <div class="p-6 sm:p-8">
                <h1 class="text-3xl font-semibold" :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }">
                    {{ quizForm.settings.confirmationMessage ?? 'Your response has been recorded' }}
                </h1>
                <button
                    v-if="quizForm.settings.showSubmitAnotherResponse ?? true"
                    type="button"
                    class="mt-6 rounded-2xl border border-slate-300 px-5 py-3 font-bold text-slate-700 transition hover:bg-slate-50"
                    @click="submitAnotherResponse"
                >
                    Submit another response
                </button>
            </div>
        </section>

        <template v-else>
            <section class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div :class="['h-3 rounded-t-3xl transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"></div>
                <div class="p-6 sm:p-8">
                    <h1 class="text-3xl font-semibold" :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }">{{ quizForm.title }}</h1>
                    <p v-if="quizForm.description" class="mt-3 text-slate-500" :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }">
                        {{ quizForm.description }}
                    </p>
                </div>
            </section>

            <!-- Email Collection Card -->
            <section v-if="quizForm.settings.collectEmail" class="mx-auto mt-4 max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <label class="block">
                    <span class="text-base font-semibold text-slate-900">Email <span class="text-red-500">*</span></span>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="Masukkan alamat email Anda"
                        class="mt-3 w-full rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                </label>
            </section>

            <section class="mx-auto mt-5 max-w-3xl space-y-4">
                <article
                    v-for="question in displayQuestions"
                    :key="question.id"
                    :id="`question-card-${question.id}`"
                    :class="[
                        'rounded-3xl border p-6 bg-white shadow-sm transition-all duration-300',
                        validationErrors[question.id] ? 'border-red-400 bg-red-50/5 ring-2 ring-red-100' : 'border-slate-200'
                    ]"
                >
                    <h2
                        class="flex flex-wrap items-center text-lg font-semibold"
                        :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }"
                    >
                        <span>{{ question.title }}</span>
                        <span v-if="question.required" class="ml-1 text-red-500">*</span>
                        <span
                            v-if="quizForm.settings.isQuiz !== false && question.points"
                            class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700"
                        >
                            {{ question.points }} Poin
                        </span>
                    </h2>
                    
                    <div v-if="validationErrors[question.id]" class="mt-2 text-xs font-bold text-red-600 flex items-center gap-1.5 animate-in fade-in duration-150">
                        <span class="block h-1.5 w-1.5 rounded-full bg-red-600"></span>
                        {{ validationErrors[question.id] }}
                    </div>

                    <p
                        v-if="question.description"
                        class="mt-2 text-sm text-slate-500"
                        :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                    >
                        {{ question.description }}
                    </p>

                    <!-- Media elements rendering -->
                    <div v-if="question.media && question.media.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div v-for="(media, idx) in question.media" :key="idx" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <img v-if="media.type === 'image' && media.url" :src="media.url" class="max-h-72 w-full object-contain p-2" />
                            <iframe
                                v-else-if="media.type === 'video' && media.url && getYoutubeEmbedUrl(media.url)"
                                :src="getYoutubeEmbedUrl(media.url)"
                                class="aspect-video w-full"
                                frameborder="0"
                                allowfullscreen
                            ></iframe>
                            <video
                                v-else-if="media.type === 'video' && media.url"
                                :src="media.url"
                                controls
                                class="max-h-72 w-full bg-slate-950"
                            ></video>
                        </div>
                    </div>

                    <input
                        v-if="question.type === 'Short answer'"
                        v-model="answers[question.id]"
                        type="text"
                        class="mt-5 w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                        placeholder="Jawaban singkat Anda"
                    />
                    <textarea
                        v-else-if="question.type === 'Paragraph'"
                        v-model="answers[question.id]"
                        class="mt-5 min-h-28 w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                        placeholder="Jawaban panjang Anda"
                    ></textarea>
                    <select
                        v-else-if="question.type === 'Drop-down'"
                        v-model="answers[question.id]"
                        class="mt-5 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    >
                        <option value="">Pilih jawaban</option>
                        <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                    </select>
                    <div
                        v-else-if="question.type === 'Linear scale'"
                        class="mt-5 flex items-center justify-between gap-2 rounded-2xl bg-slate-50 p-4"
                    >
                        <button
                            v-for="option in question.options"
                            :key="option"
                            type="button"
                            :class="[
                                'flex h-11 w-11 items-center justify-center rounded-full border text-sm font-bold transition',
                                answers[question.id] === option
                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-indigo-400',
                            ]"
                            @click="answers[question.id] = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                    <div v-else-if="question.type === 'Rating'" class="mt-5 flex gap-3 rounded-2xl bg-slate-50 p-4">
                        <button
                            v-for="option in question.options"
                            :key="option"
                            type="button"
                            :class="[
                                'transition',
                                Number(answers[question.id]) >= Number(option) ? 'text-amber-400' : 'text-slate-300 hover:text-amber-300',
                            ]"
                            @click="answers[question.id] = option"
                        >
                            <Star class="h-10 w-10 fill-current" />
                        </button>
                    </div>
                    
                    <!-- Grid Question Types Rendering -->
                    <div v-else-if="['Multiple-choice grid', 'Tick box grid'].includes(question.type)" class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-50/50">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100">
                                    <th class="p-3.5 font-bold text-slate-700">Baris / Kolom</th>
                                    <th v-for="col in question.columns" :key="col" class="p-3.5 text-center font-bold text-slate-700">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rIndex) in question.rows" :key="row" class="border-b border-slate-150 last:border-0 hover:bg-slate-50/70 transition-colors">
                                    <td class="p-3.5 font-semibold text-slate-800">{{ row }}</td>
                                    <td v-for="(col, cIndex) in question.columns" :key="col" class="p-3.5 text-center">
                                        <label class="inline-flex items-center justify-center cursor-pointer">
                                            <input
                                                :type="question.type === 'Tick box grid' ? 'checkbox' : 'radio'"
                                                :name="`question-grid-row-${question.id}-${rIndex}`"
                                                :checked="question.type === 'Tick box grid' ? (answers[question.id]?.[rIndex]?.includes(cIndex)) : (answers[question.id]?.[rIndex] === cIndex)"
                                                class="h-5 w-5 accent-indigo-600 cursor-pointer"
                                                @change="selectGridAnswer(question.id, rIndex, cIndex, question.type === 'Tick box grid' ? 'multiple' : 'single')"
                                            />
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else-if="question.options?.length" class="mt-5 space-y-3">
                        <label
                            v-for="option in question.options"
                            :key="option"
                            class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                            :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                        >
                            <input
                                :type="question.type === 'Checkboxes' ? 'checkbox' : 'radio'"
                                :name="`question-${question.id}`"
                                :checked="question.type === 'Checkboxes' ? isCheckboxChecked(question.id, option) : answers[question.id] === option"
                                class="h-5 w-5 accent-indigo-600"
                                @change="question.type === 'Checkboxes' ? toggleCheckbox(question.id, option) : (answers[question.id] = option)"
                            />
                            <span class="text-slate-800">{{ option }}</span>
                        </label>
                    </div>
                    <input
                        v-else-if="question.type === 'Date'"
                        v-model="answers[question.id]"
                        type="date"
                        class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                    <input
                        v-else-if="question.type === 'Time'"
                        v-model="answers[question.id]"
                        type="time"
                        class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                    <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Area jawaban</div>
                </article>

                <button
                    type="button"
                    :disabled="isSubmitting"
                    :class="[
                        'rounded-2xl px-6 py-3 font-bold text-white shadow-sm transition-all duration-300',
                        quizForm.settings.themeColorClass ?? 'bg-indigo-600',
                        'hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60',
                    ]"
                    @click="submitResponse"
                >
                    {{ isSubmitting ? 'Submitting...' : 'Submit' }}
                </button>
            </section>
        </template>
    </main>

    <!-- Locked Screen Overlay -->
    <div v-if="isLocked" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-md">
        <section class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900/90 p-8 text-center text-white shadow-2xl backdrop-blur-xl">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-red-500/10 text-red-500">
                <Lock class="h-8 w-8 animate-bounce" />
            </div>
            
            <h2 class="mt-6 text-2xl font-black tracking-tight text-white">Kuis Terkunci</h2>
            <p class="mt-3 text-sm text-slate-400">
                Sistem mendeteksi bahwa Anda telah memindahkan tab atau mem-blur jendela pengerjaan kuis.
            </p>

            <div class="mt-8 border-t border-slate-800/80 pt-6 space-y-6">
                <!-- Request Status -->
                <div v-if="hasRequestedUnlock" class="rounded-2xl bg-slate-800/50 p-4 border border-slate-700/30">
                    <div class="flex items-center justify-center gap-2 text-amber-400 font-bold text-sm">
                        <RefreshCw class="h-4 w-4 animate-spin" />
                        <span>Menunggu Persetujuan...</span>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400 leading-relaxed">
                        Permintaan buka kunci telah dikirim. Halaman ini akan terbuka otomatis begitu disetujui oleh pembuat kuis.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <p class="text-xs text-slate-500 text-left">Minta persetujuan secara otomatis ke pembuat kuis:</p>
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 px-4 shadow-lg transition-all"
                        :disabled="isRequestingUnlock"
                        @click="requestUnlock"
                    >
                        <RefreshCw v-if="isRequestingUnlock" class="h-4 w-4 animate-spin" />
                        <span>Minta Kode Buka Kunci</span>
                    </button>
                </div>

                <!-- Manual Unlock Code Entry -->
                <div class="space-y-3 pt-2">
                    <label for="manual-code" class="block text-left text-xs text-slate-500">Atau masukkan kode buka kunci 6-digit secara manual:</label>
                    <div class="flex gap-2">
                        <input
                            id="manual-code"
                            v-model="manualUnlockCode"
                            type="text"
                            placeholder="Contoh: 123456"
                            class="h-12 flex-1 rounded-2xl border border-slate-800 bg-slate-950 px-4 text-center font-mono text-lg font-black tracking-wider text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none"
                            @keyup.enter="verifyCode"
                        />
                        <button
                            type="button"
                            class="rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 transition-colors"
                            @click="verifyCode"
                        >
                            Verifikasi
                        </button>
                    </div>
                </div>

                <!-- Error Messages -->
                <div v-if="unlockError" class="rounded-xl bg-red-500/10 border border-red-500/20 p-3.5 text-xs text-red-400 font-medium">
                    {{ unlockError }}
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fira+Code&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather&family=Montserrat:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap');

.pattern-none {
    background-image: none;
}
.pattern-dots {
    background-image: radial-gradient(rgba(99, 102, 241, 0.15) 1.5px, transparent 1.5px);
    background-size: 20px 20px;
}
.pattern-grid {
    background-image:
        linear-gradient(rgba(99, 102, 241, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, 0.08) 1px, transparent 1px);
    background-size: 20px 20px;
}
.pattern-diagonal {
    background-image: repeating-linear-gradient(45deg, rgba(99, 102, 241, 0.05) 0px, rgba(99, 102, 241, 0.05) 2px, transparent 2px, transparent 10px);
}
.pattern-waves {
    background-image:
        radial-gradient(
            circle at 100% 150%,
            transparent 24%,
            rgba(99, 102, 241, 0.06) 24%,
            rgba(99, 102, 241, 0.06) 28%,
            transparent 28%,
            transparent
        ),
        radial-gradient(circle at 0% 150%, transparent 24%, rgba(99, 102, 241, 0.06) 24%, rgba(99, 102, 241, 0.06) 28%, transparent 28%, transparent);
    background-size: 20px 20px;
}
.pattern-zigzag {
    background-image:
        linear-gradient(135deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
        linear-gradient(225deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%), linear-gradient(45deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
        linear-gradient(315deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%);
    background-position:
        10px 0,
        10px 0,
        0 0,
        0 0;
    background-size: 20px 20px;
    background-repeat: repeat;
}
.pattern-hexagons {
    background-image:
        radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 100% 0%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 100% 100%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 0% 100%, rgba(99, 102, 241, 0.06) 10%, transparent 10%);
    background-size: 30px 30px;
}
.pattern-blueprint {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.2) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.2) 1px, transparent 1px);
    background-size: 20px 20px;
}
.pattern-bubbles {
    background-image:
        radial-gradient(circle, rgba(99, 102, 241, 0.04) 20%, transparent 20%), radial-gradient(circle, rgba(99, 102, 241, 0.05) 15%, transparent 15%);
    background-size: 40px 40px;
    background-position:
        0 0,
        20px 20px;
}
.pattern-triangles {
    background-image:
        linear-gradient(
            30deg,
            rgba(99, 102, 241, 0.04) 12%,
            transparent 12.5%,
            transparent 87%,
            rgba(99, 102, 241, 0.04) 87.5%,
            rgba(99, 102, 241, 0.04)
        ),
        linear-gradient(
            150deg,
            rgba(99, 102, 241, 0.04) 12%,
            transparent 12.5%,
            transparent 87%,
            rgba(99, 102, 241, 0.04) 87.5%,
            rgba(99, 102, 241, 0.04)
        ),
        linear-gradient(
            270deg,
            rgba(99, 102, 241, 0.04) 25%,
            transparent 25.5%,
            transparent 74%,
            rgba(99, 102, 241, 0.04) 74.5%,
            rgba(99, 102, 241, 0.04)
        );
    background-size: 30px 52px;
}
</style>
