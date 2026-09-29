<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Star,
    Lock,
    Unlock,
    Clock,
    Key,
    RefreshCw,
    ShieldAlert,
    ArrowLeft,
    Users,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ChevronUp,
    Send,
    LayoutGrid,
    AlertCircle,
    Maximize2,
    ZoomIn,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import RichContent from '@/components/RichContent.vue';
import MediaLightboxModal from '@/components/MediaLightboxModal.vue';

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
    media?: {
        type: 'image' | 'video';
        url: string;
        width?: string;
        align?: 'left' | 'center' | 'right';
        caption?: string;
    }[];
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
            questionsPerPage?: string | number;
        };
        submitUrl: string;
    };
    session?: {
        token?: string;
        respondent_identifier?: string;
        started_at?: string;
        expires_at?: string | null;
        server_time?: string;
        is_locked?: boolean;
    };
    accessRestricted?: boolean;
    restrictionReason?: string;
    allowedCohorts?: string[];
}>();

const answers = ref<Record<number, any>>({});
const email = ref('');
const displayQuestions = ref<Question[]>([]);
const isSubmitted = ref(false);
const isSubmitting = ref(false);
const submissionError = ref('');
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);

const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine;
};

const activeLightboxMedia = ref<{ url: string; type: 'image' | 'video'; caption?: string } | null>(null);

const openLightbox = (media: any) => {
    activeLightboxMedia.value = {
        url: media.url,
        type: media.type,
        caption: media.caption ?? '',
    };
};

// Offline Auto-Save Draft Refs & Logic
const autoSaveStatus = ref<'idle' | 'saving' | 'saved'>('idle');
const draftAnswersKey = `alsen_draft_answers_${props.quizForm.id}`;
const draftEmailKey = `alsen_draft_email_${props.quizForm.id}`;
let autoSaveTimeout: ReturnType<typeof setTimeout> | null = null;

const restoreDraft = () => {
    try {
        const savedAnswers = localStorage.getItem(draftAnswersKey);
        if (savedAnswers) {
            const parsed = JSON.parse(savedAnswers);
            if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                answers.value = parsed;
                autoSaveStatus.value = 'saved';
            }
        }
        const savedEmail = localStorage.getItem(draftEmailKey);
        if (savedEmail && !email.value) {
            email.value = savedEmail;
        }
    } catch {
        // ignore storage access error
    }
};

const clearDraft = () => {
    try {
        localStorage.removeItem(draftAnswersKey);
        localStorage.removeItem(draftEmailKey);
        autoSaveStatus.value = 'idle';
    } catch {
        // ignore
    }
};

watch(
    answers,
    (newVal) => {
        if (isSubmitted.value) return;
        autoSaveStatus.value = 'saving';
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            try {
                localStorage.setItem(draftAnswersKey, JSON.stringify(newVal));
                autoSaveStatus.value = 'saved';
            } catch {
                // ignore storage error
            }
        }, 500);
    },
    { deep: true }
);

