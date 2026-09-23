<script setup lang="ts">
import RichContent from '@/components/RichContent.vue';
import MathArabicToolbar from '@/components/form-editor/MathArabicToolbar.vue';
import UnlockRequestsPanel from '@/components/form-editor/UnlockRequestsPanel.vue';
import {
    backgroundPatterns,
    colorThemes,
    fonts,
    gridQuestionTypes,
    noOptionQuestionTypes,
    optionQuestionTypes,
    pieColors,
    questionTypes,
    scaleQuestionTypes,
    templatePresets,
} from '@/constants/form-editor';
import type { PreviewAnswer, Question, QuestionType, QuizFormPayload } from '@/types/quiz';
import { hasArabic, hasMathOrArabic } from '@/utils/rich-content';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    Archive,
    Check,
    ChevronDown,
    Circle,
    Download,
    Eye,
    FileText,
    FileUp,
    Image,
    Key,
    Link2,
    MoreVertical,
    Palette,
    PanelTop,
    PlusCircle,
    Puzzle,
    Redo2,
    School,
    Star,
    ToggleRight,
    Trash2,
    Type,
    Undo2,
    UploadCloud,
    UserPlus,
    Video,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const getYoutubeEmbedUrl = (url: string): string | undefined => {
    if (!url) {
        return undefined;
    }
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return match && match[2].length === 11 ? `https://www.youtube.com/embed/${match[2]}` : undefined;
};

const props = defineProps<{
    template: string;
    quizForm?: QuizFormPayload;
}>();

const preset = templatePresets[props.template] ?? templatePresets.blank;
let nextQuestionId = Math.max(...(props.quizForm?.questions?.map((question: Question) => Number(question.id)) ?? [1])) + 1;

const form = reactive({
    title: props.quizForm?.title ?? preset.title,
    description: props.quizForm?.description ?? preset.description,
    questions: [
        ...(props.quizForm?.questions?.map((q) => ({
            id: q.id,
            title: q.title,
            description: q.description ?? '',
            type: q.type,
            options: q.options ? [...q.options] : [],
            rows: q.rows ? [...q.rows] : [],
            columns: q.columns ? [...q.columns] : [],
            answer: q.answer,
            required: Boolean(q.required),
            media: (q.media ?? []).map((m: any) => ({ type: m.type, url: m.url ?? '' })),
            points: q.points ?? 10,
        })) ?? [
            {
                id: 1,
                title: preset.question,
                description: '',
                type: 'Multiple choice' as QuestionType,
                options: [...preset.options],
                answer: '',
                required: Boolean(props.quizForm?.settings?.defaultQuestionRequired ?? false),
                media: [],
                points: props.quizForm?.settings?.defaultQuestionPoints ?? 10,
            },
        ]),
    ] as Question[],
    settings: {
        collectEmail: props.quizForm?.settings?.collectEmail ?? false,
        showProgress: props.quizForm?.settings?.showProgress ?? true,
        shuffleQuestions: props.quizForm?.settings?.shuffleQuestions ?? false,
        isQuiz: props.quizForm?.settings?.isQuiz ?? true,
        emailCollectionMode: props.quizForm?.settings?.emailCollectionMode ?? (props.quizForm?.settings?.collectEmail ? 'responder' : 'none'),
        sendResponseCopy: props.quizForm?.settings?.sendResponseCopy ?? 'off',
        allowResponseEditing: props.quizForm?.settings?.allowResponseEditing ?? false,
        limitOneResponse: props.quizForm?.settings?.limitOneResponse ?? false,
        confirmationMessage: props.quizForm?.settings?.confirmationMessage ?? 'Your response has been recorded',
        showSubmitAnotherResponse: props.quizForm?.settings?.showSubmitAnotherResponse ?? true,
        showResultsSummary: props.quizForm?.settings?.showResultsSummary ?? false,
        disableRespondentAutosave: props.quizForm?.settings?.disableRespondentAutosave ?? false,
        defaultCollectEmailMode: props.quizForm?.settings?.defaultCollectEmailMode ?? 'none',
        defaultQuestionRequired: props.quizForm?.settings?.defaultQuestionRequired ?? false,
        defaultQuestionPoints: props.quizForm?.settings?.defaultQuestionPoints ?? 10,
        maxUploadSize: Math.min(props.quizForm?.settings?.maxUploadSize ?? 20, 40),
        questionFont: props.quizForm?.settings?.questionFont ?? "'Inter', sans-serif",
        answerFont: props.quizForm?.settings?.answerFont ?? "'Inter', sans-serif",
        themeColorClass: props.quizForm?.settings?.themeColorClass ?? 'bg-indigo-600',
        backgroundColorClass: props.quizForm?.settings?.backgroundColorClass ?? 'bg-[#f0efff]',
        backgroundPatternClass: props.quizForm?.settings?.backgroundPatternClass ?? 'pattern-none',
        lockOnBlur: props.quizForm?.settings?.lockOnBlur ?? false,
        timeLimit: props.quizForm?.settings?.timeLimit ?? 0,
    },
});

const activeTab = ref<'questions' | 'responses' | 'settings' | 'keamanan'>('questions');
const activeQuestionId = ref(1);
const selectedCohortIds = ref<number[]>([...(props.quizForm?.cohortIds ?? [])]);
const isCohortRestricted = ref(selectedCohortIds.value.length > 0);
const isCohortSettingsOpen = ref(true);

const toggleCohort = (id: number) => {
    if (selectedCohortIds.value.includes(id)) {
        selectedCohortIds.value = selectedCohortIds.value.filter((cid) => cid !== id);
    } else {
        selectedCohortIds.value.push(id);
    }
    markChanged('Cohort updated');
};

const setCohortRestriction = (restricted: boolean) => {
    isCohortRestricted.value = restricted;
    if (!restricted) {
        selectedCohortIds.value = [];
    }
    markChanged('Cohort restriction updated');
};
const showPreview = ref(false);
const showPublish = ref(false);
const showMoreMenu = ref(false);
const showPalette = ref(false);
const openTypeMenuQuestionId = ref<number | null>(null);
const editingAnswerKeyQuestionId = ref<number | null>(null);
const draggedQuestionId = ref<number | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const docxFileInput = ref<HTMLInputElement | null>(null);
const examviewFileInput = ref<HTMLInputElement | null>(null);
const importSource = ref<'examview' | 'docx'>('examview');
const isImportModalOpen = ref(false);
const isImportingFile = ref(false);
const importError = ref('');
const page = usePage<any>();
const currentUser = computed(() => page.props.auth?.user);
const isCollaboratorModalOpen = ref(false);
const selectedTeacherId = ref<number | ''>('');
const isInvitingCollaborator = ref(false);
const collaboratorError = ref('');

const openCollaboratorModal = () => {
    if (!props.quizForm?.id) {
        triggerToast('Simpan kuis terlebih dahulu sebelum mengundang kolaborator.');
        return;
    }
    collaboratorError.value = '';
    selectedTeacherId.value = '';
    isCollaboratorModalOpen.value = true;
};

const inviteCollaborator = () => {
    if (!selectedTeacherId.value || !props.quizForm?.inviteCollaboratorUrl) {
        return;
    }

    isInvitingCollaborator.value = true;
    collaboratorError.value = '';

    router.post(
        props.quizForm.inviteCollaboratorUrl,
        { user_id: selectedTeacherId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedTeacherId.value = '';
                triggerToast('Kolaborator berhasil ditambahkan');
            },
            onError: (errors: any) => {
                collaboratorError.value = errors.user_id || 'Gagal menambahkan kolaborator.';
            },
            onFinish: () => {
                isInvitingCollaborator.value = false;
            },
        },
    );
};

const removeCollaborator = (collaboratorId: number, isSelf = false) => {
    if (!props.quizForm?.id) {
        return;
    }

    const confirmMsg = isSelf ? 'Apakah Anda yakin ingin keluar dari kolaborasi kuis ini?' : 'Hapus guru ini dari daftar kolaborator?';

    if (!window.confirm(confirmMsg)) {
        return;
    }

    router.delete(
        route('forms.collaborators.destroy', {
            quizForm: props.quizForm.id,
            user: collaboratorId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast(isSelf ? 'Anda telah keluar dari kolaborasi' : 'Kolaborator berhasil dihapus');
                if (isSelf) {
                    router.visit(route('dashboard'));
                }
            },
            onError: () => {
                triggerToast('Gagal menghapus kolaborator');
            },
        },
    );
};

const statusMessage = ref('All changes saved locally');
const showThemeSidebar = ref(false);
const previewAnswers = reactive<Record<number, PreviewAnswer>>({});
const appOrigin = ref(typeof window === 'undefined' ? '' : window.location.origin);
const publicSlug = ref(props.quizForm?.slug ?? 'untitled-form');
const publicUrl = ref(props.quizForm?.publicUrl ?? (appOrigin.value ? `${appOrigin.value}/forms/untitled-form` : '/forms/untitled-form'));
const slugWarning = ref('');
const isSaving = ref(false);
const isPublished = ref(props.quizForm?.isPublished ?? false);
const showStatusMenu = ref(false);
const isResponsesSettingsOpen = ref(true);
const isPresentationSettingsOpen = ref(true);
const isFormDefaultsOpen = ref(true);
const isQuestionDefaultsOpen = ref(true);
const isSecuritySettingsOpen = ref(true);

const unlockRequests = ref<any[]>([]);

const fetchUnlockRequests = async () => {
    if (!props.quizForm?.id) {
        return;
    }
    try {
        const response = await axios.get(`/forms/${props.quizForm.id}/unlock-requests`);
        unlockRequests.value = response.data.requests || [];
    } catch (error) {
        console.error('Failed to fetch unlock requests', error);
    }
};

const approveUnlockRequest = async (requestId: number) => {
    try {
        await axios.post(`/unlock-requests/${requestId}/approve`);
        fetchUnlockRequests();
        markChanged('Unlock request approved');
    } catch (error) {
        console.error('Failed to approve unlock request', error);
    }
};

let unlockRequestsInterval: any = null;
onMounted(() => {
    unlockRequestsInterval = setInterval(() => {
        if (activeTab.value === 'keamanan') {
            fetchUnlockRequests();
        }
    }, 5000);
});

onUnmounted(() => {
    if (unlockRequestsInterval) {
        clearInterval(unlockRequestsInterval);
    }
});

watch(activeTab, (newTab) => {
    if (newTab === 'keamanan') {
        fetchUnlockRequests();
    }
});

const markSettingsChanged = () => {
    form.settings.collectEmail = form.settings.emailCollectionMode !== 'none';
    markChanged('Settings updated');
};

const toggleSetting = (key: keyof typeof form.settings) => {
    const currentValue = Boolean(form.settings[key]);
    (form.settings as Record<string, unknown>)[key as string] = !currentValue;
    markSettingsChanged();
};

const questionTypeIcon = (type: QuestionType) => questionTypes.find((questionType) => questionType.value === type)?.icon ?? Circle;

const activeQuestion = computed(() => form.questions.find((question: Question) => question.id === activeQuestionId.value) ?? form.questions[0]);
const responseCount = computed(() => props.quizForm?.responses?.total ?? 0);
const shareUrl = computed(() => publicUrl.value);
const responseQuestions = computed(() => props.quizForm?.responses?.questions ?? []);
const latestResponses = computed(() => props.quizForm?.responses?.latest ?? []);

const gradebookSearch = ref('');
const maxScore = computed(() => props.quizForm?.responses?.maxScore ?? 0);
const exportResponsesUrl = computed(
    () =>
        props.quizForm?.responses?.exportUrl ??
        props.quizForm?.exportResponsesUrl ??
        (props.quizForm ? `/forms/${props.quizForm.id}/responses/export` : '#'),
);
const gradebook = computed(() => props.quizForm?.responses?.gradebook ?? []);

const filteredGradebook = computed(() => {
    if (!gradebookSearch.value.trim()) {
        return gradebook.value;
    }
    const q = gradebookSearch.value.toLowerCase().trim();
    return gradebook.value.filter((item) => {
        return (
            item.name.toLowerCase().includes(q) ||
            item.nis.toLowerCase().includes(q) ||
            item.kelas.toLowerCase().includes(q) ||
            item.email.toLowerCase().includes(q)
        );
    });
});

const pieChartStyle = (question: NonNullable<QuizFormPayload['responses']>['questions'][number]) => {
    if (!question.total || !question.options.length) {
        return {
            background: '#e2e8f0',
        };
    }

    let cursor = 0;
    const segments = question.options.map((option, index) => {
        const start = cursor;
        const size = (option.count / question.total) * 100;
        cursor += size;

        return `${pieColors[index % pieColors.length]} ${start}% ${cursor}%`;
    });

    return {
        background: `conic-gradient(${segments.join(', ')})`,
    };
};

const debouncedRecordHistory = useDebounceFn(() => {
    recordHistory();
}, 500);

const markChanged = (message = 'Draft updated') => {
    statusMessage.value = message;
    debouncedRecordHistory();
};

const normalizePublicSlug = () => {
    publicSlug.value = publicSlug.value.replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'untitled-form';
    slugWarning.value = '';
};

