<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    CalendarDays,
    CheckSquare,
    ChevronDown,
    Circle,
    Clock,
    Eye,
    FileText,
    FileUp,
    Grid3X3,
    Image,
    Link2,
    List,
    Menu,
    MoreVertical,
    Palette,
    PanelTop,
    PlusCircle,
    Puzzle,
    Redo2,
    Star,
    ToggleRight,
    Trash2,
    Type,
    Undo2,
    UserPlus,
    Video,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch, type Component } from 'vue';

type QuestionType =
    | 'Short answer'
    | 'Paragraph'
    | 'Multiple choice'
    | 'Checkboxes'
    | 'Drop-down'
    | 'File upload'
    | 'Linear scale'
    | 'Rating'
    | 'Multiple-choice grid'
    | 'Tick box grid'
    | 'Date'
    | 'Time';

type Question = {
    id: number;
    title: string;
    description: string;
    type: QuestionType;
    options: string[];
    answer: any;
    required: boolean;
    media: { type: 'image' | 'video'; url: string }[];
    points: number;
};

type PreviewAnswer = string | string[];

type QuizFormPayload = {
    id: number;
    title: string;
    description: string;
    slug: string;
    questions: Question[];
    settings: {
        collectEmail: boolean;
        showProgress: boolean;
        shuffleQuestions: boolean;
        isQuiz?: boolean;
        emailCollectionMode?: 'none' | 'verified' | 'responder';
        sendResponseCopy?: 'off' | 'request' | 'always';
        allowResponseEditing?: boolean;
        limitOneResponse?: boolean;
        confirmationMessage?: string;
        showSubmitAnotherResponse?: boolean;
        showResultsSummary?: boolean;
        disableRespondentAutosave?: boolean;
        defaultCollectEmailMode?: 'none' | 'verified' | 'responder';
        defaultQuestionRequired?: boolean;
        defaultQuestionPoints?: number;
    };
    responses: {
        total: number;
        latest: {
            id: number;
            email: string | null;
            submittedAt: string;
        }[];
        questions: {
            id: number | string | null;
            title: string;
            type: string;
            total: number;
            options: {
                label: string;
                count: number;
            }[];
            textAnswers: string[];
        }[];
    };
    updateUrl: string;
    publicUrl: string;
};

const getYoutubeEmbedUrl = (url: string): string | null => {
    if (!url) {
        return null;
    }
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return match && match[2].length === 11 ? `https://www.youtube.com/embed/${match[2]}` : null;
};

const props = defineProps<{
    template: string;
    quizForm?: QuizFormPayload;
}>();

const templatePresets: Record<string, { title: string; description: string; question: string; options: string[] }> = {
    blank: {
        title: 'Untitled form',
        description: 'Form description',
        question: 'Untitled Question',
        options: ['Option 1'],
    },
    'contact-information': {
        title: 'Contact Information',
        description: 'Kumpulkan informasi kontak responden.',
        question: 'Informasi apa yang ingin dikirim?',
        options: ['Nama lengkap', 'Email', 'Nomor WhatsApp'],
    },
    'party-invite': {
        title: 'Party Invite',
        description: 'Konfirmasi undangan acara.',
        question: 'Apakah kamu akan hadir?',
        options: ['Ya, hadir', 'Belum pasti', 'Tidak bisa hadir'],
    },
    'work-request': {
        title: 'Work Request',
        description: 'Detail permintaan pekerjaan untuk tim.',
        question: 'Jenis pekerjaan apa yang dibutuhkan?',
        options: ['Desain', 'Dokumen', 'Perbaikan teknis'],
    },
    rsvp: {
        title: 'RSVP',
        description: 'Konfirmasi kehadiran peserta.',
        question: 'Status kehadiran kamu?',
        options: ['Hadir', 'Tidak hadir', 'Mungkin hadir'],
    },
    't-shirt-sign-up': {
        title: 'T-Shirt Sign Up',
        description: 'Pemesanan kaos dan pilihan ukuran.',
        question: 'Ukuran kaos yang dipilih?',
        options: ['S', 'M', 'L', 'XL'],
    },
};