watch(email, (newVal) => {
    if (isSubmitted.value) return;
    try {
        if (newVal) {
            localStorage.setItem(draftEmailKey, newVal);
        }
    } catch {
        // ignore
    }
});

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
    if (props.session?.respondent_identifier) {
        return props.session.respondent_identifier;
    }
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
    axios.post(`/forms/${props.quizForm.slug}/lock`, {
        respondent_identifier: respondentIdentifier,
    }).catch(() => {});

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
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    let qs = [...props.quizForm.questions];
    if (props.quizForm.settings.shuffleQuestions) {
        for (let i = qs.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [qs[i], qs[j]] = [qs[j], qs[i]];
        }
    }
    displayQuestions.value = qs;

    // Restore draft answers from localStorage if available
    restoreDraft();

    // Focus Lock (Anti-Cheat) initialization
    if (props.quizForm.settings?.lockOnBlur) {
        window.addEventListener('blur', handleBlur);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        if (props.session?.is_locked || localStorage.getItem(`is_locked_${props.quizForm.id}`) === 'true') {
            lockQuiz();
        }
    }

    // Server-enforced Time Limiter Initialization
    if (props.session?.expires_at) {
        const targetEnd = new Date(props.session.expires_at).getTime();
        const clockOffset = props.session.server_time ? new Date(props.session.server_time).getTime() - Date.now() : 0;

        const updateTimer = () => {
            const now = Date.now() + clockOffset;
            const remaining = targetEnd - now;

            if (remaining <= 0) {
                timeRemaining.value = 0;
                formattedTime.value = '00:00';
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                if (!isSubmitted.value && !isSubmitting.value) {
                    submitOnTimeout();
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
    } else {
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
                        timerInterval = null;
                    }
                    if (!isSubmitted.value && !isSubmitting.value) {
                        submitOnTimeout();
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
    }
});

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
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

const perPage = computed<number>(() => {
    const val = props.quizForm.settings?.questionsPerPage;
    if (!val || val === 'all') {
        return 0;
    }
    const parsed = parseInt(String(val), 10);
    return isNaN(parsed) || parsed <= 0 ? 0 : parsed;
});

const isPaginated = computed<boolean>(() => perPage.value > 0 && displayQuestions.value.length > perPage.value);

const currentPage = ref(1);

const totalPages = computed<number>(() => {
    if (!isPaginated.value) {
        return 1;
    }
    return Math.ceil(displayQuestions.value.length / perPage.value);
});

const currentPagedQuestions = computed<Question[]>(() => {
    if (!isPaginated.value) {
        return displayQuestions.value;
    }
    const start = (currentPage.value - 1) * perPage.value;
    return displayQuestions.value.slice(start, start + perPage.value);
});

const totalQuestionsCount = computed<number>(() => displayQuestions.value.length);

const isQuestionAnswered = (question: Question): boolean => {
    const ans = answers.value[question.id];
    if (ans === undefined || ans === null || ans === '') {
        return false;
    }
    if (Array.isArray(ans)) {
        return ans.length > 0;
    }
    if (typeof ans === 'object') {
        return Object.keys(ans).length > 0;
    }
    return true;
};

const totalAnsweredCount = computed<number>(() => {
    return displayQuestions.value.filter((q) => isQuestionAnswered(q)).length;
});

const unansweredRequiredQuestions = computed<Question[]>(() => {
    return displayQuestions.value.filter((q) => q.required && !isQuestionAnswered(q));
});

const isQuestionMapOpen = ref(true);

const isCurrentPageQuestion = (questionId: number): boolean => {
    return currentPagedQuestions.value.some((q) => q.id === questionId);
};

const getQuestionNumber = (questionId: number): number => {
    const idx = displayQuestions.value.findIndex((q) => q.id === questionId);
    return idx >= 0 ? idx + 1 : 1;
};

const scrollToQuizTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
        scrollToQuizTop();
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
        scrollToQuizTop();
    }
};