const generateComplexSlug = (): string => {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';

    while (result.length < 59) {
        const remaining = 59 - result.length;
        let groupSize = Math.floor(Math.random() * (10 - 7 + 1)) + 7;

        if (groupSize >= remaining) {
            groupSize = remaining;
        } else if (remaining - groupSize <= 2) {
            groupSize = remaining;
        }

        let group = '';
        for (let i = 0; i < groupSize; i++) {
            group += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        result += group;
        if (result.length < 59) {
            result += '-';
        }
    }

    if (result.length > 59) {
        result = result.substring(0, 59);
    }
    return result;
};

const handleGenerateSlug = () => {
    publicSlug.value = generateComplexSlug();
    slugWarning.value = '';
};

const hasUnsavedChanges = ref(false);

const saveDraft = (publishAfterSave = false, onComplete?: () => void) => {
    if (!props.quizForm) {
        onComplete?.();
        return;
    }

    normalizePublicSlug();
    if (!publicSlug.value) {
        publicSlug.value = 'untitled-form';
    }
    isSaving.value = true;
    slugWarning.value = '';

    router.patch(
        props.quizForm.updateUrl,
        {
            title: form.title,
            description: form.description,
            slug: publicSlug.value,
            questions: form.questions,
            settings: form.settings,
            published: isPublished.value,
            cohort_ids: selectedCohortIds.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                hasUnsavedChanges.value = false;
                publicUrl.value = `${appOrigin.value}/forms/${publicSlug.value}`;
                markChanged('Semua perubahan disimpan');
                showPublish.value = publishAfterSave;
                // Update local storage in sync
                localStorage.setItem(
                    `quiz_draft_${props.quizForm.id}`,
                    JSON.stringify({
                        title: form.title,
                        description: form.description,
                        questions: form.questions,
                        settings: form.settings,
                        isPublished: isPublished.value,
                    }),
                );
                onComplete?.();
            },
            onError: (errors) => {
                slugWarning.value = errors.slug ?? 'Form belum bisa disimpan. Periksa kembali data quiz.';
                markChanged('Gagal menyimpan perubahan');
                showPublish.value = true;
                onComplete?.();
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const saveDraftAndCopy = () => {
    if (!props.quizForm) {
        return;
    }

    normalizePublicSlug();
    if (!publicSlug.value) {
        publicSlug.value = 'untitled-form';
    }
    isSaving.value = true;
    slugWarning.value = '';

    router.patch(
        props.quizForm.updateUrl,
        {
            title: form.title,
            description: form.description,
            slug: publicSlug.value,
            questions: form.questions,
            settings: form.settings,
            published: isPublished.value,
            cohort_ids: selectedCohortIds.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                publicUrl.value = `${appOrigin.value}/forms/${publicSlug.value}`;
                markChanged('Draft saved');

                // Copy to clipboard
                copyShareUrl();

                // Close modal
                showPublish.value = false;

                // Sync local storage
                localStorage.setItem(
                    `quiz_draft_${props.quizForm.id}`,
                    JSON.stringify({
                        title: form.title,
                        description: form.description,
                        questions: form.questions,
                        settings: form.settings,
                        isPublished: isPublished.value,
                    }),
                );
            },
            onError: (errors) => {
                slugWarning.value = errors.slug ?? 'Form belum bisa disimpan. Periksa kembali data quiz.';
                markChanged('Link needs attention');
                showPublish.value = true;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const addQuestion = (type: QuestionType = 'Multiple choice') => {
    const question: Question = {
        id: nextQuestionId++,
        title: ['Paragraph', 'Short answer'].includes(type) ? `${type} question` : 'Untitled Question',
        description: '',
        type,
        options: noOptionQuestionTypes.includes(type) ? [] : scaleQuestionTypes.includes(type) ? ['1', '2', '3', '4', '5'] : ['Option 1'],
        rows: gridQuestionTypes.includes(type) ? ['Baris 1', 'Baris 2'] : [],
        columns: gridQuestionTypes.includes(type) ? ['Kolom 1', 'Kolom 2'] : [],
        answer: gridQuestionTypes.includes(type) ? {} : type === 'Checkboxes' ? [] : '',
        required: false,
        media: [],
        points: 10,
    };

    form.questions.push(question);
    activeQuestionId.value = question.id;
    activeTab.value = 'questions';
    markChanged('Question added');
};

const insertTextIntoQuestion = (question: any, snippet: string) => {
    if (!question.title || question.title === 'Untitled Question') {
        question.title = snippet;
    } else {
        question.title += ` ${snippet}`;
    }
    markChanged('Question formula or arabic inserted');
};

const duplicateQuestion = (question: Question) => {
    const copy: Question = {
        ...question,
        id: nextQuestionId++,
        title: `${question.title} copy`,
        options: [...question.options],
        rows: question.rows ? [...question.rows] : [],
        columns: question.columns ? [...question.columns] : [],
        answer:
            typeof question.answer === 'object' && question.answer !== null
                ? Array.isArray(question.answer)
                    ? [...question.answer]
                    : JSON.parse(JSON.stringify(question.answer))
                : question.answer,
        media: (question.media ?? []).map((m) => ({ ...m })),
        points: question.points ?? 10,
    };

    const index = form.questions.findIndex((item) => item.id === question.id);
    form.questions.splice(index + 1, 0, copy);
    activeQuestionId.value = copy.id;
    markChanged('Question duplicated');
};

const deleteQuestion = (question: Question) => {
    if (form.questions.length === 1) {
        question.title = 'Untitled Question';
        question.description = '';
        question.options = ['Option 1'];
        question.answer = '';
        question.type = 'Multiple choice';
        question.required = false;
        question.media = [];
        markChanged('Question reset');
        return;
    }

    const index = form.questions.findIndex((item) => item.id === question.id);
    form.questions.splice(index, 1);
    activeQuestionId.value = form.questions[Math.max(0, index - 1)].id;
    markChanged('Question deleted');
};

const addOption = (question: Question, label = `Option ${question.options.length + 1}`) => {
    question.options.push(label);
    markChanged('Option added');
};

const updateOption = (question: Question, optionIndex: number, value: string) => {
    question.options[optionIndex] = value;
    markChanged('Option updated');
};

const removeOption = (question: Question, optionIndex: number) => {
    question.options.splice(optionIndex, 1);
    if (question.options.length === 0 && !noOptionQuestionTypes.includes(question.type)) {
        question.options.push('Option 1');
    }

    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer) ? [...question.answer] : [];
        const newAnswer: number[] = [];
        currentAnswer.forEach((ansIdx) => {
            const numIdx = Number(ansIdx);
            if (numIdx < optionIndex) {
                newAnswer.push(numIdx);
            } else if (numIdx > optionIndex) {
                newAnswer.push(numIdx - 1);
            }
        });
        question.answer = newAnswer;
    } else {
        const numIdx = Number(question.answer);
        if (numIdx === optionIndex) {
            question.answer = '';
        } else if (numIdx > optionIndex) {
            question.answer = numIdx - 1;
        }
    }
    markChanged('Option removed');
};

const isQuestionAnswered = (question: Question) => {
    if (question.type === 'Checkboxes') {
        return Array.isArray(question.answer) && question.answer.length > 0;
    }
    return question.answer !== '' && question.answer !== null && question.answer !== undefined;
};

const normalizeCorrectAnswer = (question: Question) => {
    if (gridQuestionTypes.includes(question.type)) {
        if (typeof question.answer !== 'object' || question.answer === null) {
            question.answer = {};
        }
        return;
    }

    if (!optionQuestionTypes.includes(question.type)) {
        if (['Linear scale', 'Rating', 'Date', 'Time'].includes(question.type)) {
            return;
        }
        question.answer = '';
        return;
    }

    if (question.type === 'Checkboxes') {
        let currentAnswer = Array.isArray(question.answer) ? [...question.answer] : [];
        // Convert legacy string array to indices
        if (currentAnswer.length && typeof currentAnswer[0] === 'string') {
            currentAnswer = currentAnswer.map((val) => question.options.indexOf(val)).filter((idx) => idx >= 0);
        }
        question.answer = currentAnswer.map(Number).filter((idx) => idx >= 0 && idx < question.options.length);
        return;
    }

    let currentAnswer = question.answer;
    if (Array.isArray(currentAnswer)) {
        currentAnswer = currentAnswer[0];
    }
    if (typeof currentAnswer === 'string' && currentAnswer !== '') {
        const idx = question.options.indexOf(currentAnswer);
        currentAnswer = idx >= 0 ? idx : '';
    }
    if (typeof currentAnswer === 'number') {
        if (currentAnswer < 0 || currentAnswer >= question.options.length) {
            currentAnswer = '';
        }
    }
    question.answer = currentAnswer !== null && currentAnswer !== undefined ? currentAnswer : '';
};

const normalizeChoiceAnswer = (question: Question) => {
    if (!optionQuestionTypes.includes(question.type)) {
        return;
    }

    const currentAnswer = previewAnswers[question.id];

    if (question.type === 'Checkboxes') {
        previewAnswers[question.id] = Array.isArray(currentAnswer)
            ? currentAnswer.filter((answer) => question.options.includes(answer))
            : currentAnswer && question.options.includes(currentAnswer)
              ? [currentAnswer]
              : [];

        return;
    }

    const currentSingleAnswer = Array.isArray(currentAnswer) ? currentAnswer[0] : currentAnswer;
    previewAnswers[question.id] = currentSingleAnswer && question.options.includes(currentSingleAnswer) ? currentSingleAnswer : '';
};

const updateQuestionType = (question: Question, type: QuestionType) => {
    const previousType = question.type;
    question.type = type;
    openTypeMenuQuestionId.value = null;

    if (optionQuestionTypes.includes(type)) {
        if (question.options.length === 0 || gridQuestionTypes.includes(previousType)) {
            question.options = ['Option 1'];
        }
        normalizeCorrectAnswer(question);
        normalizeChoiceAnswer(question);
    } else if (scaleQuestionTypes.includes(type)) {
        question.options = ['1', '2', '3', '4', '5'];
        question.answer = '';
    } else if (gridQuestionTypes.includes(type)) {
        question.options = [];
        if (!question.rows || question.rows.length === 0) {
            question.rows = ['Baris 1', 'Baris 2'];
        }
        if (!question.columns || question.columns.length === 0) {
            question.columns = ['Kolom 1', 'Kolom 2'];
        }
        question.answer = {};
    } else {
        question.answer = '';
    }
    markChanged('Question type changed');
};

const setGridAnswer = (question: Question, rowIndex: number, colIndex: number, mode: 'single' | 'multiple') => {
    if (!question.answer || typeof question.answer !== 'object') {
        question.answer = {};
    }
    if (mode === 'single') {
        question.answer[rowIndex] = colIndex;
    } else {
        if (!Array.isArray(question.answer[rowIndex])) {
            question.answer[rowIndex] = [];
        }
        const idx = question.answer[rowIndex].indexOf(colIndex);
        if (idx >= 0) {
            question.answer[rowIndex].splice(idx, 1);
        } else {
            question.answer[rowIndex].push(colIndex);
        }
    }
    markChanged('Grid answer key updated');
};

const isCorrectAnswer = (question: Question, optionIndex: number | string) => {
    const idx = Number(optionIndex);
    const optText = question.options?.[idx];
    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer) ? question.answer : [];
        return currentAnswer.some((a: any) => Number(a) === idx || (optText !== undefined && a === optText));
    }
    return question.answer !== '' && (Number(question.answer) === idx || (optText !== undefined && question.answer === optText));
};

const setCorrectAnswer = (question: Question, optionIndex: number | string) => {
    const idx = Number(optionIndex);
    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer)
            ? question.answer.map((a: any) => {
                  const asNum = Number(a);
                  if (!isNaN(asNum) && question.options[asNum] !== undefined) {
                      return asNum;
                  }
                  const found = question.options.indexOf(String(a));
                  return found >= 0 ? found : a;
              })
            : [];
        const optText = question.options?.[idx];
        const ansIdx = currentAnswer.findIndex((a: any) => Number(a) === idx || (optText !== undefined && a === optText));

        if (ansIdx >= 0) {
            currentAnswer.splice(ansIdx, 1);
        } else {
            currentAnswer.push(idx);
        }
        question.answer = currentAnswer;
    } else {
        question.answer = idx;
    }
    markChanged('Correct answer updated');
};

const checkboxAnswerIncludes = (question: Question, option: string) => {
    const currentAnswer = previewAnswers[question.id];

    return Array.isArray(currentAnswer) && currentAnswer.includes(option);
};

const toggleCheckboxAnswer = (question: Question, option: string) => {
    const currentAnswer = previewAnswers[question.id];
    const selectedOptions = Array.isArray(currentAnswer) ? [...currentAnswer] : [];
    const optionIndex = selectedOptions.indexOf(option);

    if (optionIndex >= 0) {
        selectedOptions.splice(optionIndex, 1);
    } else {
        selectedOptions.push(option);
    }

    previewAnswers[question.id] = selectedOptions;
};

const selectPreviewGridAnswer = (questionId: number, rowIndex: number, colIndex: number, mode: 'single' | 'multiple') => {
    if (!previewAnswers[questionId] || typeof previewAnswers[questionId] !== 'object' || Array.isArray(previewAnswers[questionId])) {
        previewAnswers[questionId] = {};
    }
    if (mode === 'single') {
        previewAnswers[questionId][rowIndex] = colIndex;
    } else {
        if (!Array.isArray(previewAnswers[questionId][rowIndex])) {
            previewAnswers[questionId][rowIndex] = [];
        }
        const idx = previewAnswers[questionId][rowIndex].indexOf(colIndex);
        if (idx >= 0) {
            previewAnswers[questionId][rowIndex].splice(idx, 1);
        } else {
            previewAnswers[questionId][rowIndex].push(colIndex);
        }
    }
};

const openPreview = () => {
    window.open(publicUrl.value, '_blank');
};

const addSection = () => {
    addQuestion('Paragraph');
    activeQuestion.value.title = 'Section description';
};

const addTitleDescription = () => {
    addQuestion('Paragraph');
    activeQuestion.value.title = 'Judul bagian';
    activeQuestion.value.description = 'Deskripsi bagian';
};

const addMediaToActiveQuestion = (type: 'image' | 'video') => {
    activeQuestion.value.media.push({
        type,
        url: '',
        sourceType: 'upload',
        isUploading: false,
        uploadError: '',
    });
    markChanged(type === 'image' ? 'Image added to question' : 'Video added to question');
};

const handleMediaUpload = async (event: Event, media: any) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) {
        return;
    }

    const file = input.files[0];
    const limitMb = form.settings.maxUploadSize ?? 20;
    const limitBytes = limitMb * 1024 * 1024;

    if (file.size > limitBytes) {
        media.uploadError = `Ukuran berkas melebihi batas maksimum yang ditentukan (${limitMb}MB).`;
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    media.isUploading = true;
    media.uploadError = '';

    try {
        const response = await axios.post(route('forms.media.upload'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        media.url = response.data.url;
        markChanged('Media uploaded successfully');
    } catch (error: any) {
        console.error(error);
        const errMsg = error.response?.data?.message || '';
        const hasPhpLimitError =
            errMsg.toLowerCase().includes('failed to upload') || errMsg.toLowerCase().includes('too large') || error.response?.status === 422;

        if (hasPhpLimitError) {
            media.uploadError = 'Gagal mengunggah file. Ukuran berkas melebihi batas upload_max_filesize PHP server Anda (saat ini 40MB).';
        } else {
            media.uploadError = error.response?.data?.message || 'Gagal mengunggah file. Silakan coba lagi.';
        }
    } finally {
        media.isUploading = false;
    }
};

const moveQuestion = (fromId: number, toId: number) => {
    if (fromId === toId) {
        return;
    }

    const fromIndex = form.questions.findIndex((question: Question) => question.id === fromId);
    const toIndex = form.questions.findIndex((question: Question) => question.id === toId);

    if (fromIndex < 0 || toIndex < 0) {
        return;
    }

    const [movedQuestion] = form.questions.splice(fromIndex, 1);
    form.questions.splice(toIndex, 0, movedQuestion);
    activeQuestionId.value = movedQuestion.id;
    markChanged('Question reordered');
};

const handleDragStart = (question: Question, event: DragEvent) => {
    draggedQuestionId.value = question.id;
    activeQuestionId.value = question.id;
    event.dataTransfer?.setData('text/plain', String(question.id));
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const handleDrop = (question: Question, event: DragEvent) => {
    event.preventDefault();
    const fromId = Number(event.dataTransfer?.getData('text/plain') || draggedQuestionId.value);
    if (fromId) {
        moveQuestion(fromId, question.id);
    }
    draggedQuestionId.value = null;
};

const downloadImportTemplate = () => {
    const link = document.createElement('a');
    link.href = '/templates/template_import_soal.docx';
    link.download = 'template_import_soal.docx';
    link.click();
    markChanged('DOCX template downloaded');
};

const handleImportDocxFile = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    isImportingFile.value = true;
    importError.value = '';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post('/questions/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        const importedQuestions = response.data.questions;
        if (Array.isArray(importedQuestions) && importedQuestions.length > 0) {
            importedQuestions.forEach((q: any) => {
                const isCheckboxes = q.type === 'Checkboxes';
                let answerVal =
                    typeof q.answer === 'object' && q.answer !== null
                        ? Array.isArray(q.answer)
                            ? [...q.answer]
                            : JSON.parse(JSON.stringify(q.answer))
                        : q.answer;

                if (isCheckboxes && Array.isArray(answerVal)) {
                    answerVal = answerVal.map((ans: any) => {
                        const foundIdx = (q.options || []).indexOf(ans);
                        return foundIdx >= 0 ? foundIdx : ans;
                    });
                }

                const question: Question = {
                    id: nextQuestionId++,
                    title: q.title || 'Untitled Question',
                    description: q.description || '',
                    type: q.type || 'Multiple choice',
                    options: Array.isArray(q.options) ? [...q.options] : [],
                    rows: Array.isArray(q.rows) ? [...q.rows] : [],
                    columns: Array.isArray(q.columns) ? [...q.columns] : [],
                    answer: answerVal,
                    required: !!q.required,
                    media: Array.isArray(q.media) ? [...q.media] : [],
                    points: q.points ?? 10,
                };
                form.questions.push(question);
                activeQuestionId.value = question.id;
            });

            isImportModalOpen.value = false;
            activeTab.value = 'questions';
            markChanged(`${importedQuestions.length} pertanyaan berhasil diimpor`);
        } else {
            importError.value = 'Tidak ada pertanyaan valid yang ditemukan di dalam berkas.';
        }
    } catch (error: any) {
        importError.value = error.response?.data?.message || 'Gagal mengimpor file. Pastikan format berkas benar.';
    } finally {
        isImportingFile.value = false;
        input.value = '';
    }
};

const handleImportExamViewFile = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    isImportingFile.value = true;
    importError.value = '';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post('/questions/import-examview', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        const importedQuestions = response.data.questions;
        if (Array.isArray(importedQuestions) && importedQuestions.length > 0) {
            importedQuestions.forEach((q: any) => {
                const question: Question = {
                    id: nextQuestionId++,
                    title: q.title || 'Untitled Question',
                    description: q.description || '',
                    type: q.type || 'Multiple choice',
                    options: Array.isArray(q.options) ? [...q.options] : [],
                    rows: Array.isArray(q.rows) ? [...q.rows] : [],
                    columns: Array.isArray(q.columns) ? [...q.columns] : [],
                    answer:
                        typeof q.answer === 'object' && q.answer !== null
                            ? Array.isArray(q.answer)
                                ? [...q.answer]
                                : JSON.parse(JSON.stringify(q.answer))
                            : q.answer,
                    required: !!q.required,
                    media: Array.isArray(q.media) ? [...q.media] : [],
                    points: q.points ?? 10,
                };
                form.questions.push(question);
                activeQuestionId.value = question.id;
            });

            isImportModalOpen.value = false;
            activeTab.value = 'questions';
            markChanged(`${importedQuestions.length} soal ExamView berhasil diimpor`);
        } else {
            importError.value = 'Tidak ada pertanyaan valid yang ditemukan di dalam berkas ExamView.';
        }
    } catch (error: any) {
        importError.value =
            error.response?.data?.message || 'Gagal mengimpor file ExamView. Pastikan format berkas ZIP hasil ekspor Blackboard benar.';
    } finally {
        isImportingFile.value = false;
        input.value = '';
    }
};

const copyShareUrl = async () => {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        markChanged('Link copied');
    } catch {
        markChanged(shareUrl.value);
    }
};