const preset = templatePresets[props.template] ?? templatePresets.blank;
let nextQuestionId = Math.max(...(props.quizForm?.questions?.map((question) => Number(question.id)) ?? [1])) + 1;

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
                required: Boolean(form.settings.defaultQuestionRequired),
                media: [],
                points: form.settings.defaultQuestionPoints ?? 10,
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
        maxUploadSize: props.quizForm?.settings?.maxUploadSize ?? 20,
        questionFont: props.quizForm?.settings?.questionFont ?? "'Inter', sans-serif",
        answerFont: props.quizForm?.settings?.answerFont ?? "'Inter', sans-serif",
        themeColorClass: props.quizForm?.settings?.themeColorClass ?? 'bg-indigo-600',
        backgroundColorClass: props.quizForm?.settings?.backgroundColorClass ?? 'bg-[#f0efff]',
        backgroundPatternClass: props.quizForm?.settings?.backgroundPatternClass ?? 'pattern-none',
    },
});

const activeTab = ref<'questions' | 'responses' | 'settings'>('questions');
const activeQuestionId = ref(1);
const showPreview = ref(false);
const showPublish = ref(false);
const showMoreMenu = ref(false);
const showPalette = ref(false);
const openTypeMenuQuestionId = ref<number | null>(null);
const draggedQuestionId = ref<number | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const statusMessage = ref('All changes saved locally');
const fonts = [
    { name: 'Inter (Default)', value: "'Inter', sans-serif" },
    { name: 'Outfit (Modern)', value: "'Outfit', sans-serif" },
    { name: 'Plus Jakarta Sans', value: "'Plus Jakarta Sans', sans-serif" },
    { name: 'Montserrat', value: "'Montserrat', sans-serif" },
    { name: 'Roboto', value: "'Roboto', sans-serif" },
    { name: 'Playfair Display', value: "'Playfair Display', serif" },
    { name: 'Lora', value: "'Lora', serif" },
    { name: 'Merriweather', value: "'Merriweather', serif" },
    { name: 'Georgia', value: "'Georgia', serif" },
    { name: 'Fira Code (Monospace)', value: "'Fira Code', monospace" },
];

const colorThemes = [
    { name: 'Classic Indigo', theme: 'bg-indigo-600', bg: 'bg-[#f0efff]' },
    { name: 'Emerald Garden', theme: 'bg-emerald-600', bg: 'bg-[#eefdf5]' },
    { name: 'Rose Blush', theme: 'bg-rose-600', bg: 'bg-[#fff1f2]' },
    { name: 'Amber Warmth', theme: 'bg-amber-600', bg: 'bg-[#fefaf0]' },
    { name: 'Sky Breeze', theme: 'bg-sky-600', bg: 'bg-[#f0f9ff]' },
    { name: 'Fuchsia Fantasy', theme: 'bg-fuchsia-600', bg: 'bg-[#fdf4ff]' },
    { name: 'Violet Mystery', theme: 'bg-violet-600', bg: 'bg-[#f5f3ff]' },
    { name: 'Teal Dream', theme: 'bg-teal-600', bg: 'bg-[#f0fdfa]' },
    { name: 'Orange Warmth', theme: 'bg-orange-600', bg: 'bg-[#fff7ed]' },
    { name: 'Slate Gray', theme: 'bg-slate-700', bg: 'bg-[#f8fafc]' },
];

const backgroundPatterns = [
    { name: 'Solid Background', value: 'pattern-none' },
    { name: 'Polka Dots', value: 'pattern-dots' },
    { name: 'Grid Graph', value: 'pattern-grid' },
    { name: 'Stripes', value: 'pattern-diagonal' },
    { name: 'Waves Ripple', value: 'pattern-waves' },
    { name: 'Zigzag Chevron', value: 'pattern-zigzag' },
    { name: 'Hexagon Cell', value: 'pattern-hexagons' },
    { name: 'Blueprint Grid', value: 'pattern-blueprint' },
    { name: 'Bubbles Circle', value: 'pattern-bubbles' },
    { name: 'Triangle Mesh', value: 'pattern-triangles' },
];

const showThemeSidebar = ref(false);
const previewAnswers = reactive<Record<number, PreviewAnswer>>({});
const publicSlug = ref(props.quizForm?.slug ?? 'untitled-form');
const publicUrl = ref(props.quizForm?.publicUrl ?? 'http://alsenform.test/forms/untitled-form');
const slugWarning = ref('');
const isSaving = ref(false);
const isPublished = ref(props.quizForm?.isPublished ?? false);
const showStatusMenu = ref(false);
const appOrigin = ref(typeof window === 'undefined' ? 'http://alsenform.test' : window.location.origin);
const isResponsesSettingsOpen = ref(true);
const isPresentationSettingsOpen = ref(true);
const isFormDefaultsOpen = ref(true);
const isQuestionDefaultsOpen = ref(true);