const goToQuestion = (questionId: number) => {
    const idx = displayQuestions.value.findIndex((q) => q.id === questionId);
    if (idx < 0) {
        return;
    }

    if (isPaginated.value) {
        currentPage.value = Math.floor(idx / perPage.value) + 1;
    }

    nextTick(() => {
        const el = document.getElementById(`question-card-${questionId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
};

const goToFirstUnansweredRequired = () => {
    if (unansweredRequiredQuestions.value.length === 0) {
        return;
    }
    const target = unansweredRequiredQuestions.value[0];
    showSubmitConfirmModal.value = false;
    goToQuestion(target.id);
};

const showSubmitConfirmModal = ref(false);

const openSubmitConfirmation = () => {
    if (props.quizForm.settings.collectEmail && (!email.value || !email.value.trim())) {
        validationErrors.value[0] = 'Silakan isi alamat email Anda terlebih dahulu.';
        if (isPaginated.value) {
            currentPage.value = 1;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    validateForm();
    showSubmitConfirmModal.value = true;
};

const confirmAndSubmit = () => {
    if (unansweredRequiredQuestions.value.length > 0) {
        return;
    }
    showSubmitConfirmModal.value = false;
    submitResponse();
};

const autoResizePublicTextarea = (event: Event) => {
    const el = event.target as HTMLTextAreaElement;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.max(el.scrollHeight, 112)}px`;
};

const handleShiftEnterKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && event.shiftKey) {
        setTimeout(() => {
            const el = event.target as HTMLTextAreaElement;
            if (el) {
                el.style.height = 'auto';
                el.style.height = `${Math.max(el.scrollHeight, 112)}px`;
            }
        }, 0);
    }
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

const submitOnTimeout = async (retries = 3) => {
    if (isSubmitted.value) {
        return;
    }
    isSubmitting.value = true;
    submissionError.value = '';

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            await axios.post(
                props.quizForm.submitUrl,
                {
                    email: email.value || null,
                    answers: answers.value,
                    respondent_identifier: respondentIdentifier,
                    session_token: props.session?.token ?? null,
                    is_timeout: true,
                },
                {
                    headers: { Accept: 'application/json' },
                }
            );
            isSubmitted.value = true;
            localStorage.removeItem(`is_locked_${props.quizForm.id}`);
            localStorage.removeItem(`form_start_time_${props.quizForm.id}`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        } catch (err: any) {
            console.error(`Auto-submit on timeout attempt ${attempt} failed:`, err);
            if (attempt < retries) {
                await new Promise((resolve) => setTimeout(resolve, 1500 * attempt));
            } else {
                submissionError.value =
                    'Waktu pengerjaan telah habis. Gagal mengirim jawaban otomatis karena kendala jaringan. Silakan klik tombol "Coba Kirim Ulang Jawaban" di bawah.';
            }
        } finally {
            if (isSubmitted.value) {
                isSubmitting.value = false;
            }
        }
    }
    isSubmitting.value = false;
};

const submitResponse = () => {
    if (isSubmitting.value) {
        return;
    }

    if (!validateForm()) {
        const firstErrorQuestion = props.quizForm.questions.find((q) => validationErrors.value[q.id]);
        if (firstErrorQuestion) {
            goToQuestion(firstErrorQuestion.id);
        }
        return;
    }

    isSubmitting.value = true;
    submissionError.value = '';
    router.post(
        props.quizForm.submitUrl,
        {
            email: email.value || null,
            answers: answers.value,
            respondent_identifier: respondentIdentifier,
            session_token: props.session?.token ?? null,
            is_timeout: false,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitted.value = true;
                clearDraft();
                localStorage.removeItem(`is_locked_${props.quizForm.id}`);
                localStorage.removeItem(`form_start_time_${props.quizForm.id}`);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            onError: (errors: any) => {
                if (errors && errors.error) {
                    submissionError.value = errors.error;
                } else {
                    submissionError.value =
                        'Koneksi intranet terputus atau lambat saat mengirim jawaban. Tenang, jawaban Anda tetap tersimpan aman di perangkat ini. Periksa koneksi Wi-Fi lalu klik tombol "Coba Kirim Ulang Jawaban Sekarang" di bawah.';
                }
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
    clearDraft();
    isSubmitted.value = false;
    validationErrors.value = {};
    currentPage.value = 1;
    showSubmitConfirmModal.value = false;
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
        <!-- Offline Intranet Warning Banner -->
        <div
            v-if="!isOnline"
            class="sticky top-0 z-50 flex items-center justify-center gap-2 bg-amber-500 px-4 py-2.5 text-center text-xs sm:text-sm font-bold text-white shadow-md transition-all"
        >
            <AlertCircle class="h-4 w-4 shrink-0" />
            <span>Koneksi intranet terputus. Jawaban Anda tetap tersimpan aman di perangkat. Hubungkan kembali ke Wi-Fi sekolah untuk mengirimkan.</span>
        </div>

        <!-- Access Restricted State (Cohort Restriction) -->
        <section v-if="accessRestricted" class="mx-auto max-w-xl rounded-3xl border border-amber-200 bg-white shadow-xl overflow-hidden my-8">
            <div class="h-3 bg-amber-500"></div>
            <div class="p-6 sm:p-8 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-amber-50 text-amber-600 mb-4 ring-8 ring-amber-50">
                    <ShieldAlert class="h-8 w-8" />
                </div>
                <h1 class="text-2xl font-black text-slate-900">Akses Kuis Dibatasi</h1>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                    {{ restrictionReason || 'Kuis ini hanya dapat diakses oleh murid yang terdaftar dalam kelompok (Cohort) tertentu.' }}
                </p>

                <div v-if="allowedCohorts && allowedCohorts.length > 0" class="mt-5 rounded-2xl bg-slate-50 p-4 border border-slate-200/80 text-left">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">
                        Kelompok yang Diizinkan:
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="name in allowedCohorts"
                            :key="name"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 border border-indigo-200 px-3 py-1 text-xs font-bold text-indigo-700"
                        >
                            <Users class="h-3.5 w-3.5" />
                            <span>{{ name }}</span>
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        <span>Kembali ke Dashboard</span>
                    </Link>
                </div>
            </div>
        </section>

        <section v-else-if="isSubmitted" class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
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
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex-1">
                            <div v-if="isPaginated" class="mb-2.5 inline-flex items-center gap-2 rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700">
                                <span>Bagian {{ currentPage }} dari {{ totalPages }}</span>
                            </div>
                            <h1 class="text-3xl font-semibold" :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }">
                                <RichContent :content="quizForm.title" />
                            </h1>
                        </div>
                        <div v-if="autoSaveStatus !== 'idle'" class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs text-slate-500">
                            <span v-if="autoSaveStatus === 'saving'" class="inline-flex items-center gap-1.5 text-amber-600 font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                Menyimpan draft...
                            </span>
                            <span v-else-if="autoSaveStatus === 'saved'" class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                                <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500" />
                                Draft tersimpan otomatis
                            </span>
                        </div>
                    </div>
                    <RichContent
                        v-if="quizForm.description && (!isPaginated || currentPage === 1)"
                        :content="quizForm.description"
                        as="p"
                        class="mt-3 text-slate-500"
                        :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                    />
                </div>
            </section>

            <!-- Email Collection Card -->
            <section v-if="quizForm.settings.collectEmail && (!isPaginated || currentPage === 1)" class="mx-auto mt-4 max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
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

            <!-- Question Number Navigation Map (Peta Nomor Soal) -->
            <section class="mx-auto mt-4 max-w-3xl rounded-3xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <LayoutGrid class="h-4 w-4 text-indigo-600 shrink-0" />
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Daftar Nomor Soal</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">
                            {{ totalAnsweredCount }} / {{ totalQuestionsCount }} Terjawab
                        </span>
                        <span v-if="isPaginated" class="text-xs text-slate-400 font-medium hidden sm:inline">
                            • Halaman {{ currentPage }} dari {{ totalPages }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition"
                        @click="isQuestionMapOpen = !isQuestionMapOpen"
                    >
                        <span>{{ isQuestionMapOpen ? 'Sembunyikan' : 'Buka Peta Soal' }}</span>
                        <ChevronUp v-if="isQuestionMapOpen" class="h-3.5 w-3.5" />
                        <ChevronDown v-else class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Number Grid -->
                <div v-show="isQuestionMapOpen" class="mt-3 pt-3 border-t border-slate-100">
                    <div class="flex flex-wrap gap-2 max-h-48 overflow-y-auto pr-1">
                        <button
                            v-for="(q, idx) in displayQuestions"
                            :key="q.id"
                            type="button"
                            :class="[
                                'relative flex h-9 w-9 items-center justify-center rounded-xl text-xs font-bold transition-all shadow-sm',
                                isCurrentPageQuestion(q.id) ? 'ring-2 ring-indigo-600 ring-offset-2 scale-105' : '',
                                isQuestionAnswered(q)
                                    ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                            ]"
                            :title="`Soal ${idx + 1}: ${isQuestionAnswered(q) ? 'Sudah Dijawab' : (q.required ? 'Belum Dijawab (Wajib)' : 'Belum Dijawab')}`"
                            @click="goToQuestion(q.id)"
                        >
                            <span>{{ idx + 1 }}</span>
                            <!-- Dot indicator for unanswered required question -->
                            <span
                                v-if="q.required && !isQuestionAnswered(q)"
                                class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white"
                            ></span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-md bg-emerald-500"></span>
                            <span>Sudah Dijawab</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-md bg-slate-200"></span>
                            <span>Belum Dijawab</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="relative h-3 w-3 rounded-md bg-slate-200">
                                <span class="absolute -right-0.5 -top-0.5 h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            </span>
                            <span>Wajib Diisi</span>
                        </span>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-5 max-w-3xl space-y-4">
                <article
                    v-for="question in currentPagedQuestions"
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
                        <span class="mr-2.5 inline-flex items-center justify-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                            Soal {{ getQuestionNumber(question.id) }}
                        </span>
                        <RichContent :content="question.title" class="flex-1" />
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

                    <RichContent
                        v-if="question.description"
                        :content="question.description"
                        as="p"
                        class="mt-2 text-sm text-slate-500"
                        :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                    />

                    <!-- Media elements rendering -->
                    <div v-if="question.media && question.media.length" class="mt-4 space-y-4">
                        <div
                            v-for="(media, idx) in question.media"
                            :key="idx"
                            class="flex w-full"
                            :class="{
                                'justify-start': media.align === 'left',
                                'justify-center': !media.align || media.align === 'center',
                                'justify-end': media.align === 'right',
                            }"
                        >
                            <div
                                class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 transition-all duration-200 hover:shadow-md"
                                :style="{ width: media.width ?? '100%', maxWidth: '100%' }"
                            >
                                <div
                                    v-if="media.type === 'image' && media.url"
                                    class="group relative cursor-pointer"
                                    @click="openLightbox(media)"
                                >
                                    <img
                                        :src="media.url"
                                        class="max-h-[550px] w-full object-contain p-2 transition-transform duration-200 group-hover:scale-[1.01]"
                                        loading="lazy"
                                    />
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-slate-900/30 opacity-0 backdrop-blur-[1px] transition duration-200 group-hover:opacity-100"
                                    >
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-4 py-2 text-xs font-bold text-slate-800 shadow-lg">
                                            <ZoomIn class="h-4 w-4 text-indigo-600" />
                                            Klik untuk Layar Penuh & Zoom
                                        </span>
                                    </div>
                                    <p
                                        v-if="media.caption"
                                        class="border-t border-slate-100 bg-white px-3 py-2 text-center text-xs italic text-slate-500"
                                    >
                                        {{ media.caption }}
                                    </p>
                                </div>

                                <div
                                    v-else-if="media.type === 'video' && media.url && getYoutubeEmbedUrl(media.url)"
                                    class="relative"
                                >
                                    <iframe
                                        :src="getYoutubeEmbedUrl(media.url)"
                                        class="aspect-video w-full"
                                        frameborder="0"
                                        allowfullscreen
                                    ></iframe>
                                    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600">
                                        <span class="truncate italic">{{ media.caption || 'Video Soal' }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium text-indigo-600 hover:bg-indigo-50"
                                            @click="openLightbox(media)"
                                        >
                                            <Maximize2 class="h-3.5 w-3.5" />
                                            Layar Penuh
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-else-if="media.type === 'video' && media.url"
                                    class="relative"
                                >
                                    <video
                                        :src="media.url"
                                        controls
                                        class="max-h-[500px] w-full bg-slate-950"
                                    ></video>
                                    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600">
                                        <span class="truncate italic">{{ media.caption || 'Video Soal' }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium text-indigo-600 hover:bg-indigo-50"
                                            @click="openLightbox(media)"
                                        >
                                            <Maximize2 class="h-3.5 w-3.5" />
                                            Layar Penuh
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                        class="mt-5 min-h-28 w-full resize-none overflow-hidden rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                        placeholder="Jawaban panjang Anda"
                        @input="autoResizePublicTextarea($event)"
                        @keydown="handleShiftEnterKeydown($event)"
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
                                    <th v-for="col in question.columns" :key="col" class="p-3.5 text-center font-bold text-slate-700">
                                        <RichContent :content="col" />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rIndex) in question.rows" :key="row" class="border-b border-slate-150 last:border-0 hover:bg-slate-50/70 transition-colors">
                                    <td class="p-3.5 font-semibold text-slate-800">
                                        <RichContent :content="row" />
                                    </td>
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
                            class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                            :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                        >
                            <input
                                :type="question.type === 'Checkboxes' ? 'checkbox' : 'radio'"
                                :name="`question-${question.id}`"
                                :checked="question.type === 'Checkboxes' ? isCheckboxChecked(question.id, option) : answers[question.id] === option"
                                class="mt-0.5 h-5 w-5 shrink-0 accent-indigo-600"
                                @change="question.type === 'Checkboxes' ? toggleCheckbox(question.id, option) : (answers[question.id] = option)"
                            />
                            <RichContent :content="option" class="text-slate-800 flex-1" />
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

                <!-- Submission / Timeout Error Alert -->
                <div v-if="submissionError" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 space-y-3">
                    <p class="font-semibold">{{ submissionError }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-700"
                            @click="submitResponse"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            <span>Coba Kirim Ulang Jawaban Sekarang</span>
                        </button>
                    </div>
                </div>

                <!-- Navigation Controls: Prev, Next, Submit -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <!-- Tombol Sebelumnya jika terpaginasi -->
                    <button
                        v-if="isPaginated"
                        type="button"
                        :disabled="currentPage <= 1 || isSubmitting"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="prevPage"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        <span>Sebelumnya</span>
                    </button>
                    <div v-else></div>

                    <!-- Indikator Halaman -->
                    <div v-if="isPaginated" class="text-center">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                        <span class="text-xs font-semibold text-slate-600 mt-0.5 block">
                            Soal {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, totalQuestionsCount) }} dari {{ totalQuestionsCount }}
                        </span>
                    </div>

                    <!-- Tombol Selanjutnya atau Kirim Jawaban -->
                    <div class="w-full sm:w-auto">
                        <button
                            v-if="isPaginated && currentPage < totalPages"
                            type="button"
                            :disabled="isSubmitting"
                            :class="[
                                'w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl px-7 py-3.5 font-bold text-white shadow-sm transition-all duration-300 hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60',
                                quizForm.settings.themeColorClass ?? 'bg-indigo-600',
                            ]"
                            @click="nextPage"
                        >
                            <span>Selanjutnya</span>
                            <ChevronRight class="h-4 w-4" />
                        </button>

                        <button
                            v-else
                            type="button"
                            :disabled="isSubmitting"
                            :class="[
                                'w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl px-7 py-3.5 font-bold text-white shadow-md transition-all duration-300 hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60',
                                quizForm.settings.themeColorClass ?? 'bg-indigo-600',
                            ]"
                            @click="openSubmitConfirmation"
                        >
                            <Send class="h-4 w-4" />
                            <span>{{ isSubmitting ? 'Mengirim...' : 'Kirim Jawaban' }}</span>
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </main>

    <!-- Modal Konfirmasi Kirim Jawaban -->
    <div
        v-if="showSubmitConfirmModal"
        class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm animate-in fade-in duration-200"
    >
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <Send class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Konfirmasi Pengiriman Jawaban</h3>
                        <p class="text-xs text-slate-500">Periksa kembali jawaban Anda sebelum dikirim</p>
                    </div>
                </div>
            </div>

            <!-- Stats & Progress Summary -->
            <div class="mt-5 rounded-2xl bg-slate-50 p-4 border border-slate-200/80">
                <div class="flex items-center justify-between text-sm font-bold text-slate-800">
                    <span>Status Pengerjaan</span>
                    <span :class="totalAnsweredCount === totalQuestionsCount ? 'text-emerald-600' : 'text-indigo-600'">
                        {{ totalAnsweredCount }} dari {{ totalQuestionsCount }} Soal Terjawab
                    </span>
                </div>
                <div class="mt-2.5 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="totalAnsweredCount === totalQuestionsCount ? 'bg-emerald-500' : 'bg-indigo-600'"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>
            </div>

            <!-- Warning if Unanswered Required Questions Exist -->
            <div
                v-if="unansweredRequiredQuestions.length > 0"
                class="mt-4 rounded-2xl border border-red-200 bg-red-50/80 p-4 text-xs text-red-800"
            >
                <div class="flex items-start gap-2.5 font-bold text-red-700 text-sm">
                    <AlertCircle class="h-5 w-5 shrink-0 text-red-600" />
                    <span>Ada {{ unansweredRequiredQuestions.length }} soal wajib (*) yang belum dijawab!</span>
                </div>
                <p class="mt-1.5 pl-7 text-xs text-red-600 leading-relaxed">
                    Anda harus menyelesaikan seluruh soal wajib sebelum dapat mengirimkan jawaban ujian ini.
                </p>
                <div class="mt-3 pl-7">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-red-700 transition"
                        @click="goToFirstUnansweredRequired"
                    >
                        <span>Menuju Soal Belum Terjawab</span>
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Notice if all required answered, but some optional questions unanswered -->
            <div
                v-else-if="totalAnsweredCount < totalQuestionsCount"
                class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-800"
            >
                <div class="flex items-start gap-2.5 font-bold text-amber-700 text-sm">
                    <AlertCircle class="h-5 w-5 shrink-0 text-amber-600" />
                    <span>Masih ada {{ totalQuestionsCount - totalAnsweredCount }} soal belum dijawab.</span>
                </div>
                <p class="mt-1.5 pl-7 text-xs text-amber-700 leading-relaxed">
                    Semua soal wajib sudah terisi, namun masih ada soal opsional yang kosong. Apakah Anda yakin ingin langsung mengumpulkan?
                </p>
            </div>

            <!-- Complete check message if all answered -->
            <div
                v-else
                class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs text-emerald-800"
            >
                <div class="flex items-center gap-2 font-bold text-emerald-700 text-sm">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                    <span>Luar biasa! Seluruh soal sudah selesai dijawab.</span>
                </div>
                <p class="mt-1 text-xs text-emerald-700 pl-7">
                    Pastikan Anda sudah yakin dengan semua pilihan jawaban sebelum mengirim.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button
                    type="button"
                    class="w-full sm:w-auto rounded-2xl border border-slate-300 px-5 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                    @click="showSubmitConfirmModal = false"
                >
                    Periksa Kembali
                </button>
                <button
                    type="button"
                    :disabled="isSubmitting || unansweredRequiredQuestions.length > 0"
                    :class="[
                        'w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl px-6 py-3 text-xs font-bold text-white shadow-md transition-all duration-300',
                        quizForm.settings.themeColorClass ?? 'bg-indigo-600',
                        'hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50',
                    ]"
                    @click="confirmAndSubmit"
                >
                    <Send class="h-3.5 w-3.5" />
                    <span>{{ isSubmitting ? 'Mengirim...' : 'Ya, Kirim Jawaban Sekarang' }}</span>
                </button>
            </div>
        </div>
    </div>

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
                        Permintaan buka kunci telah dikirim ke guru / pengawas. Halaman ini akan terbuka otomatis begitu disetujui oleh guru.
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

        <!-- Fullscreen Media Lightbox Modal with Zoom In/Out -->
        <MediaLightboxModal
            :show="!!activeLightboxMedia"
            :media="activeLightboxMedia"
            @close="activeLightboxMedia = null"
        />
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Fira+Code&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather&family=Montserrat:wght@400;600;700&family=Noto+Naskh+Arabic:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&family=Scheherazade+New:wght@400;700&display=swap');

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