// Toast Notification State
const toastMessage = ref('');
const showToast = ref(false);
let toastTimeout: any = null;

const triggerToast = (message: string) => {
    toastMessage.value = message;
    showToast.value = true;
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }
    toastTimeout = setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Undo & Redo History State Stack
const history = ref<string[]>([]);
const historyIndex = ref(-1);
let isUndoingRedoing = false;

const recordHistory = () => {
    if (isUndoingRedoing) {
        return;
    }
    const currentState = JSON.stringify({
        title: form.title,
        description: form.description,
        questions: form.questions,
        settings: form.settings,
    });
    // If the state is identical to the current history state, skip recording
    if (historyIndex.value >= 0 && history.value[historyIndex.value] === currentState) {
        return;
    }
    // Truncate future history if we were in the middle of undo stack
    if (historyIndex.value < history.value.length - 1) {
        history.value = history.value.slice(0, historyIndex.value + 1);
    }
    history.value.push(currentState);
    historyIndex.value = history.value.length - 1;

    // Enforce capacity of max 5 undos
    while (historyIndex.value > 5) {
        history.value.shift();
        historyIndex.value--;
    }

    // Enforce capacity of max 5 redos
    if (history.value.length - 1 - historyIndex.value > 5) {
        history.value = history.value.slice(0, historyIndex.value + 1 + 5);
    }
};

const undo = () => {
    if (historyIndex.value > 0) {
        isUndoingRedoing = true;
        historyIndex.value--;
        const state = JSON.parse(history.value[historyIndex.value]);
        form.title = state.title;
        form.description = state.description;
        form.questions = state.questions;
        form.settings = state.settings;
        isUndoingRedoing = false;
        statusMessage.value = 'Undo performed';
        triggerToast('Undo berhasil dilakukan');
    }
};

const redo = () => {
    if (historyIndex.value < history.value.length - 1) {
        isUndoingRedoing = true;
        historyIndex.value++;
        const state = JSON.parse(history.value[historyIndex.value]);
        form.title = state.title;
        form.description = state.description;
        form.questions = state.questions;
        form.settings = state.settings;
        isUndoingRedoing = false;
        statusMessage.value = 'Redo performed';
        triggerToast('Redo berhasil dilakukan');
    }
};

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(() => historyIndex.value < history.value.length - 1);

const setStatus = (status: boolean) => {
    isPublished.value = status;
    showStatusMenu.value = false;
    // Save to LocalStorage immediately
    if (props.quizForm) {
        localStorage.setItem(
            `quiz_draft_${props.quizForm.id}`,
            JSON.stringify({
                title: form.title,
                description: form.description,
                questions: form.questions,
                settings: form.settings,
                isPublished: status,
            }),
        );
    }
    // Save to server immediately
    saveDraft(false);
    triggerToast(status ? 'Quiz berhasil dipublikasikan' : 'Quiz diubah menjadi Draft');
};

// Check and restore LocalStorage draft on load
onMounted(() => {
    if (props.quizForm) {
        const localData = localStorage.getItem(`quiz_draft_${props.quizForm.id}`);
        if (localData) {
            try {
                const parsed = JSON.parse(localData);
                form.title = parsed.title;
                form.description = parsed.description;
                form.questions = parsed.questions;
                form.settings = parsed.settings;
                if (parsed.isPublished !== undefined) {
                    isPublished.value = parsed.isPublished;
                }
            } catch (e) {
                console.error(e);
            }
        }
    }
    // Convert legacy correct answers to indices for all loaded questions
    form.questions.forEach((q: Question) => normalizeCorrectAnswer(q));

    // Record initial state in undo stack
    recordHistory();
});

// Auto-save to server logic (1.2 seconds debounce)
const autoSave = useDebounceFn(() => {
    if (props.quizForm) {
        saveDraft(false);
    }
}, 1200);

const handleTitleBlur = () => {
    if (props.quizForm) {
        saveDraft(false);
    }
};

const navigateBackToDashboard = () => {
    if (props.quizForm && (hasUnsavedChanges.value || isSaving.value)) {
        statusMessage.value = 'Menyimpan sebelum kembali...';
        saveDraft(false, () => {
            router.visit(route('dashboard'));
        });
    } else {
        router.visit(route('dashboard'));
    }
};

// Watch form changes (updates LocalStorage in real-time)
watch(
    () => [form.title, form.description, form.questions, form.settings],
    () => {
        hasUnsavedChanges.value = true;
        statusMessage.value = 'Menyimpan perubahan...';

        // Save to LocalStorage immediately (real-time)
        if (props.quizForm) {
            localStorage.setItem(
                `quiz_draft_${props.quizForm.id}`,
                JSON.stringify({
                    title: form.title,
                    description: form.description,
                    questions: form.questions,
                    settings: form.settings,
                    isPublished: isPublished.value,
                }),
            );
        }

        autoSave();
        debouncedRecordHistory();
    },
    { deep: true },
);
</script>