const markSettingsChanged = () => {
    form.settings.collectEmail = form.settings.emailCollectionMode !== 'none';
    markChanged('Settings updated');
};

const toggleSetting = (key: keyof typeof form.settings) => {
    const currentValue = Boolean(form.settings[key]);
    (form.settings as Record<string, unknown>)[key as string] = !currentValue;
    markSettingsChanged();
};

const questionTypes = [
    { value: 'Short answer', group: 'text', icon: Menu },
    { value: 'Paragraph', group: 'text', icon: List },
    { value: 'Multiple choice', group: 'choice', icon: Circle },
    { value: 'Checkboxes', group: 'choice', icon: CheckSquare },
    { value: 'Drop-down', group: 'choice', icon: ChevronDown },
    { value: 'File upload', group: 'file', icon: FileText },
    { value: 'Linear scale', group: 'scale', icon: MoreVertical },
    { value: 'Rating', group: 'scale', icon: Star },
    { value: 'Multiple-choice grid', group: 'grid', icon: Grid3X3 },
    { value: 'Tick box grid', group: 'grid', icon: Grid3X3 },
    { value: 'Date', group: 'date', icon: CalendarDays },
    { value: 'Time', group: 'date', icon: Clock },
] as const satisfies readonly { value: QuestionType; group: string; icon: Component }[];

const optionQuestionTypes: QuestionType[] = ['Multiple choice', 'Checkboxes', 'Drop-down'];
const noOptionQuestionTypes: QuestionType[] = ['Short answer', 'Paragraph', 'File upload', 'Date', 'Time'];
const scaleQuestionTypes: QuestionType[] = ['Linear scale', 'Rating'];
const gridQuestionTypes: QuestionType[] = ['Multiple-choice grid', 'Tick box grid'];

const questionTypeIcon = (type: QuestionType) => questionTypes.find((questionType) => questionType.value === type)?.icon ?? Circle;

const activeQuestion = computed(() => form.questions.find((question) => question.id === activeQuestionId.value) ?? form.questions[0]);
const responseCount = computed(() => props.quizForm?.responses?.total ?? 0);
const shareUrl = computed(() => publicUrl.value);
const responseQuestions = computed(() => props.quizForm?.responses?.questions ?? []);
const latestResponses = computed(() => props.quizForm?.responses?.latest ?? []);
const pieColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#a855f7', '#14b8a6', '#f97316'];

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

const saveDraft = (publishAfterSave = false) => {
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
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                publicUrl.value = `${appOrigin.value}/forms/${publicSlug.value}`;
                markChanged('Draft saved');
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
        answer: type === 'Checkboxes' ? [] : '',
        required: false,
        media: [],
        points: 10,
    };

    form.questions.push(question);
    activeQuestionId.value = question.id;
    activeTab.value = 'questions';
    markChanged('Question added');
};

const duplicateQuestion = (question: Question) => {
    const copy: Question = {
        ...question,
        id: nextQuestionId++,
        title: `${question.title} copy`,
        options: [...question.options],
        answer: Array.isArray(question.answer) ? [...question.answer] : question.answer,
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
    if (!optionQuestionTypes.includes(question.type)) {
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
        question.options = ['Row 1', 'Row 2', 'Column 1', 'Column 2'];
        question.answer = '';
    } else {
        question.answer = '';
    }
    markChanged('Question type changed');
};

const isCorrectAnswer = (question: Question, optionIndex: number) => {
    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer) ? question.answer.map(Number) : [];
        return currentAnswer.includes(optionIndex);
    }
    return question.answer !== '' && Number(question.answer) === optionIndex;
};

const setCorrectAnswer = (question: Question, optionIndex: number) => {
    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer) ? [...question.answer].map(Number) : [];
        const idx = currentAnswer.indexOf(optionIndex);

        if (idx >= 0) {
            currentAnswer.splice(idx, 1);
        } else {
            currentAnswer.push(optionIndex);
        }

        question.answer = currentAnswer;
    } else {
        question.answer = optionIndex;
    }

    markChanged('Answer key updated');
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

    const fromIndex = form.questions.findIndex((question) => question.id === fromId);
    const toIndex = form.questions.findIndex((question) => question.id === toId);

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
    const content = [
        'Alsen Form Question Import Template',
        '',
        'Tulis satu soal per baris.',
        'Gunakan format: Pertanyaan | Opsi 1 | Opsi 2 | Opsi 3',
        '',
        'Contoh:',
        'Apa ibu kota Indonesia? | Jakarta | Bandung | Surabaya',
        'Jelaskan proses daur air.',
    ].join('\n');
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'alsenform-question-template.txt';
    link.click();
    URL.revokeObjectURL(url);
    markChanged('TXT template downloaded');
};

const importQuestionsFromText = (content: string) => {
    const lines = content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line && !line.toLowerCase().includes('template') && !line.toLowerCase().startsWith('contoh'));

    const importedLines = lines.length ? lines : ['Pertanyaan dari file TXT | Option 1 | Option 2'];

    importedLines.forEach((line) => {
        const parts = line
            .split('|')
            .map((part) => part.trim())
            .filter(Boolean);
        const question: Question = {
            id: nextQuestionId++,
            title: parts[0] ?? 'Imported Question',
            description: '',
            type: parts.length > 1 ? 'Multiple choice' : 'Paragraph',
            options: parts.length > 1 ? parts.slice(1) : [],
            required: false,
            media: [],
            points: 10,
        };
        form.questions.push(question);
        activeQuestionId.value = question.id;
    });

    activeTab.value = 'questions';
    markChanged(`${importedLines.length} question(s) imported`);
};

const handleImportFile = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    const content = await file.text().catch(() => '');
    importQuestionsFromText(content);
    input.value = '';
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
    form.questions.forEach((q) => normalizeCorrectAnswer(q));

    // Record initial state in undo stack
    recordHistory();
});

// Auto-save to server logic (every 10 seconds)
const autoSave = useDebounceFn(() => {
    if (props.quizForm) {
        saveDraft(false);
    }
}, 10000); // 10 seconds debounce