<template>
    <Head :title="form.title" />

    <main
        :class="[
            'min-h-screen text-slate-900 transition-all duration-300',
            form.settings.backgroundColorClass ?? 'bg-[#f0efff]',
            form.settings.backgroundPatternClass ?? 'pattern-none',
        ]"
    >
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-5">
                <button
                    type="button"
                    class="grid h-9 w-9 shrink-0 grid-cols-2 gap-1 rounded-xl bg-indigo-500 p-1.5 text-white shadow-[0_3px_0_#4338ca] transition hover:-translate-y-0.5 hover:shadow-[0_5px_0_#4338ca]"
                    aria-label="Back to dashboard"
                    title="Kembali ke Dashboard"
                    @click="navigateBackToDashboard"
                >
                    <span class="rounded-lg bg-white/95"></span>
                    <span class="rounded-lg bg-white/70"></span>
                    <span class="rounded-lg bg-white/70"></span>
                    <span class="rounded-lg bg-white/95"></span>
                </button>

                <div class="flex min-w-0 items-center gap-3">
                    <input
                        v-model="form.title"
                        type="text"
                        class="min-w-0 max-w-[170px] border-0 bg-transparent p-0 text-lg font-medium outline-none transition focus:border-b focus:border-indigo-500 sm:max-w-md sm:text-xl"
                        @input="markChanged('Title updated')"
                        @blur="handleTitleBlur"
                        @change="handleTitleBlur"
                        @keydown.enter.prevent="handleTitleBlur"
                    />
                    <Star class="hidden h-5 w-5 text-slate-500 sm:block" />
                </div>

                <div class="ml-auto flex items-center gap-2 text-slate-600 lg:gap-4">
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block"
                        aria-label="Add-ons"
                        @click="markChanged('Add-ons panel opened')"
                    >
                        <Puzzle class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block"
                        aria-label="Theme"
                        @click="showThemeSidebar = !showThemeSidebar"
                    >
                        <Palette class="h-5 w-5" />
                    </button>
                    <button type="button" class="rounded-full p-2 transition hover:bg-slate-100" aria-label="Preview" @click="openPreview">
                        <Eye class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block"
                        :class="{ 'cursor-not-allowed opacity-40': !canUndo }"
                        :disabled="!canUndo"
                        aria-label="Undo"
                        @click="undo"
                    >
                        <Undo2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block"
                        :class="{ 'cursor-not-allowed opacity-40': !canRedo }"
                        :disabled="!canRedo"
                        aria-label="Redo"
                        @click="redo"
                    >
                        <Redo2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 md:block"
                        aria-label="Copy & Edit link"
                        @click="showPublish = true"
                    >
                        <Link2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="relative hidden rounded-full p-2 transition hover:bg-slate-100 md:block"
                        aria-label="Invite collaborators"
                        title="Kolaborasi Kuis Antar Guru"
                        @click="openCollaboratorModal"
                    >
                        <UserPlus class="h-5 w-5" />
                        <span
                            v-if="props.quizForm?.collaborators?.length"
                            class="shadow-xs absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white"
                        >
                            {{ props.quizForm.collaborators.length }}
                        </span>
                    </button>

                    <!-- Status Dropdown (Draft / Published) -->
                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-700 lg:px-6"
                            @click="showStatusMenu = !showStatusMenu"
                        >
                            <span>{{ isPublished ? 'Published' : 'Draft' }}</span>
                            <ChevronDown class="h-4 w-4" />
                        </button>
                        <div
                            v-if="showStatusMenu"
                            class="absolute right-0 top-11 z-50 w-40 rounded-2xl border border-slate-200 bg-white p-2 text-sm font-medium shadow-lg"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
                                @click="setStatus(true)"
                            >
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                <span>Publish</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
                                @click="setStatus(false)"
                            >
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                <span>Draft</span>
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="rounded-full p-2 transition hover:bg-slate-100"
                            aria-label="More menu"
                            @click="showMoreMenu = !showMoreMenu"
                        >
                            <MoreVertical class="h-5 w-5" />
                        </button>
                        <div
                            v-if="showMoreMenu"
                            class="absolute right-0 top-11 w-52 rounded-2xl border border-slate-200 bg-white p-2 text-sm font-medium shadow-lg"
                        >
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="showPublish = true">
                                Copy public link
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left hover:bg-slate-50 md:hidden"
                                @click="
                                    openCollaboratorModal();
                                    showMoreMenu = false;
                                "
                            >
                                <UserPlus class="h-4 w-4 text-indigo-600" />
                                Kolaborasi Kuis
                            </button>
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="openPreview">
                                Open preview
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50"
                                @click="
                                    showThemeSidebar = true;
                                    showMoreMenu = false;
                                "
                            >
                                Change theme
                            </button>
                        </div>
                    </div>
                    <div
                        class="hidden h-10 w-10 rounded-full border-4 border-emerald-400 bg-gradient-to-br from-lime-200 via-emerald-300 to-sky-300 sm:block"
                    ></div>
                </div>
            </div>

            <nav class="flex h-10 items-end justify-center gap-5 bg-white sm:gap-8">
                <button
                    v-for="tab in ['questions', 'responses', 'settings', 'keamanan']"
                    :key="tab"
                    type="button"
                    :class="[
                        'px-3 pb-2.5 text-sm font-bold capitalize transition',
                        activeTab === tab ? 'border-b-4 border-indigo-600 text-indigo-700' : 'text-slate-700 hover:text-indigo-600',
                    ]"
                    @click="activeTab = tab as 'questions' | 'responses' | 'settings' | 'keamanan'"
                >
                    {{ tab === 'keamanan' ? 'keamanan & kunci' : tab }}
                </button>
            </nav>
        </header>

        <section class="mx-auto grid max-w-[980px] grid-cols-1 gap-4 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1fr)_60px]">
            <input ref="docxFileInput" type="file" accept=".docx" class="hidden" @change="handleImportDocxFile" />
            <input ref="examviewFileInput" type="file" accept=".zip" class="hidden" @change="handleImportExamViewFile" />
            <div class="space-y-4">
                <p class="text-sm font-medium text-slate-500">{{ statusMessage }}</p>

                <template v-if="activeTab === 'questions'">
                    <section class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm transition duration-200 hover:shadow-md">
                        <div :class="['h-3 transition-all duration-300', form.settings.themeColorClass ?? 'bg-indigo-600']"></div>
                        <div class="grid gap-3 p-5 sm:p-6">
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full border-0 border-b border-transparent bg-transparent p-0 text-2xl font-normal leading-tight outline-none transition focus:border-indigo-500 sm:text-3xl"
                                :style="{ fontFamily: form.settings.questionFont ?? 'inherit' }"
                                @input="markChanged('Title updated')"
                                @blur="handleTitleBlur"
                                @change="handleTitleBlur"
                                @keydown.enter.prevent="handleTitleBlur"
                            />
                            <input
                                v-model="form.description"
                                type="text"
                                class="w-full border-0 border-b border-transparent bg-transparent p-0 text-base text-slate-500 outline-none transition focus:border-indigo-500 sm:text-lg"
                                :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                placeholder="Form description"
                                @input="markChanged('Description updated')"
                            />
                        </div>
                    </section>

                    <TransitionGroup
                        tag="div"
                        class="space-y-4"
                        move-class="motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out"
                        enter-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
                        enter-from-class="opacity-0 translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-2"
                    >
                        <section
                            v-for="question in form.questions"
                            :key="question.id"
                            draggable="true"
                            :class="[
                                'relative overflow-visible rounded-2xl border border-slate-300 bg-white shadow-sm transition duration-200 hover:shadow-md',
                                draggedQuestionId === question.id ? 'scale-[0.99] opacity-60' : '',
                            ]"
                            @click="activeQuestionId = question.id"
                            @dragstart="handleDragStart(question, $event)"
                            @dragover.prevent
                            @drop="handleDrop(question, $event)"
                            @dragend="draggedQuestionId = null"
                        >
                            <div
                                :class="[
                                    'grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_260px]',
                                    activeQuestionId === question.id ? 'border-l-4 border-blue-500' : 'border-l-4 border-transparent',
                                ]"
                            >
                                <template v-if="editingAnswerKeyQuestionId === question.id">
                                    <!-- ANSWER KEY EDIT MODE -->
                                    <div class="col-span-full overflow-hidden rounded-2xl border border-indigo-100 bg-white">
                                        <div class="flex items-center justify-between border-b border-indigo-100 bg-indigo-50/50 px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <Key class="h-5 w-5 animate-pulse text-indigo-600" />
                                                <span class="text-sm font-extrabold text-indigo-950">Pengaturan Kunci Jawaban</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-slate-600">Poin:</span>
                                                <input
                                                    v-model.number="question.points"
                                                    type="number"
                                                    min="0"
                                                    class="w-16 rounded-xl border border-slate-300 bg-white px-2.5 py-1 text-center text-sm font-extrabold outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100"
                                                    @input="markChanged('Points updated')"
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-4 p-6">
                                            <div class="text-sm font-bold text-slate-800">
                                                Pertanyaan: <span class="font-normal text-slate-600">{{ question.title || 'Tanpa Judul' }}</span>
                                            </div>

                                            <!-- Short Answer Kunci Jawaban -->
                                            <div v-if="question.type === 'Short answer'" class="space-y-2">
                                                <label class="text-xs font-bold text-slate-600">Tuliskan jawaban yang benar:</label>
                                                <input
                                                    v-model="question.answer"
                                                    type="text"
                                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/30 px-3.5 text-sm outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100"
                                                    placeholder="Masukkan teks jawaban yang benar..."
                                                    @input="markChanged('Answer key updated')"
                                                />
                                            </div>

                                            <!-- Paragraph Kunci Jawaban Info -->
                                            <div
                                                v-else-if="question.type === 'Paragraph'"
                                                class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-medium leading-relaxed text-slate-500"
                                            >
                                                💡 Pertanyaan bertipe <strong>Paragraf</strong> akan dinilai secara manual oleh Anda setelah responden
                                                mengirimkan jawaban. Tidak ada kunci jawaban otomatis.
                                            </div>

                                            <!-- Multiple choice / Checkboxes / Drop-down Kunci Jawaban -->
                                            <div v-else-if="optionQuestionTypes.includes(question.type)" class="space-y-2.5">
                                                <span class="block text-xs font-bold text-slate-600"
                                                    >Pilih satu atau lebih opsi jawaban yang benar:</span
                                                >
                                                <div class="space-y-2">
                                                    <button
                                                        v-for="(option, optionIndex) in question.options"
                                                        :key="`anskey-opt-${optionIndex}`"
                                                        type="button"
                                                        :class="[
                                                            'flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition-all',
                                                            isCorrectAnswer(question, optionIndex)
                                                                ? 'border-emerald-500 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-100'
                                                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                                        ]"
                                                        @click="setCorrectAnswer(question, optionIndex)"
                                                    >
                                                        <span
                                                            :class="[
                                                                'flex h-5 w-5 shrink-0 items-center justify-center border-2',
                                                                question.type === 'Checkboxes' ? 'rounded' : 'rounded-full',
                                                                isCorrectAnswer(question, optionIndex)
                                                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                                                    : 'border-slate-300',
                                                            ]"
                                                        >
                                                            <Check v-if="isCorrectAnswer(question, optionIndex)" class="h-3.5 w-3.5 text-white" />
                                                        </span>
                                                        <span>{{ option }}</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Linear scale Kunci Jawaban -->
                                            <div v-else-if="question.type === 'Linear scale'" class="space-y-3">
                                                <span class="block text-xs font-bold text-slate-600">Pilih titik skala yang benar:</span>
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        v-for="option in question.options"
                                                        :key="`anskey-scale-${option}`"
                                                        type="button"
                                                        :class="[
                                                            'flex h-10 w-10 items-center justify-center rounded-full border text-sm font-bold transition-all',
                                                            question.answer === option
                                                                ? 'border-emerald-500 bg-emerald-500 text-white shadow-sm'
                                                                : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-400',
                                                        ]"
                                                        @click="
                                                            question.answer = option;
                                                            markChanged('Answer key updated');
                                                        "
                                                    >
                                                        {{ option }}
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Rating Kunci Jawaban -->
                                            <div v-else-if="question.type === 'Rating'" class="space-y-3">
                                                <span class="block text-xs font-bold text-slate-600">Pilih rating bintang yang benar:</span>
                                                <div class="flex gap-2 text-slate-300">
                                                    <button
                                                        v-for="option in question.options"
                                                        :key="`anskey-rating-${option}`"
                                                        type="button"
                                                        :class="[
                                                            'transition-colors',
                                                            Number(question.answer) >= Number(option)
                                                                ? 'text-amber-400'
                                                                : 'text-slate-300 hover:text-amber-200',
                                                        ]"
                                                        @click="
                                                            question.answer = option;
                                                            markChanged('Answer key updated');
                                                        "
                                                    >
                                                        <Star class="h-8 w-8 fill-current" />
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Date Kunci Jawaban -->
                                            <div v-else-if="question.type === 'Date'" class="space-y-2">
                                                <label class="text-xs font-bold text-slate-600">Pilih tanggal yang benar:</label>
                                                <input
                                                    v-model="question.answer"
                                                    type="date"
                                                    class="h-10 rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none focus:border-indigo-600"
                                                    @change="markChanged('Answer key updated')"
                                                />
                                            </div>

                                            <!-- Time Kunci Jawaban -->
                                            <div v-else-if="question.type === 'Time'" class="space-y-2">
                                                <label class="text-xs font-bold text-slate-600">Pilih waktu yang benar:</label>
                                                <input
                                                    v-model="question.answer"
                                                    type="time"
                                                    class="h-10 rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none focus:border-indigo-600"
                                                    @change="markChanged('Answer key updated')"
                                                />
                                            </div>

                                            <!-- Grid types Kunci Jawaban -->
                                            <div v-else-if="gridQuestionTypes.includes(question.type)" class="space-y-4">
                                                <span class="block text-xs font-bold text-slate-600">Pilih jawaban benar untuk setiap baris:</span>
                                                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-slate-50/50">
                                                    <table class="w-full border-collapse text-left text-sm">
                                                        <thead>
                                                            <tr class="border-b border-slate-200 bg-slate-100">
                                                                <th class="p-3 font-bold text-slate-700">Baris / Kolom</th>
                                                                <th
                                                                    v-for="(col, cIndex) in question.columns"
                                                                    :key="`header-col-${cIndex}`"
                                                                    class="p-3 text-center font-bold text-slate-700"
                                                                >
                                                                    {{ col }}
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="(row, rIndex) in question.rows"
                                                                :key="`row-key-${rIndex}`"
                                                                class="border-slate-150 border-b transition-colors last:border-0 hover:bg-slate-50"
                                                            >
                                                                <td class="p-3 font-semibold text-slate-800">{{ row }}</td>
                                                                <td
                                                                    v-for="(col, cIndex) in question.columns"
                                                                    :key="`cell-${rIndex}-${cIndex}`"
                                                                    class="p-3 text-center"
                                                                >
                                                                    <label class="inline-flex cursor-pointer items-center justify-center">
                                                                        <input
                                                                            :type="question.type === 'Tick box grid' ? 'checkbox' : 'radio'"
                                                                            :name="`anskey-grid-row-${question.id}-${rIndex}`"
                                                                            :checked="
                                                                                question.type === 'Tick box grid'
                                                                                    ? question.answer?.[rIndex]?.includes(cIndex)
                                                                                    : question.answer?.[rIndex] === cIndex
                                                                            "
                                                                            class="h-4 w-4 accent-emerald-600"
                                                                            @change="
                                                                                setGridAnswer(
                                                                                    question,
                                                                                    rIndex,
                                                                                    cIndex,
                                                                                    question.type === 'Tick box grid' ? 'multiple' : 'single',
                                                                                )
                                                                            "
                                                                        />
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                                                <button
                                                    type="button"
                                                    class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-indigo-700"
                                                    @click="editingAnswerKeyQuestionId = null"
                                                >
                                                    Selesai
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <!-- NORMAL EDIT MODE -->
                                    <div>
                                        <div
                                            class="mx-auto mb-3 flex w-10 cursor-grab justify-center gap-1 text-slate-300 active:cursor-grabbing"
                                            title="Drag untuk pindahkan soal"
                                        >
                                            <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                            <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                            <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                        </div>

                                        <!-- Math & Arabic Toolbar -->
                                        <MathArabicToolbar
                                            @insert="(snip) => insertTextIntoQuestion(question, snip)"
                                            @toggle-rtl="question.isRtl = !question.isRtl"
                                        />

                                        <textarea
                                            v-model="question.title"
                                            rows="2"
                                            :dir="question.isRtl ? 'rtl' : hasArabic(question.title) ? 'rtl' : 'auto'"
                                            class="mb-3 w-full resize-y rounded-xl border-0 border-b border-slate-400 bg-slate-50 px-4 py-3 text-lg font-medium outline-none transition focus:border-indigo-600 sm:text-xl"
                                            :style="{ fontFamily: form.settings.questionFont ?? 'inherit' }"
                                            placeholder="Tulis pertanyaan, rumus matematika $...$, atau ketik bahasa Arab..."
                                            @input="markChanged('Question updated')"
                                        ></textarea>

                                        <!-- Live Preview of Math & Quran Arabic Rendering -->
                                        <div
                                            v-if="hasMathOrArabic(question.title)"
                                            class="shadow-xs mb-5 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-3"
                                        >
                                            <div class="mb-1.5 flex items-center justify-between text-[11px] font-bold text-indigo-700">
                                                <span class="flex items-center gap-1.5">
                                                    <Sparkles class="h-3.5 w-3.5 text-indigo-600" />
                                                    Pratinjau Tampilan (KaTeX & Mushaf Madinah):
                                                </span>
                                            </div>
                                            <div class="text-base font-semibold text-slate-900 sm:text-lg">
                                                <RichContent :content="question.title" />
                                            </div>
                                        </div>

                                        <input
                                            v-model="question.description"
                                            type="text"
                                            class="mb-4 w-full border-0 border-b border-transparent bg-transparent p-0 text-sm text-slate-500 outline-none transition focus:border-indigo-500"
                                            placeholder="Description"
                                            :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                            @input="markChanged('Description updated')"
                                        />

                                        <!-- Media Rendering and Editing -->
                                        <div v-if="question.media && question.media.length" class="mb-4 grid gap-4 sm:grid-cols-2">
                                            <div
                                                v-for="(media, mediaIndex) in question.media"
                                                :key="mediaIndex"
                                                class="relative rounded-xl border border-slate-200 bg-slate-50 p-4"
                                            >
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                                        {{ media.type === 'image' ? 'Gambar' : 'Video' }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="text-slate-400 transition hover:text-red-500"
                                                        title="Hapus media"
                                                        @click.stop="
                                                            question.media.splice(mediaIndex, 1);
                                                            markChanged('Media removed');
                                                        "
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </button>
                                                </div>

                                                <!-- Source Selector Tabs -->
                                                <div class="mb-3 flex gap-2 rounded-xl bg-slate-200/50 p-1 text-[10px] font-bold">
                                                    <button
                                                        type="button"
                                                        class="flex-1 rounded-lg py-1.5 text-center transition"
                                                        :class="[
                                                            (media.sourceType ?? 'upload') === 'upload'
                                                                ? 'bg-white text-slate-800 shadow-sm'
                                                                : 'text-slate-500 hover:text-slate-700',
                                                        ]"
                                                        @click="media.sourceType = 'upload'"
                                                    >
                                                        Upload File
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex-1 rounded-lg py-1.5 text-center transition"
                                                        :class="[
                                                            media.sourceType === 'link'
                                                                ? 'bg-white text-slate-800 shadow-sm'
                                                                : 'text-slate-500 hover:text-slate-700',
                                                        ]"
                                                        @click="media.sourceType = 'link'"
                                                    >
                                                        {{ media.type === 'video' ? 'Link YouTube' : 'Link URL' }}
                                                    </button>
                                                </div>

                                                <!-- Upload Interface -->
                                                <div v-if="(media.sourceType ?? 'upload') === 'upload'" class="space-y-2">
                                                    <div
                                                        v-if="media.isUploading"
                                                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-indigo-300 bg-indigo-50/50 p-4"
                                                    >
                                                        <span
                                                            class="mb-1 h-5 w-5 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"
                                                        ></span>
                                                        <span class="text-[10px] font-bold text-indigo-700">Uploading...</span>
                                                    </div>
                                                    <div v-else class="flex flex-col gap-2">
                                                        <label
                                                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center transition hover:border-indigo-400"
                                                        >
                                                            <FileUp class="mb-1 h-5 w-5 text-slate-400" />
                                                            <span class="text-[10px] font-bold text-slate-600"
                                                                >Pilih File {{ media.type === 'image' ? 'Gambar' : 'Video' }}</span
                                                            >
                                                            <span class="mt-0.5 text-[8px] text-slate-400"
                                                                >Maks. {{ form.settings.maxUploadSize ?? 20 }}MB</span
                                                            >
                                                            <span v-if="media.type === 'video'" class="mt-0.5 text-[8px] font-semibold text-amber-600"
                                                                >💡 Saran: tidak lebih dari 100MB</span
                                                            >
                                                            <input
                                                                type="file"
                                                                class="hidden"
                                                                :accept="media.type === 'image' ? 'image/*' : 'video/*'"
                                                                @change="handleMediaUpload($event, media)"
                                                            />
                                                        </label>
                                                        <p v-if="media.uploadError" class="text-[9px] font-bold text-red-600">
                                                            {{ media.uploadError }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Link Interface -->
                                                <div v-else class="space-y-2">
                                                    <input
                                                        media.url
                                                        v-model="media.url"
                                                        type="text"
                                                        :placeholder="
                                                            media.type === 'image'
                                                                ? 'Masukkan URL link gambar (http://...)'
                                                                : 'Masukkan Link YouTube (https://youtube.com/...)'
                                                        "
                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-indigo-500"
                                                        @input="markChanged('Media URL updated')"
                                                    />
                                                </div>

                                                <!-- Preview Display -->
                                                <div v-if="media.url" class="mt-2 overflow-hidden rounded-lg border border-slate-200 bg-white">
                                                    <img v-if="media.type === 'image'" :src="media.url" class="max-h-48 w-full object-contain p-2" />
                                                    <iframe
                                                        v-else-if="media.type === 'video' && getYoutubeEmbedUrl(media.url)"
                                                        :src="getYoutubeEmbedUrl(media.url)"
                                                        class="aspect-video w-full"
                                                        frameborder="0"
                                                        allowfullscreen
                                                    ></iframe>
                                                    <video
                                                        v-else-if="media.type === 'video'"
                                                        :src="media.url"
                                                        controls
                                                        class="max-h-48 w-full bg-slate-950"
                                                    ></video>
                                                    <div v-else class="p-2 text-center text-xs text-slate-400">
                                                        Format URL video tidak didukung (gunakan link YouTube)
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="question.type === 'Short answer'" class="space-y-4">
                                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-400">
                                                Responden memasukkan jawaban teks singkat
                                            </div>
                                            <div
                                                v-if="form.settings.isQuiz"
                                                class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2.5 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Kunci jawaban benar:</span> {{ question.answer || 'belum diisi' }}
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="question.type === 'Paragraph'"
                                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-400"
                                        >
                                            Responden memasukkan jawaban teks panjang (paragraf)
                                        </div>

                                        <div
                                            v-else-if="question.type === 'File upload'"
                                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-600"
                                            >
                                                Add file
                                            </button>
                                        </div>

                                        <div
                                            v-else-if="question.type === 'Date'"
                                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"
                                        >
                                            <input
                                                type="date"
                                                disabled
                                                class="cursor-not-allowed rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-400"
                                            />
                                            <div
                                                v-if="form.settings.isQuiz && question.answer"
                                                class="mt-2 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Kunci tanggal benar:</span> {{ question.answer }}
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="question.type === 'Time'"
                                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"
                                        >
                                            <input
                                                type="time"
                                                disabled
                                                class="cursor-not-allowed rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-400"
                                            />
                                            <div
                                                v-if="form.settings.isQuiz && question.answer"
                                                class="mt-2 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Kunci waktu benar:</span> {{ question.answer }}
                                            </div>
                                        </div>

                                        <div v-else-if="question.type === 'Linear scale'" class="space-y-4">
                                            <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 p-3">
                                                <span
                                                    v-for="option in question.options"
                                                    :key="option"
                                                    class="flex h-9 w-9 items-center justify-center rounded-full border bg-white text-sm font-bold text-slate-700"
                                                >
                                                    {{ option }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="text-xs font-bold text-indigo-600 hover:underline"
                                                    @click.stop="
                                                        question.options.push(String(question.options.length + 1));
                                                        markChanged('Scale extended');
                                                    "
                                                >
                                                    + Tambah Poin Skala
                                                </button>
                                                <button
                                                    v-if="question.options.length > 2"
                                                    type="button"
                                                    class="ml-4 text-xs font-bold text-red-600 hover:underline"
                                                    @click.stop="
                                                        question.options.pop();
                                                        markChanged('Scale reduced');
                                                    "
                                                >
                                                    Kurangi Skala
                                                </button>
                                            </div>
                                            <div
                                                v-if="form.settings.isQuiz && question.answer"
                                                class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Kunci jawaban benar:</span> {{ question.answer }}
                                            </div>
                                        </div>

                                        <div v-else-if="question.type === 'Rating'" class="space-y-4">
                                            <div class="flex gap-2 rounded-xl bg-slate-50 p-3 text-slate-300">
                                                <Star v-for="option in question.options" :key="option" class="h-8 w-8" />
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="text-xs font-bold text-indigo-600 hover:underline"
                                                    @click.stop="
                                                        question.options.push(String(question.options.length + 1));
                                                        markChanged('Rating extended');
                                                    "
                                                >
                                                    + Tambah Skala Rating
                                                </button>
                                                <button
                                                    v-if="question.options.length > 1"
                                                    type="button"
                                                    class="ml-4 text-xs font-bold text-red-600 hover:underline"
                                                    @click.stop="
                                                        question.options.pop();
                                                        markChanged('Rating reduced');
                                                    "
                                                >
                                                    Kurangi Rating
                                                </button>
                                            </div>
                                            <div
                                                v-if="form.settings.isQuiz && question.answer"
                                                class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Kunci jawaban benar (Rating):</span> {{ question.answer }} bintang
                                            </div>
                                        </div>

                                        <!-- Rows & Columns Editor for Grid types -->
                                        <div v-else-if="gridQuestionTypes.includes(question.type)" class="space-y-4">
                                            <div class="grid grid-cols-2 gap-4 rounded-2xl border border-slate-200 bg-slate-50/30 p-4">
                                                <!-- Rows list -->
                                                <div class="space-y-2">
                                                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-500">Baris (Rows)</span>
                                                    <div
                                                        v-for="(row, rIndex) in question.rows"
                                                        :key="`row-${rIndex}`"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <span class="text-xs font-bold text-slate-400">#{{ rIndex + 1 }}</span>
                                                        <input
                                                            v-model="question.rows[rIndex]"
                                                            type="text"
                                                            class="min-w-0 flex-1 border-b border-slate-200 bg-transparent py-1 text-xs outline-none focus:border-indigo-500"
                                                            @input="markChanged('Row label updated')"
                                                        />
                                                        <button
                                                            type="button"
                                                            class="px-1 text-xs font-bold text-slate-400 hover:text-red-500"
                                                            @click="question.rows.splice(rIndex, 1)"
                                                        >
                                                            x
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="flex items-center text-xs font-bold text-indigo-600 hover:underline"
                                                        @click="question.rows.push(`Baris ${question.rows.length + 1}`)"
                                                    >
                                                        + Tambah Baris
                                                    </button>
                                                </div>

                                                <!-- Columns list -->
                                                <div class="space-y-2">
                                                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-500"
                                                        >Kolom (Columns)</span
                                                    >
                                                    <div
                                                        v-for="(col, cIndex) in question.columns"
                                                        :key="`col-${cIndex}`"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <span class="text-xs font-bold text-slate-400">#{{ cIndex + 1 }}</span>
                                                        <input
                                                            v-model="question.columns[cIndex]"
                                                            type="text"
                                                            class="min-w-0 flex-1 border-b border-slate-200 bg-transparent py-1 text-xs outline-none focus:border-indigo-500"
                                                            @input="markChanged('Column label updated')"
                                                        />
                                                        <button
                                                            type="button"
                                                            class="px-1 text-xs font-bold text-slate-400 hover:text-red-500"
                                                            @click="question.columns.splice(cIndex, 1)"
                                                        >
                                                            x
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="flex items-center text-xs font-bold text-indigo-600 hover:underline"
                                                        @click="question.columns.push(`Kolom ${question.columns.length + 1}`)"
                                                    >
                                                        + Tambah Kolom
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Preview of correct answers in Grid -->
                                            <div
                                                v-if="form.settings.isQuiz && question.answer && Object.keys(question.answer).length"
                                                class="space-y-1 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-3 text-xs text-emerald-800"
                                            >
                                                <span class="block font-bold">Kunci jawaban benar:</span>
                                                <div v-for="(colIdx, rowIdx) in question.answer" :key="`ans-${rowIdx}`" class="ml-2 font-medium">
                                                    - {{ question.rows?.[rowIdx] }}:
                                                    <span class="font-bold">
                                                        {{
                                                            Array.isArray(colIdx)
                                                                ? colIdx.map((c) => question.columns?.[c]).join(', ')
                                                                : question.columns?.[colIdx]
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="space-y-5">
                                            <div
                                                v-if="question.type === 'Drop-down'"
                                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-500"
                                            >
                                                <ChevronDown class="h-4 w-4" />
                                                Responden memilih satu jawaban dari daftar
                                            </div>
                                            <div
                                                v-for="(option, optionIndex) in question.options"
                                                :key="`${question.id}-${optionIndex}`"
                                                v-show="option !== 'Other' || !isQuestionAnswered(question)"
                                                class="flex items-center gap-3 text-base transition hover:translate-x-1 sm:text-lg"
                                            >
                                                <!-- Visual icon only for checkboxes/radio in edit mode -->
                                                <span
                                                    :class="[
                                                        'flex h-6 w-6 shrink-0 items-center justify-center border-2 transition',
                                                        question.type === 'Checkboxes' ? 'rounded' : 'rounded-full',
                                                        form.settings.isQuiz && isCorrectAnswer(question, optionIndex)
                                                            ? 'border-emerald-500 bg-emerald-500 text-white shadow-sm'
                                                            : 'border-slate-300 bg-white',
                                                    ]"
                                                >
                                                    <Check
                                                        v-if="form.settings.isQuiz && isCorrectAnswer(question, optionIndex)"
                                                        class="h-3.5 w-3.5 text-white"
                                                    />
                                                </span>

                                                <!-- Dropdown number index if it is a Drop-down type -->
                                                <span v-if="question.type === 'Drop-down'" class="text-xs font-bold text-slate-400">
                                                    #{{ optionIndex + 1 }}
                                                </span>

                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <input
                                                        v-model="question.options[optionIndex]"
                                                        type="text"
                                                        :dir="hasArabic(question.options[optionIndex]) ? 'rtl' : 'auto'"
                                                        class="w-full border-0 border-b border-transparent bg-transparent p-0 outline-none transition focus:border-indigo-500"
                                                        :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                                        @input="markChanged('Option updated')"
                                                    />
                                                    <div
                                                        v-if="hasMathOrArabic(question.options[optionIndex])"
                                                        class="inline-flex items-center gap-1 self-start rounded border border-indigo-100 bg-indigo-50/70 px-2 py-0.5 text-xs text-slate-800"
                                                    >
                                                        <span class="text-[10px] font-bold text-indigo-500">Pratinjau:</span>
                                                        <RichContent :content="question.options[optionIndex]" />
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded-full px-2 text-slate-400 transition hover:bg-slate-100 hover:text-red-500"
                                                    @click.stop="removeOption(question, optionIndex)"
                                                >
                                                    x
                                                </button>
                                            </div>
                                            <div
                                                v-if="form.settings.isQuiz && optionQuestionTypes.includes(question.type)"
                                                class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-xs text-emerald-800"
                                            >
                                                <span class="font-bold">Jawaban benar:</span>
                                                <span
                                                    v-if="question.type === 'Checkboxes' && Array.isArray(question.answer) && question.answer.length"
                                                    class="ml-1"
                                                >
                                                    {{
                                                        question.answer
                                                            .map((a: any) => {
                                                                const asNum = Number(a);
                                                                if (!isNaN(asNum) && question.options[asNum] !== undefined) {
                                                                    return question.options[asNum];
                                                                }
                                                                return a;
                                                            })
                                                            .filter(Boolean)
                                                            .join(', ')
                                                    }}
                                                </span>
                                                <span
                                                    v-else-if="
                                                        question.type !== 'Checkboxes' &&
                                                        question.answer !== '' &&
                                                        question.answer !== null &&
                                                        question.answer !== undefined
                                                    "
                                                    class="ml-1"
                                                >
                                                    {{ question.options[Number(question.answer)] || question.answer }}
                                                </span>
                                                <span v-else class="ml-1 text-emerald-600">belum dipilih</span>
                                            </div>
                                            <div
                                                v-if="optionQuestionTypes.includes(question.type)"
                                                class="flex flex-wrap items-center gap-3 text-base text-slate-500 sm:text-lg"
                                            >
                                                <span
                                                    :class="[
                                                        'h-5 w-5 border-2 border-slate-300',
                                                        question.type === 'Checkboxes'
                                                            ? 'rounded'
                                                            : question.type === 'Drop-down'
                                                              ? 'rounded-lg'
                                                              : 'rounded-full',
                                                    ]"
                                                ></span>
                                                <button type="button" class="transition hover:text-indigo-600" @click.stop="addOption(question)">
                                                    Add option
                                                </button>
                                                <span
                                                    v-if="!question.options.includes('Other') && !isQuestionAnswered(question)"
                                                    class="text-slate-900"
                                                    >or</span
                                                >
                                                <button
                                                    v-if="!question.options.includes('Other') && !isQuestionAnswered(question)"
                                                    type="button"
                                                    class="text-blue-600 transition hover:text-blue-700"
                                                    @click.stop="addOption(question, 'Other')"
                                                >
                                                    Add "Other"
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <aside class="flex flex-col gap-6">
                                        <div class="relative">
                                            <button
                                                type="button"
                                                class="flex h-12 w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700 outline-none transition hover:border-indigo-300 focus:border-indigo-500 sm:text-base"
                                                @click.stop="openTypeMenuQuestionId = openTypeMenuQuestionId === question.id ? null : question.id"
                                            >
                                                <span class="flex min-w-0 items-center gap-3">
                                                    <component :is="questionTypeIcon(question.type)" class="h-5 w-5 shrink-0 text-slate-500" />
                                                    <span class="truncate">{{ question.type }}</span>
                                                </span>
                                                <ChevronDown class="h-5 w-5 shrink-0 text-slate-500" />
                                            </button>

                                            <div
                                                v-if="openTypeMenuQuestionId === question.id"
                                                class="absolute right-0 top-14 z-30 max-h-[480px] w-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white py-2 shadow-xl"
                                                @click.stop
                                            >
                                                <template v-for="(type, index) in questionTypes" :key="type.value">
                                                    <div
                                                        v-if="index > 0 && questionTypes[index - 1].group !== type.group"
                                                        class="my-2 border-t border-slate-200"
                                                    ></div>
                                                    <button
                                                        type="button"
                                                        :class="[
                                                            'flex w-full items-center gap-4 px-4 py-3 text-left text-base transition hover:bg-slate-100',
                                                            question.type === type.value ? 'bg-blue-50 text-slate-950' : 'text-slate-700',
                                                        ]"
                                                        @click="updateQuestionType(question, type.value)"
                                                    >
                                                        <component :is="type.icon" class="h-6 w-6 shrink-0 text-slate-500" />
                                                        <span>{{ type.value }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="mt-auto border-t border-slate-200 pt-6">
                                            <div class="flex items-center justify-end gap-4 text-slate-600 sm:gap-5">
                                                <button
                                                    type="button"
                                                    aria-label="Duplicate question"
                                                    class="transition hover:text-indigo-600"
                                                    @click.stop="duplicateQuestion(question)"
                                                >
                                                    <FileText class="h-6 w-6" />
                                                </button>
                                                <button
                                                    type="button"
                                                    aria-label="Delete question"
                                                    class="transition hover:text-red-500"
                                                    @click.stop="deleteQuestion(question)"
                                                >
                                                    <Trash2 class="h-6 w-6" />
                                                </button>
                                                <span class="h-8 border-l border-slate-200"></span>
                                                <button
                                                    v-if="form.settings.isQuiz"
                                                    type="button"
                                                    class="flex shrink-0 items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-700 shadow-sm transition hover:bg-indigo-100"
                                                    @click.stop="editingAnswerKeyQuestionId = question.id"
                                                >
                                                    <Key class="h-3.5 w-3.5" />
                                                    Kunci Jawaban
                                                </button>
                                                <span v-if="form.settings.isQuiz" class="h-8 border-l border-slate-200"></span>
                                                <span class="text-sm font-medium">Required</span>
                                                <button
                                                    type="button"
                                                    aria-label="Toggle required"
                                                    @click.stop="
                                                        question.required = !question.required;
                                                        markChanged('Required setting changed');
                                                    "
                                                >
                                                    <ToggleRight
                                                        :class="[
                                                            'h-8 w-8 transition',
                                                            question.required ? 'text-indigo-600' : 'rotate-180 text-slate-300',
                                                        ]"
                                                    />
                                                </button>
                                                <MoreVertical class="h-6 w-6" />
                                            </div>
                                        </div>
                                    </aside>
                                </template>
                            </div>
                            <Transition
                                enter-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
                                enter-from-class="opacity-0 translate-y-2 lg:-translate-x-2 lg:translate-y-0"
                                enter-to-class="opacity-100 translate-y-0 lg:translate-x-0"
                                leave-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
                                leave-from-class="opacity-100 translate-y-0 lg:translate-x-0"
                                leave-to-class="opacity-0 translate-y-2 lg:-translate-x-2 lg:translate-y-0"
                            >
                                <div
                                    v-if="activeQuestionId === question.id"
                                    class="absolute -bottom-7 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg lg:-right-20 lg:bottom-auto lg:left-auto lg:top-4 lg:translate-x-0 lg:flex-col"
                                >
                                    <button type="button" aria-label="Tambah pertanyaan" class="tool-button-primary" @click.stop="addQuestion()">
                                        <PlusCircle class="h-5 w-5" />
                                    </button>
                                    <button type="button" aria-label="Import question" class="tool-button" @click.stop="isImportModalOpen = true">
                                        <FileUp class="h-5 w-5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Tambah title dan deskripsi"
                                        class="tool-button"
                                        @click.stop="addTitleDescription"
                                    >
                                        <Type class="h-5 w-5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Tambah gambar"
                                        class="tool-button"
                                        @click.stop="addMediaToActiveQuestion('image')"
                                    >
                                        <Image class="h-5 w-5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Tambah video"
                                        class="tool-button"
                                        @click.stop="addMediaToActiveQuestion('video')"
                                    >
                                        <Video class="h-5 w-5" />
                                    </button>
                                    <button type="button" aria-label="Tambah section" class="tool-button" @click.stop="addSection">
                                        <PanelTop class="h-5 w-5" />
                                    </button>
                                </div>
                            </Transition>
                        </section>
                    </TransitionGroup>
                </template>

                <section v-else-if="activeTab === 'responses'" class="space-y-5">
                    <div class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-2xl font-semibold">Responses</h2>
                                <p class="mt-2 text-sm text-slate-500">Ringkasan jawaban responden dari link publik form ini.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    v-if="responseCount"
                                    :href="exportResponsesUrl"
                                    class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"
                                >
                                    <Download class="h-4 w-4" />
                                    Ekspor Nilai (CSV)
                                </a>
                                <button
                                    type="button"
                                    class="with-tooltip rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-indigo-600 hover:bg-indigo-50"
                                    aria-label="Copy public link"
                                    @click="copyShareUrl"
                                >
                                    Copy link
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-5">
                                <p class="text-sm font-bold text-slate-500">Total responses</p>
                                <p class="mt-2 text-4xl font-black text-indigo-600">{{ responseCount }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-5">
                                <p class="text-sm font-bold text-slate-500">Accepting</p>
                                <p class="mt-2 text-xl font-bold text-emerald-600">On</p>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-slate-50 p-5">
                                <p class="text-sm font-bold text-slate-500">Share URL</p>
                                <button
                                    type="button"
                                    class="mt-2 max-w-full truncate text-left text-sm font-bold text-indigo-600"
                                    @click="copyShareUrl"
                                >
                                    {{ shareUrl }}
                                </button>
                            </div>
                        </div>

                        <!-- Rekapitulasi Nilai Siswa Table -->
                        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-3.5">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Rekapitulasi Nilai Siswa</h3>
                                    <p class="text-xs text-slate-500">Daftar skor dan status pengerjaan seluruh responden.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="gradebookSearch"
                                        type="text"
                                        placeholder="Cari siswa / NIS / kelas..."
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none"
                                    />
                                    <a
                                        v-if="responseCount"
                                        :href="exportResponsesUrl"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700"
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                        Unduh CSV
                                    </a>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-600">
                                    <thead
                                        class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500"
                                    >
                                        <tr>
                                            <th class="px-4 py-3">No</th>
                                            <th class="px-4 py-3">NIS</th>
                                            <th class="px-4 py-3">Nama Siswa</th>
                                            <th class="px-4 py-3">Kelas / Cohort</th>
                                            <th class="px-4 py-3 text-center">Skor Poin</th>
                                            <th class="px-4 py-3 text-center">Nilai (100)</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Waktu Selesai</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(item, idx) in filteredGradebook" :key="item.id" class="transition-colors hover:bg-slate-50/80">
                                            <td class="px-4 py-3 font-semibold text-slate-400">{{ idx + 1 }}</td>
                                            <td class="px-4 py-3 font-mono font-medium text-slate-700">{{ item.nis }}</td>
                                            <td class="px-4 py-3 font-bold text-slate-900">{{ item.name }}</td>
                                            <td class="px-4 py-3">
                                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-medium text-slate-700">{{ item.kelas }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold text-indigo-600">{{ item.score }} / {{ maxScore }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    :class="[
                                                        'inline-block rounded-full px-2.5 py-0.5 font-bold',
                                                        item.percentage >= 75
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : item.percentage >= 60
                                                              ? 'bg-amber-100 text-amber-800'
                                                              : 'bg-rose-100 text-rose-800',
                                                    ]"
                                                >
                                                    {{ item.percentage }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    :class="[
                                                        'rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase',
                                                        item.is_timeout
                                                            ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                                            : 'border border-emerald-200 bg-emerald-50 text-emerald-700',
                                                    ]"
                                                >
                                                    {{ item.is_timeout ? 'Timeout' : 'Selesai' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-slate-400">{{ item.formattedDate }}</td>
                                        </tr>
                                        <tr v-if="filteredGradebook.length === 0">
                                            <td colspan="8" class="py-8 text-center text-slate-400">
                                                {{
                                                    gradebook.length === 0
                                                        ? 'Belum ada tanggapan masuk untuk kuis ini.'
                                                        : 'Tidak ada data siswa yang cocok dengan pencarian.'
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="latestResponses.length" class="mt-6 rounded-2xl border border-slate-200">
                            <div class="border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">Recent submissions</div>
                            <div class="divide-y divide-slate-100">
                                <div v-for="response in latestResponses" :key="response.id" class="flex items-center justify-between gap-4 px-4 py-3">
                                    <span class="min-w-0 truncate text-sm font-semibold text-slate-700">
                                        {{ response.email ?? 'Anonymous respondent' }}
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold text-slate-500">{{ response.submittedAt }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="responseCount" class="space-y-4">
                        <article
                            v-for="question in responseQuestions"
                            :key="`response-${question.id}`"
                            class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-bold text-slate-900">{{ question.title }}</h3>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ question.total }} answer</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ question.type }}</span>
                            </div>

                            <div v-if="question.options.length" class="mt-5 grid gap-6 lg:grid-cols-[180px_1fr]">
                                <div class="mx-auto h-40 w-40 rounded-full border-8 border-white shadow-inner" :style="pieChartStyle(question)"></div>
                                <div class="space-y-3">
                                    <div v-for="(option, optionIndex) in question.options" :key="option.label" class="flex items-center gap-3">
                                        <span
                                            class="h-3 w-3 shrink-0 rounded-full"
                                            :style="{ backgroundColor: pieColors[optionIndex % pieColors.length] }"
                                        ></span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700">{{ option.label }}</span>
                                        <span class="text-sm font-bold text-slate-900">{{ option.count }}</span>
                                        <span class="w-12 text-right text-xs font-semibold text-slate-500">
                                            {{ Math.round((option.count / question.total) * 100) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="question.textAnswers.length" class="mt-5 space-y-2">
                                <div
                                    v-for="answer in question.textAnswers"
                                    :key="answer"
                                    class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700"
                                >
                                    {{ answer }}
                                </div>
                            </div>

                            <p v-else class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-500">
                                Belum ada jawaban untuk pertanyaan ini.
                            </p>
                        </article>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                        <FileText class="mx-auto h-10 w-10 text-slate-400" />
                        <h3 class="mt-4 text-lg font-semibold text-slate-700">Belum ada respons masuk</h3>
                        <p class="mt-2 text-sm text-slate-500">
                            Bagikan link publik, lalu jawaban akan muncul di sini dalam bentuk ringkasan dan grafik.
                        </p>
                    </div>
                </section>

                <template v-else-if="activeTab === 'settings'">
                    <section class="rounded-xl border border-slate-300 bg-white shadow-sm">
                        <div class="px-7 py-7">
                            <h2 class="text-2xl font-normal text-slate-950">Settings</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <div class="flex items-center justify-between gap-6 px-11 py-16">
                                <div>
                                    <h3 class="text-xl font-normal text-slate-950">Make this a quiz</h3>
                                    <p class="mt-2 text-lg text-slate-600">Assign point values, set answers and automatically provide feedback</p>
                                </div>
                                <button
                                    type="button"
                                    :class="['relative h-5 w-11 rounded-full transition', form.settings.isQuiz ? 'bg-indigo-500' : 'bg-slate-300']"
                                    @click="toggleSetting('isQuiz')"
                                >
                                    <span
                                        :class="[
                                            'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                            form.settings.isQuiz ? 'left-5' : '-left-1',
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div class="border-t border-slate-200"></div>

                            <section class="px-11 py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isResponsesSettingsOpen = !isResponsesSettingsOpen"
                                >
                                    <span>
                                        <span class="block text-xl font-normal text-slate-950">Responses</span>
                                        <span class="mt-2 block text-lg text-slate-600">Manage how responses are collected and protected</span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 transition-transform', isResponsesSettingsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isResponsesSettingsOpen" class="mt-10 space-y-9">
                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <label class="text-xl font-normal text-slate-950" for="emailCollectionMode">Collect email addresses</label>
                                        <select
                                            id="emailCollectionMode"
                                            v-model="form.settings.emailCollectionMode"
                                            class="h-14 w-64 rounded border border-slate-300 bg-white px-5 text-lg text-slate-950 focus:border-indigo-500 focus:ring-indigo-500"
                                            @change="markSettingsChanged"
                                        >
                                            <option value="none">Do not collect</option>
                                            <option value="responder">Responder input</option>
                                            <option value="verified">Verified email</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">Send responders a copy of their response</span>
                                            <span class="mt-1 block text-base text-slate-600">Requires <strong>Collect email addresses</strong></span>
                                        </span>
                                        <select
                                            v-model="form.settings.sendResponseCopy"
                                            :disabled="form.settings.emailCollectionMode === 'none'"
                                            class="h-14 w-64 rounded border border-slate-300 bg-white px-5 text-lg text-slate-950 disabled:bg-slate-50 disabled:text-slate-400"
                                            @change="markSettingsChanged"
                                        >
                                            <option value="off">Off</option>
                                            <option value="request">When requested</option>
                                            <option value="always">Always</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">Allow response editing</span>
                                            <span class="mt-1 block text-lg text-slate-600">Responses can be changed after being submitted</span>
                                        </span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.allowResponseEditing ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('allowResponseEditing')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.allowResponseEditing ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <p class="pl-11 text-sm font-bold uppercase tracking-widest text-slate-600">Requires sign-in</p>
                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span class="block text-xl font-normal text-slate-950">Limit to 1 response</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.limitOneResponse ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('limitOneResponse')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.limitOneResponse ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>
                                </div>
                            </section>

                            <div class="border-t border-slate-200"></div>

                            <section class="px-11 py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isCohortSettingsOpen = !isCohortSettingsOpen"
                                >
                                    <span>
                                        <span class="flex items-center gap-2 text-xl font-normal text-slate-950">
                                            <span>Peserta & Kelompok (Cohort)</span>
                                            <span
                                                v-if="selectedCohortIds.length > 0"
                                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800"
                                            >
                                                {{ selectedCohortIds.length }} Cohort Terpilih
                                            </span>
                                        </span>
                                        <span class="mt-2 block text-lg text-slate-600">
                                            Batasi kuis agar hanya dapat dikerjakan oleh anggota kelompok/rombel tertentu
                                        </span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 transition-transform', isCohortSettingsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isCohortSettingsOpen" class="mt-10 space-y-6 pl-11">
                                    <div class="grid max-w-xl grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition"
                                            :class="
                                                !isCohortRestricted
                                                    ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20'
                                                    : 'border-slate-200 hover:bg-slate-50'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="cohort_restriction_type"
                                                :checked="!isCohortRestricted"
                                                @change="setCohortRestriction(false)"
                                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div>
                                                <span class="block text-base font-bold text-slate-900">Terbuka untuk Semua (Publik)</span>
                                                <span class="text-sm text-slate-500"
                                                    >Siapapun yang memiliki link dapat mengisi dan mengerjakan kuis ini.</span
                                                >
                                            </div>
                                        </label>

                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition"
                                            :class="
                                                isCohortRestricted
                                                    ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20'
                                                    : 'border-slate-200 hover:bg-slate-50'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="cohort_restriction_type"
                                                :checked="isCohortRestricted"
                                                @change="setCohortRestriction(true)"
                                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div>
                                                <span class="block text-base font-bold text-slate-900">Khusus Cohort Tertentu</span>
                                                <span class="text-sm text-slate-500"
                                                    >Hanya murid yang terdaftar di kelompok terpilih yang dapat mengakses kuis.</span
                                                >
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Cohort Checklist when restricted -->
                                    <div v-if="isCohortRestricted" class="mt-5 space-y-3">
                                        <p class="text-sm font-bold uppercase tracking-wider text-slate-500">Pilih Cohort yang Diizinkan:</p>

                                        <div
                                            v-if="props.quizForm?.availableCohorts?.length"
                                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3"
                                        >
                                            <div
                                                v-for="c in props.quizForm.availableCohorts"
                                                :key="c.id"
                                                @click="toggleCohort(c.id)"
                                                class="flex cursor-pointer select-none items-center justify-between rounded-2xl border p-3.5 transition"
                                                :class="
                                                    selectedCohortIds.includes(c.id)
                                                        ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20'
                                                        : 'border-slate-200 hover:bg-slate-50'
                                                "
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="flex h-5 w-5 items-center justify-center rounded-md border"
                                                        :class="
                                                            selectedCohortIds.includes(c.id)
                                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                                : 'border-slate-300 bg-white'
                                                        "
                                                    >
                                                        <Check v-if="selectedCohortIds.includes(c.id)" class="h-3.5 w-3.5 stroke-[3]" />
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-bold text-slate-900">{{ c.name }}</div>
                                                        <div v-if="c.code" class="font-mono text-xs text-slate-400">{{ c.code }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                                        >
                                            <span
                                                >Belum ada Cohort yang tersedia di sistem. Buat Cohort terlebih dahulu di menu Kelompok Belajar.</span
                                            >
                                            <Link :href="route('cohorts.index')" class="ml-2 font-bold underline">Kelola Cohort</Link>
                                        </div>

                                        <p v-if="selectedCohortIds.length === 0" class="text-sm font-medium text-red-600">
                                            * Harap pilih minimal satu Cohort jika pembatasan aktif, atau pilih "Terbuka untuk Semua".
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <div class="border-t border-slate-200"></div>

                            <section class="px-11 py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isPresentationSettingsOpen = !isPresentationSettingsOpen"
                                >
                                    <span>
                                        <span class="block text-xl font-normal text-slate-950">Presentation</span>
                                        <span class="mt-2 block text-lg text-slate-600">Manage how the form and responses are presented</span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 transition-transform', isPresentationSettingsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isPresentationSettingsOpen" class="mt-10 space-y-9">
                                    <p class="pl-11 text-sm font-bold uppercase tracking-widest text-slate-600">Form presentation</p>
                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span class="block text-xl font-normal text-slate-950">Show progress bar</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.showProgress ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('showProgress')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.showProgress ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span class="block text-xl font-normal text-slate-950">Shuffle question order</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.shuffleQuestions ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('shuffleQuestions')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.shuffleQuestions ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <p class="pl-11 text-sm font-bold uppercase tracking-widest text-slate-600">After submission</p>
                                    <div class="flex items-start justify-between gap-8 pl-11">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">Confirmation message</span>
                                            <span class="mt-2 block text-lg italic text-slate-600">{{ form.settings.confirmationMessage }}</span>
                                        </span>
                                        <input
                                            v-model="form.settings.confirmationMessage"
                                            type="text"
                                            class="h-11 w-80 rounded border border-transparent px-3 text-right text-lg font-semibold text-blue-600 outline-none hover:border-slate-200 focus:border-indigo-500 focus:text-slate-900"
                                            @input="markSettingsChanged"
                                        />
                                    </div>

                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span class="block text-xl font-normal text-slate-950">Show link to submit another response</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.showSubmitAnotherResponse ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('showSubmitAnotherResponse')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.showSubmitAnotherResponse ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">View results summary</span>
                                            <span class="mt-1 block text-lg text-slate-600">Share results summary with respondents.</span>
                                        </span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.showResultsSummary ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('showResultsSummary')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.showResultsSummary ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <p class="pl-11 text-sm font-bold uppercase tracking-widest text-slate-600">Restrictions</p>
                                    <div class="flex items-center justify-between gap-8 pl-11">
                                        <span class="block text-xl font-normal text-slate-950">Disable auto-save for all respondents</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.disableRespondentAutosave ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('disableRespondentAutosave')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.disableRespondentAutosave ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>

                    <section class="mt-5 rounded-xl border border-slate-300 bg-white shadow-sm">
                        <div class="px-7 py-7">
                            <h2 class="text-2xl font-normal text-slate-950">Security & Limits</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <div class="flex items-center justify-between gap-6 px-11 py-12">
                                <div>
                                    <h3 class="text-xl font-normal text-slate-950">Lock quiz on tab switch</h3>
                                    <p class="mt-2 text-lg text-slate-600">
                                        Automatically lock attempt if respondent switches tab or blurs window. Requires approval to unlock.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    :class="[
                                        'relative h-5 w-11 rounded-full transition',
                                        form.settings.lockOnBlur ? 'bg-indigo-500' : 'bg-slate-300',
                                    ]"
                                    @click="toggleSetting('lockOnBlur')"
                                >
                                    <span
                                        :class="[
                                            'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                            form.settings.lockOnBlur ? 'left-5' : '-left-1',
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div class="border-t border-slate-200"></div>

                            <div class="flex items-center justify-between gap-6 px-11 py-12">
                                <div>
                                    <h3 class="text-xl font-normal text-slate-950">Batas Waktu Pengerjaan (Menit)</h3>
                                    <p class="mt-2 text-lg text-slate-600">Batasi waktu pengisian kuis (0 atau kosong untuk tanpa batas waktu)</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="form.settings.timeLimit"
                                        type="number"
                                        min="0"
                                        class="h-12 w-28 rounded border border-slate-300 px-4 text-lg focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="markSettingsChanged"
                                    />
                                    <span class="text-lg text-slate-600">menit</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-300 bg-white shadow-sm">
                        <div class="px-7 py-7">
                            <h2 class="text-2xl font-normal text-slate-950">Defaults</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <section class="px-11 py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isFormDefaultsOpen = !isFormDefaultsOpen"
                                >
                                    <span>
                                        <span class="block text-xl font-normal text-slate-950">Form defaults</span>
                                        <span class="mt-2 block text-lg text-slate-600">Settings applied to this form and new forms</span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 transition-transform', isFormDefaultsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isFormDefaultsOpen" class="mt-10 flex items-center justify-between gap-8 pl-11">
                                    <label class="text-xl font-normal text-slate-950" for="defaultCollectEmailMode"
                                        >Collect email addresses by default</label
                                    >
                                    <select
                                        id="defaultCollectEmailMode"
                                        v-model="form.settings.defaultCollectEmailMode"
                                        class="h-14 w-64 rounded border border-slate-300 bg-white px-5 text-lg text-slate-950 focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="markSettingsChanged"
                                    >
                                        <option value="none">Do not collect</option>
                                        <option value="responder">Responder input</option>
                                        <option value="verified">Verified email</option>
                                    </select>
                                </div>
                            </section>

                            <div class="border-t border-slate-200"></div>

                            <section class="px-11 py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isQuestionDefaultsOpen = !isQuestionDefaultsOpen"
                                >
                                    <span>
                                        <span class="block text-xl font-normal text-slate-950">Question defaults</span>
                                        <span class="mt-2 block text-lg text-slate-600">Settings applied to all new questions</span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 transition-transform', isQuestionDefaultsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isQuestionDefaultsOpen" class="mt-10 space-y-8 pl-11">
                                    <div v-if="form.settings.isQuiz" class="flex items-center justify-between gap-8">
                                        <span class="block text-xl font-normal text-slate-950">Make questions required by default</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 rounded-full transition',
                                                form.settings.defaultQuestionRequired ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('defaultQuestionRequired')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.defaultQuestionRequired ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between gap-8">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">Default quiz points</span>
                                            <span class="mt-1 block text-lg text-slate-600">Applied when adding new questions.</span>
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="form.settings.defaultQuestionPoints"
                                                type="number"
                                                min="0"
                                                max="1000"
                                                class="h-12 w-28 rounded border border-slate-300 px-4 text-lg focus:border-indigo-500 focus:ring-indigo-500"
                                                @change="markSettingsChanged"
                                            />
                                            <span class="text-lg text-slate-600">points</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-8">
                                        <span>
                                            <span class="block text-xl font-normal text-slate-950">Media upload limit</span>
                                            <span class="mt-1 block text-lg text-slate-600">Maximum image/video upload size in the editor.</span>
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="form.settings.maxUploadSize"
                                                type="number"
                                                min="1"
                                                max="40"
                                                class="h-12 w-28 rounded border border-slate-300 px-4 text-lg focus:border-indigo-500 focus:ring-indigo-500"
                                                @change="markSettingsChanged"
                                            />
                                            <span class="text-lg text-slate-600">MB</span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>
                </template>

                <UnlockRequestsPanel
                    v-else-if="activeTab === 'keamanan'"
                    :unlock-requests="unlockRequests"
                    @refresh="fetchUnlockRequests"
                    @approve="approveUnlockRequest"
                />
            </div>
        </section>

        <div v-if="showPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Preview</h2>
                    <button type="button" class="rounded-full px-3 py-1 text-slate-500 hover:bg-slate-100" @click="showPreview = false">Close</button>
                </div>
                <div :class="['mb-5 h-3 rounded-full', form.settings.themeColorClass ?? 'bg-indigo-600']"></div>
                <h3 class="text-3xl font-semibold"><RichContent :content="form.title" /></h3>
                <RichContent v-if="form.description" :content="form.description" as="p" class="mt-2 text-slate-500" />
                <div class="mt-6 space-y-5">
                    <div v-for="question in form.questions" :key="`preview-${question.id}`" class="rounded-2xl border border-slate-200 p-5">
                        <p class="flex flex-wrap items-center font-bold">
                            <RichContent :content="question.title" class="flex-1" />
                            <span v-if="question.required" class="ml-1 text-red-500">*</span>
                            <span
                                v-if="form.settings.isQuiz && question.points"
                                class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700"
                            >
                                {{ question.points }} Poin
                            </span>
                        </p>
                        <!-- Preview media display -->
                        <div v-if="question.media && question.media.length" class="mb-4 mt-3 grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="(media, idx) in question.media"
                                :key="idx"
                                class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                            >
                                <img v-if="media.type === 'image' && media.url" :src="media.url" class="max-h-48 w-full object-contain p-2" />
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
                                    class="max-h-48 w-full bg-slate-950"
                                ></video>
                            </div>
                        </div>
                        <div
                            v-if="question.type === 'File upload'"
                            class="mt-4 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500"
                        >
                            File upload area
                        </div>
                        <input
                            v-else-if="question.type === 'Date'"
                            type="date"
                            class="mt-4 rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                        />
                        <input
                            v-else-if="question.type === 'Time'"
                            type="time"
                            class="mt-4 rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                        />
                        <select
                            v-else-if="question.type === 'Drop-down'"
                            v-model="previewAnswers[question.id]"
                            class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                        >
                            <option value="" disabled>Pilih jawaban</option>
                            <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <div v-else-if="scaleQuestionTypes.includes(question.type)" class="mt-4 flex gap-2">
                            <span
                                v-for="option in question.options"
                                :key="option"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-sm"
                            >
                                {{ question.type === 'Rating' ? '★' : option }}
                            </span>
                        </div>
                        <div
                            v-else-if="gridQuestionTypes.includes(question.type)"
                            class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/50"
                        >
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-100">
                                        <th class="p-2.5 font-bold text-slate-600">Baris / Kolom</th>
                                        <th v-for="col in question.columns" :key="col" class="p-2.5 text-center font-bold text-slate-600">
                                            <RichContent :content="col" />
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, rIndex) in question.rows"
                                        :key="row"
                                        class="border-slate-150 border-b transition-colors last:border-0 hover:bg-slate-50"
                                    >
                                        <td class="p-2.5 font-semibold text-slate-700">
                                            <RichContent :content="row" />
                                        </td>
                                        <td v-for="(col, cIndex) in question.columns" :key="col" class="p-2.5 text-center">
                                            <label class="inline-flex cursor-pointer items-center justify-center">
                                                <input
                                                    :type="question.type === 'Tick box grid' ? 'checkbox' : 'radio'"
                                                    :name="`preview-question-grid-row-${question.id}-${rIndex}`"
                                                    :checked="
                                                        question.type === 'Tick box grid'
                                                            ? previewAnswers[question.id]?.[rIndex]?.includes(cIndex)
                                                            : previewAnswers[question.id]?.[rIndex] === cIndex
                                                    "
                                                    class="h-4 w-4 cursor-pointer accent-indigo-600"
                                                    @change="
                                                        selectPreviewGridAnswer(
                                                            question.id,
                                                            rIndex,
                                                            cIndex,
                                                            question.type === 'Tick box grid' ? 'multiple' : 'single',
                                                        )
                                                    "
                                                />
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="question.type === 'Multiple choice'" class="mt-4 space-y-3">
                            <label v-for="option in question.options" :key="option" class="flex items-center gap-3">
                                <input
                                    v-model="previewAnswers[question.id]"
                                    type="radio"
                                    :name="`preview-question-${question.id}`"
                                    :value="option"
                                    class="accent-indigo-600"
                                />
                                <RichContent :content="option" class="flex-1" />
                            </label>
                        </div>
                        <div v-else-if="question.type === 'Checkboxes'" class="mt-4 space-y-3">
                            <label v-for="option in question.options" :key="option" class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :checked="checkboxAnswerIncludes(question, option)"
                                    class="accent-indigo-600"
                                    @change="toggleCheckboxAnswer(question, option)"
                                />
                                <RichContent :content="option" class="flex-1" />
                            </label>
                        </div>
                        <input
                            v-else
                            type="text"
                            class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                            placeholder="Your answer"
                        />
                    </div>
                </div>
            </section>
        </div>

        <div v-if="isImportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="w-full max-w-lg space-y-5 rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-extrabold text-slate-900">Import Bank Soal</h2>
                    <button type="button" class="text-lg font-bold text-slate-400 transition hover:text-slate-600" @click="isImportModalOpen = false">
                        x
                    </button>
                </div>

                <!-- Segmented Tabs: ExamView (.zip) vs Word (.docx) -->
                <div class="flex rounded-2xl bg-slate-100 p-1">
                    <button
                        type="button"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-bold transition',
                            importSource === 'examview' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="importSource = 'examview'"
                    >
                        <Archive class="h-4 w-4" />
                        ExamView (.zip)
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-bold transition',
                            importSource === 'docx' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="importSource = 'docx'"
                    >
                        <FileText class="h-4 w-4" />
                        Word (.docx)
                    </button>
                </div>

                <!-- ExamView Import Tab -->
                <div v-if="importSource === 'examview'" class="space-y-4">
                    <div class="space-y-2 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 text-xs text-indigo-950">
                        <span class="block font-bold text-indigo-900">Panduan Ekspor dari ExamView:</span>
                        <ol class="list-inside list-decimal space-y-1 text-slate-600">
                            <li>
                                Buka bank soal (<code class="font-mono text-indigo-700">.bnk</code>) di aplikasi
                                <strong>ExamView Test Generator</strong>.
                            </li>
                            <li>Pilih menu <strong>File &gt; Export &gt; Blackboard 6.0 - 7.0...</strong></li>
                            <li>Beri nama dan simpan berkas sebagai <strong>.zip</strong>.</li>
                            <li>Unggah berkas <strong>.zip</strong> hasil ekspor tersebut di bawah ini.</li>
                        </ol>
                    </div>

                    <div class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Unggah Berkas ZIP ExamView</label>
                        <div
                            class="border-slate-250 hover:border-indigo-450 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed bg-slate-50/30 p-6 transition-colors"
                            @click="examviewFileInput?.click()"
                        >
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                <Archive class="h-6 w-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-800">Klik untuk memilih berkas ZIP</span>
                            <span class="mt-1 text-[11px] text-slate-500">Dukung file format .zip Blackboard 6.0-7.0 (maks. 25MB)</span>
                        </div>

                        <div
                            v-if="isImportingFile"
                            class="flex items-center justify-center gap-2 rounded-xl bg-indigo-50/50 p-3 text-xs font-bold text-indigo-700"
                        >
                            <span class="mb-1 h-4 w-4 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></span>
                            Sedang mengekstrak dan memproses soal ExamView...
                        </div>

                        <div
                            v-if="importError"
                            class="flex gap-2 rounded-xl border border-red-100 bg-red-50/50 p-3.5 text-xs font-semibold text-red-600"
                        >
                            <span class="font-extrabold">Gagal:</span>
                            <span>{{ importError }}</span>
                        </div>
                    </div>
                </div>

                <!-- Word (.docx) Import Tab -->
                <div v-else class="space-y-4">
                    <p class="text-sm leading-relaxed text-slate-500">
                        Unggah dokumen Word (.docx) yang berisi daftar pertanyaan Anda. Untuk mempermudah impor, silakan gunakan templat resmi di
                        bawah ini.
                    </p>

                    <!-- Clean style template download section -->
                    <div class="flex items-center justify-between rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                <FileText class="h-5 w-5" />
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-slate-800">Templat Soal DOCX</span>
                                <span class="block text-[11px] font-medium text-slate-500">Format: Soal, Opsi, Kunci</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-xl border border-indigo-200 bg-white px-3.5 py-2 text-xs font-bold text-indigo-700 shadow-sm transition hover:bg-slate-50 hover:text-indigo-900"
                            @click="downloadImportTemplate"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Unduh
                        </button>
                    </div>

                    <!-- Import / Upload Box -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Unggah Berkas DOCX</label>
                        <div
                            class="border-slate-250 hover:border-indigo-450 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed bg-slate-50/30 p-6 transition-colors"
                            @click="docxFileInput?.click()"
                        >
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <UploadCloud class="h-6 w-6" />
                            </div>
                            <span class="text-sm font-bold text-slate-800">Klik untuk memilih berkas</span>
                            <span class="mt-1 text-[11px] text-slate-500">Dukung file format .docx (maks. 5MB)</span>
                        </div>

                        <div
                            v-if="isImportingFile"
                            class="flex items-center justify-center gap-2 rounded-xl bg-indigo-50/50 p-3 text-xs font-bold text-indigo-700"
                        >
                            <span class="mb-1 h-4 w-4 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></span>
                            Sedang mengimpor dan memproses soal...
                        </div>

                        <div
                            v-if="importError"
                            class="flex gap-2 rounded-xl border border-red-100 bg-red-50/50 p-3.5 text-xs font-semibold text-red-600"
                        >
                            <span class="font-extrabold">Gagal:</span>
                            <span>{{ importError }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-slate-150 flex justify-end gap-3 border-t pt-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                        @click="isImportModalOpen = false"
                    >
                        Batal
                    </button>
                </div>
            </section>
        </div>

        <!-- Modal Kolaborasi Kuis (Antar Guru) -->
        <div v-if="isCollaboratorModalOpen" class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="w-full max-w-lg space-y-5 rounded-3xl bg-white p-6 shadow-2xl duration-150 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <UserPlus class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Kolaborasi Kuis</h2>
                            <p class="text-xs text-slate-500">Kelola guru yang dapat melihat dan mengedit kuis ini.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        @click="isCollaboratorModalOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Info callout: Only teachers -->
                <div class="flex items-start gap-2.5 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3.5 text-xs text-indigo-950">
                    <School class="mt-0.5 h-4 w-4 shrink-0 text-indigo-600" />
                    <div class="leading-relaxed">
                        <span class="font-bold">Khusus Antar Guru:</span>
                        Kolaborasi kuis hanya dapat dilakukan antar sesama akun Guru. Akun Murid (Siswa) tidak dapat ditambahkan ke kuis.
                    </div>
                </div>

                <!-- Section: Pemilik (Owner) -->
                <div class="space-y-2">
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pemilik Kuis</label>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                                {{ (props.quizForm?.owner?.name || 'G')[0].toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900">{{ props.quizForm?.owner?.name ?? 'Pemilik Form' }}</div>
                                <div class="text-xs text-slate-500">{{ props.quizForm?.owner?.email ?? '-' }}</div>
                            </div>
                        </div>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                            Pemilik
                        </span>
                    </div>
                </div>

                <!-- Section: Daftar Kolaborator -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Kolaborator ({{ props.quizForm?.collaborators?.length ?? 0 }})
                        </label>
                    </div>

                    <div
                        v-if="props.quizForm?.collaborators && props.quizForm.collaborators.length > 0"
                        class="max-h-48 space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="collab in props.quizForm.collaborators"
                            :key="collab.id"
                            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-3 transition hover:border-slate-300"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700"
                                >
                                    {{ collab.name ? collab.name[0].toUpperCase() : 'G' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-bold text-slate-900">{{ collab.name }}</div>
                                    <div class="truncate text-[11px] text-slate-500">{{ collab.email || '-' }}</div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                                    Editor
                                </span>
                                <button
                                    v-if="props.quizForm?.isOwner || currentUser?.is_admin"
                                    type="button"
                                    class="rounded-lg p-1.5 text-red-500 transition hover:bg-red-50"
                                    title="Hapus kolaborator"
                                    @click="removeCollaborator(collab.id, false)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                                <button
                                    v-else-if="currentUser?.id === collab.id"
                                    type="button"
                                    class="rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700 transition hover:bg-amber-100"
                                    @click="removeCollaborator(collab.id, true)"
                                >
                                    Keluar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-slate-200 p-4 text-center">
                        <p class="text-xs text-slate-500">Belum ada guru lain yang ditambahkan sebagai kolaborator.</p>
                    </div>
                </div>

                <!-- Section: Form Undang Kolaborator (Hanya untuk Owner / Superadmin) -->
                <div v-if="props.quizForm?.isOwner || currentUser?.is_admin" class="space-y-3 border-t border-slate-100 pt-2">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700"> Undang Guru Lain </label>

                    <div v-if="props.quizForm?.availableTeachers && props.quizForm.availableTeachers.length > 0" class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <select
                                v-model="selectedTeacherId"
                                class="h-10 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            >
                                <option value="">-- Pilih akun guru untuk diundang --</option>
                                <option v-for="teacher in props.quizForm.availableTeachers" :key="teacher.id" :value="teacher.id">
                                    {{ teacher.name }} ({{ teacher.email || 'tanpa email' }})
                                </option>
                            </select>
                            <button
                                type="button"
                                :disabled="!selectedTeacherId || isInvitingCollaborator"
                                class="shadow-xs flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="inviteCollaborator"
                            >
                                <span
                                    v-if="isInvitingCollaborator"
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                ></span>
                                <UserPlus v-else class="h-3.5 w-3.5" />
                                <span>Undang</span>
                            </button>
                        </div>
                        <p v-if="collaboratorError" class="text-xs font-semibold text-red-600">
                            {{ collaboratorError }}
                        </p>
                    </div>
                    <div v-else class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs text-slate-500">Semua guru telah menjadi kolaborator atau belum ada akun guru lain di sistem.</p>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 pt-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100"
                        @click="isCollaboratorModalOpen = false"
                    >
                        Tutup
                    </button>
                </div>
            </section>
        </div>

        <div v-if="showPublish" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h2 class="text-2xl font-bold">Publish form</h2>
                <p class="mt-2 text-slate-500">Form siap dibagikan. Link tetap memakai slug ini meskipun judul quiz diganti.</p>
                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <label class="text-sm font-bold text-slate-500" for="public-slug">Public link</label>
                    <div class="mt-2 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center">
                        <span class="text-sm font-semibold text-slate-500">{{ `${appOrigin}/forms/` }}</span>
                        <input
                            id="public-slug"
                            v-model="publicSlug"
                            type="text"
                            class="min-w-0 flex-1 border-0 bg-transparent text-sm font-bold text-indigo-600 outline-none"
                            @input="normalizePublicSlug"
                        />
                    </div>
                    <p v-if="slugWarning" class="mt-2 text-sm font-bold text-red-600">{{ slugWarning }}</p>
                    <p class="mt-1 break-all text-sm font-semibold text-indigo-600">{{ shareUrl }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-xl px-4 py-2 font-bold text-slate-600 hover:bg-slate-100" @click="showPublish = false">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2 font-bold text-white hover:bg-indigo-700"
                        :disabled="isSaving"
                        @click="saveDraft(false)"
                    >
                        {{ isSaving ? 'Saving...' : 'Save link' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-emerald-500 px-4 py-2 font-bold text-white hover:bg-emerald-600"
                        :disabled="isSaving || Boolean(slugWarning)"
                        @click="saveDraftAndCopy()"
                    >
                        Copy
                    </button>
                </div>
            </section>
        </div>

        <!-- Elegant Theme Customizer Sidebar -->
        <Transition
            enter-active-class="transform transition ease-in-out duration-300"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition ease-in-out duration-300"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="showThemeSidebar" class="fixed inset-y-0 right-0 z-50 w-80 overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-2xl">
                <div class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="flex items-center gap-2 text-xl font-bold">
                        <Palette class="h-5 w-5 text-indigo-600" />
                        <span>Kustomisasi Tema</span>
                    </h2>
                    <button
                        type="button"
                        class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        @click="showThemeSidebar = false"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Fonts Configuration -->
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Gaya Font</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Font Pertanyaan</label>
                                <select
                                    v-model="form.settings.questionFont"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                                    @change="markChanged('Theme font updated')"
                                >
                                    <option v-for="font in fonts" :key="font.value" :value="font.value">
                                        {{ font.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Font Jawaban</label>
                                <select
                                    v-model="form.settings.answerFont"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                                    @change="markChanged('Theme font updated')"
                                >
                                    <option v-for="font in fonts" :key="font.value" :value="font.value">
                                        {{ font.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-100" />

                    <!-- Color Theme Picker (10 Options) -->
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Warna Tema & Background</h3>
                        <div class="grid grid-cols-5 gap-2.5">
                            <button
                                v-for="theme in colorThemes"
                                :key="theme.name"
                                type="button"
                                class="group relative flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 shadow-sm transition hover:scale-110"
                                :class="[theme.theme]"
                                :title="theme.name"
                                @click="
                                    form.settings.themeColorClass = theme.theme;
                                    form.settings.backgroundColorClass = theme.bg;
                                    markChanged('Theme color updated');
                                "
                            >
                                <span v-if="form.settings.themeColorClass === theme.theme" class="h-3 w-3 rounded-full bg-white shadow-sm"></span>
                            </button>
                        </div>
                    </div>

                    <hr class="border-slate-100" />

                    <!-- Background Patterns Picker (10 Options) -->
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Pola Background (10 Jenis)</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                v-for="pattern in backgroundPatterns"
                                :key="pattern.value"
                                type="button"
                                class="relative h-16 overflow-hidden rounded-xl border p-2 text-left transition hover:border-indigo-400"
                                :class="[
                                    form.settings.backgroundPatternClass === pattern.value
                                        ? 'border-indigo-500 bg-slate-50 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 hover:bg-slate-50',
                                    pattern.value,
                                    form.settings.backgroundColorClass ?? 'bg-[#f0efff]',
                                ]"
                                @click="
                                    form.settings.backgroundPatternClass = pattern.value;
                                    markChanged('Background pattern updated');
                                "
                            >
                                <span
                                    class="relative z-10 block w-fit rounded border border-slate-100 bg-white/95 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-sm"
                                >
                                    {{ pattern.name }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Floating Premium Toast -->
        <Transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="translate-y-10 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in transform"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-10 opacity-0 scale-95"
        >
            <div
                v-if="showToast"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white shadow-xl"
            >
                <div class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></div>
                <span>{{ toastMessage }}</span>
            </div>
        </Transition>
    </main>
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

.tool-button {
    @apply flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:-translate-y-0.5 hover:bg-slate-100 hover:text-indigo-600;
}

.tool-button-primary {
    @apply flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:-translate-y-0.5 hover:bg-indigo-50 hover:text-indigo-600;
}

button[aria-label],
.with-tooltip {
    position: relative;
}

button[aria-label]::after,
.with-tooltip::after {
    content: attr(aria-label);
    pointer-events: none;
    position: absolute;
    left: 50%;
    top: calc(100% + 8px);
    z-index: 70;
    max-width: 190px;
    transform: translate(-50%, -4px);
    white-space: nowrap;
    border-radius: 8px;
    background: #0f172a;
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    color: white;
    opacity: 0;
    box-shadow: 0 10px 25px rgb(15 23 42 / 18%);
    transition:
        opacity 120ms ease,
        transform 120ms ease;
}

button[aria-label]:hover::after,
button[aria-label]:focus-visible::after,
.with-tooltip:hover::after,
.with-tooltip:focus-visible::after {
    transform: translate(-50%, 0);
    opacity: 1;
}
</style>