// Watch form changes (updates LocalStorage in real-time)
watch(
    () => [form.title, form.description, form.questions, form.settings],
    () => {
        statusMessage.value = 'Saving changes...';

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
                <Link
                    :href="route('dashboard')"
                    class="grid h-9 w-9 shrink-0 grid-cols-2 gap-1 rounded-xl bg-indigo-500 p-1.5 text-white shadow-[0_3px_0_#4338ca] transition hover:-translate-y-0.5 hover:shadow-[0_5px_0_#4338ca]"
                    aria-label="Back to dashboard"
                >
                    <span class="rounded-lg bg-white/95"></span>
                    <span class="rounded-lg bg-white/70"></span>
                    <span class="rounded-lg bg-white/70"></span>
                    <span class="rounded-lg bg-white/95"></span>
                </Link>

                <div class="flex min-w-0 items-center gap-3">
                    <input
                        v-model="form.title"
                        type="text"
                        class="min-w-0 max-w-[170px] border-0 bg-transparent p-0 text-lg font-medium outline-none transition focus:border-b focus:border-indigo-500 sm:max-w-md sm:text-xl"
                        @input="markChanged('Title updated')"
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
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 md:block"
                        aria-label="Invite collaborators"
                        @click="markChanged('Invite collaborator opened')"
                    >
                        <UserPlus class="h-5 w-5" />
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
                    v-for="tab in ['questions', 'responses', 'settings']"
                    :key="tab"
                    type="button"
                    :class="[
                        'px-3 pb-2.5 text-sm font-bold capitalize transition',
                        activeTab === tab ? 'border-b-4 border-indigo-600 text-indigo-700' : 'text-slate-700 hover:text-indigo-600',
                    ]"
                    @click="activeTab = tab as 'questions' | 'responses' | 'settings'"
                >
                    {{ tab }}
                </button>
            </nav>
        </header>

        <section class="mx-auto grid max-w-[980px] grid-cols-1 gap-4 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1fr)_60px]">
            <input ref="fileInput" type="file" accept=".txt,.md" class="hidden" @change="handleImportFile" />
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
                                <div>
                                    <div
                                        class="mx-auto mb-3 flex w-10 cursor-grab justify-center gap-1 text-slate-300 active:cursor-grabbing"
                                        title="Drag untuk pindahkan soal"
                                    >
                                        <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                        <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                        <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                                    </div>

                                    <input
                                        v-model="question.title"
                                        type="text"
                                        class="mb-6 w-full border-0 border-b border-slate-400 bg-slate-50 px-4 py-3 text-lg font-medium outline-none transition focus:border-indigo-600 sm:text-xl"
                                        :style="{ fontFamily: form.settings.questionFont ?? 'inherit' }"
                                        @input="markChanged('Question updated')"
                                    />

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
                                                    <p v-if="media.uploadError" class="text-[9px] font-bold text-red-600">{{ media.uploadError }}</p>
                                                </div>
                                            </div>

                                            <!-- Link Interface -->
                                            <div v-else class="space-y-2">
                                                <input
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
                                            Short answer text
                                        </div>
                                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
                                            <label class="mb-1 block text-xs font-bold text-emerald-800"
                                                >Kunci Jawaban Benar (Pencocokan Teks):</label
                                            >
                                            <input
                                                v-model="question.answer"
                                                type="text"
                                                placeholder="Masukkan kunci jawaban benar..."
                                                class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500"
                                                @input="markChanged('Answer key updated')"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="question.type === 'Paragraph'"
                                        class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-400"
                                    >
                                        Long answer text
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
                                        <input type="date" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-500" />
                                    </div>

                                    <div
                                        v-else-if="question.type === 'Time'"
                                        class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"
                                    >
                                        <input type="time" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-500" />
                                    </div>

                                    <div v-else-if="question.type === 'Linear scale'" class="space-y-4">
                                        <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 p-3">
                                            <button
                                                v-for="option in question.options"
                                                :key="option"
                                                type="button"
                                                :class="[
                                                    'flex h-9 w-9 items-center justify-center rounded-full border text-sm font-bold transition',
                                                    question.answer === option
                                                        ? 'border-emerald-500 bg-emerald-500 text-white shadow-sm'
                                                        : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-300',
                                                ]"
                                                @click.stop="
                                                    question.answer = option;
                                                    markChanged('Answer key updated');
                                                "
                                            >
                                                {{ option }}
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-sm font-bold text-indigo-600"
                                            @click.stop="
                                                question.options.push(String(question.options.length + 1));
                                                markChanged('Scale extended');
                                            "
                                        >
                                            Add scale point
                                        </button>
                                        <div
                                            v-if="question.answer"
                                            class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                        >
                                            <span class="font-bold">Kunci jawaban benar:</span> {{ question.answer }}
                                        </div>
                                    </div>

                                    <div v-else-if="question.type === 'Rating'" class="space-y-4">
                                        <div class="flex gap-2 rounded-xl bg-slate-50 p-3 text-slate-300">
                                            <button
                                                v-for="option in question.options"
                                                :key="option"
                                                type="button"
                                                :class="[
                                                    'transition',
                                                    Number(question.answer) >= Number(option)
                                                        ? 'text-amber-400'
                                                        : 'text-slate-300 hover:text-amber-200',
                                                ]"
                                                @click.stop="
                                                    question.answer = option;
                                                    markChanged('Answer key updated');
                                                "
                                            >
                                                <Star class="h-8 w-8 fill-current" />
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-sm font-bold text-indigo-600"
                                            @click.stop="
                                                question.options.push(String(question.options.length + 1));
                                                markChanged('Rating extended');
                                            "
                                        >
                                            Add rating point
                                        </button>
                                        <div
                                            v-if="question.answer"
                                            class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-2 text-xs text-emerald-800"
                                        >
                                            <span class="font-bold">Kunci jawaban benar (Rating):</span> {{ question.answer }} bintang
                                        </div>
                                    </div>

                                    <div v-else-if="gridQuestionTypes.includes(question.type)" class="space-y-3">
                                        <div class="grid grid-cols-[1fr_1fr_1fr] gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                                            <span></span>
                                            <span class="font-bold text-slate-500">Column 1</span>
                                            <span class="font-bold text-slate-500">Column 2</span>
                                            <span class="font-bold text-slate-500">Row 1</span>
                                            <span class="h-5 w-5 rounded-full border-2 border-slate-300"></span>
                                            <span class="h-5 w-5 rounded-full border-2 border-slate-300"></span>
                                            <span class="font-bold text-slate-500">Row 2</span>
                                            <span class="h-5 w-5 rounded-full border-2 border-slate-300"></span>
                                            <span class="h-5 w-5 rounded-full border-2 border-slate-300"></span>
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
                                            <!-- Checkbox/Radio Correct Answer Selector on the left -->
                                            <button
                                                type="button"
                                                :aria-label="`Pilih ${option || `Opsi ${optionIndex + 1}`} sebagai jawaban benar`"
                                                :class="[
                                                    'flex h-6 w-6 shrink-0 items-center justify-center border-2 transition',
                                                    question.type === 'Checkboxes' ? 'rounded' : 'rounded-full',
                                                    isCorrectAnswer(question, optionIndex)
                                                        ? 'border-emerald-500 bg-emerald-500 text-white shadow-sm'
                                                        : 'border-slate-300 bg-white hover:border-emerald-400',
                                                ]"
                                                @click.stop="setCorrectAnswer(question, optionIndex)"
                                            >
                                                <span
                                                    v-if="isCorrectAnswer(question, optionIndex)"
                                                    :class="[
                                                        'block h-2 w-2 bg-white',
                                                        question.type === 'Checkboxes' ? 'rounded-sm' : 'rounded-full',
                                                    ]"
                                                ></span>
                                            </button>

                                            <!-- Dropdown number index if it is a Drop-down type -->
                                            <span v-if="question.type === 'Drop-down'" class="text-xs font-bold text-slate-400">
                                                #{{ optionIndex + 1 }}
                                            </span>

                                            <input
                                                :value="question.options[optionIndex]"
                                                type="text"
                                                class="min-w-0 flex-1 border-0 border-b border-transparent bg-transparent p-0 outline-none transition focus:border-indigo-500"
                                                :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                                @input="updateOption(question, optionIndex, ($event.target as HTMLInputElement).value)"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-full px-2 text-slate-400 transition hover:bg-slate-100 hover:text-red-500"
                                                @click.stop="removeOption(question, optionIndex)"
                                            >
                                                x
                                            </button>
                                        </div>
                                        <div
                                            v-if="optionQuestionTypes.includes(question.type)"
                                            class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-800"
                                        >
                                            <span class="font-bold">Jawaban benar:</span>
                                            <span
                                                v-if="question.type === 'Checkboxes' && Array.isArray(question.answer) && question.answer.length"
                                                class="ml-1"
                                            >
                                                {{
                                                    question.answer
                                                        .map((idx) => question.options[Number(idx)])
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
                                                {{ question.options[Number(question.answer)] }}
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
                                            <span v-if="!question.options.includes('Other') && !isQuestionAnswered(question)" class="text-slate-900"
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
                                            <div v-if="form.settings.isQuiz" class="flex items-center gap-1.5">
                                                <span class="text-xs font-semibold text-slate-500 text-slate-700">Poin:</span>
                                                <input
                                                    v-model.number="question.points"
                                                    type="number"
                                                    min="0"
                                                    class="w-14 rounded-lg border border-slate-300 bg-white px-2 py-1 text-center text-xs font-bold outline-none focus:border-indigo-500"
                                                    @input="markChanged('Points updated')"
                                                />
                                            </div>
                                            <span class="h-8 border-l border-slate-200"></span>
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
                                    <button type="button" aria-label="Import question" class="tool-button" @click.stop="fileInput?.click()">
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
                            <button
                                type="button"
                                class="with-tooltip rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-indigo-600 hover:bg-indigo-50"
                                aria-label="Copy public link"
                                @click="copyShareUrl"
                            >
                                Copy link
                            </button>
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

                <template v-else>
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
                                                max="500"
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
            </div>
        </section>

        <div v-if="showPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Preview</h2>
                    <button type="button" class="rounded-full px-3 py-1 text-slate-500 hover:bg-slate-100" @click="showPreview = false">Close</button>
                </div>
                <div :class="['mb-5 h-3 rounded-full', themeColor]"></div>
                <h3 class="text-3xl font-semibold">{{ form.title }}</h3>
                <p class="mt-2 text-slate-500">{{ form.description }}</p>
                <div class="mt-6 space-y-5">
                    <div v-for="question in form.questions" :key="`preview-${question.id}`" class="rounded-2xl border border-slate-200 p-5">
                        <p class="flex flex-wrap items-center font-bold">
                            <span>{{ question.title }}</span>
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
                        <div v-else-if="gridQuestionTypes.includes(question.type)" class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                            Grid answer preview
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
                                <span>{{ option }}</span>
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
                                <span>{{ option }}</span>
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
