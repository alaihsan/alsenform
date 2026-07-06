<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
import { computed, reactive, ref, type Component } from 'vue';

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
    answer: string | string[];
    required: boolean;
    media: { type: 'image' | 'video'; label: string }[];
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
    };
    updateUrl: string;
    publicUrl: string;
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
let nextQuestionId = Math.max(...(props.quizForm?.questions.map((question) => Number(question.id)) ?? [1])) + 1;

const form = reactive({
    title: props.quizForm?.title ?? preset.title,
    description: props.quizForm?.description ?? preset.description,
    questions: [
        ...(props.quizForm?.questions ?? [
            {
                id: 1,
                title: preset.question,
                description: '',
                type: 'Multiple choice' as QuestionType,
                options: [...preset.options],
                answer: '',
                required: true,
                media: [],
            },
        ]),
    ] as Question[],
    settings: props.quizForm?.settings ?? {
        collectEmail: false,
        showProgress: true,
        shuffleQuestions: false,
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
const themeColor = ref('bg-indigo-600');
const previewAnswers = reactive<Record<number, PreviewAnswer>>({});
const publicSlug = ref(props.quizForm?.slug ?? 'untitled-form');
const publicUrl = ref(props.quizForm?.publicUrl ?? 'http://alsenform.test/forms/untitled-form');
const slugWarning = ref('');
const isSaving = ref(false);
const appOrigin = ref(typeof window === 'undefined' ? 'http://alsenform.test' : window.location.origin);

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
const responseCount = computed(() => 0);
const shareUrl = computed(() => publicUrl.value);

const markChanged = (message = 'Draft updated') => {
    statusMessage.value = message;
};

const normalizePublicSlug = () => {
    publicSlug.value =
        publicSlug.value
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'untitled-form';
    slugWarning.value = '';
};

const saveDraft = (publishAfterSave = false) => {
    if (!props.quizForm) {
        return;
    }

    normalizePublicSlug();
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
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                publicUrl.value = `${appOrigin.value}/forms/${publicSlug.value}`;
                markChanged('Draft saved');
                showPublish.value = publishAfterSave;
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
        media: [...question.media],
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
    const previousValue = question.options[optionIndex];
    question.options[optionIndex] = value;

    if (Array.isArray(question.answer)) {
        question.answer = question.answer.map((answer) => (answer === previousValue ? value : answer)).filter(Boolean);
    } else if (question.answer === previousValue) {
        question.answer = value;
    }

    markChanged('Option updated');
};

const removeOption = (question: Question, optionIndex: number) => {
    const removedOption = question.options[optionIndex];
    question.options.splice(optionIndex, 1);
    if (question.options.length === 0 && !noOptionQuestionTypes.includes(question.type)) {
        question.options.push('Option 1');
    }
    if (Array.isArray(question.answer)) {
        question.answer = question.answer.filter((answer) => answer !== removedOption);
    } else if (question.answer === removedOption) {
        question.answer = '';
    }
    markChanged('Option removed');
};

const normalizeCorrectAnswer = (question: Question) => {
    if (!optionQuestionTypes.includes(question.type)) {
        question.answer = '';
        return;
    }

    if (question.type === 'Checkboxes') {
        question.answer = Array.isArray(question.answer)
            ? question.answer.filter((answer) => question.options.includes(answer))
            : question.answer && question.options.includes(question.answer)
              ? [question.answer]
              : [];

        return;
    }

    const currentAnswer = Array.isArray(question.answer) ? question.answer[0] : question.answer;
    question.answer = currentAnswer && question.options.includes(currentAnswer) ? currentAnswer : '';
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

const isCorrectAnswer = (question: Question, option: string) => {
    return Array.isArray(question.answer) ? question.answer.includes(option) : question.answer === option;
};

const setCorrectAnswer = (question: Question, option: string) => {
    if (question.type === 'Checkboxes') {
        const currentAnswer = Array.isArray(question.answer) ? [...question.answer] : [];
        const optionIndex = currentAnswer.indexOf(option);

        if (optionIndex >= 0) {
            currentAnswer.splice(optionIndex, 1);
        } else {
            currentAnswer.push(option);
        }

        question.answer = currentAnswer;
    } else {
        question.answer = question.answer === option ? '' : option;
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
    form.questions.forEach((question) => normalizeChoiceAnswer(question));
    showPreview.value = true;
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
        label: type === 'image' ? 'Gambar pendukung soal' : 'Video pendukung soal',
    });
    markChanged(type === 'image' ? 'Image added to question' : 'Video added to question');
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
    const blob = new Blob([content], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'alsenform-question-template.docx';
    link.click();
    URL.revokeObjectURL(url);
    markChanged('DOCX template downloaded');
};

const importQuestionsFromText = (content: string) => {
    const lines = content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line && !line.toLowerCase().includes('template') && !line.toLowerCase().startsWith('contoh'));

    const importedLines = lines.length ? lines : ['Pertanyaan dari file DOCX | Option 1 | Option 2'];

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

const cycleTheme = () => {
    const colors = ['bg-indigo-600', 'bg-emerald-500', 'bg-fuchsia-500', 'bg-orange-500'];
    const nextIndex = (colors.indexOf(themeColor.value) + 1) % colors.length;
    themeColor.value = colors[nextIndex];
    showPalette.value = true;
    markChanged('Theme changed');
};

const undo = () => markChanged('Undo is ready for the next saved history step');
const redo = () => markChanged('Redo is ready for the next saved history step');
</script>

<template>
    <Head :title="form.title" />

    <main class="min-h-screen bg-[#f0efff] text-slate-900">
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
                        @click="cycleTheme"
                    >
                        <Palette class="h-5 w-5" />
                    </button>
                    <button type="button" class="rounded-full p-2 transition hover:bg-slate-100" aria-label="Preview" @click="openPreview">
                        <Eye class="h-5 w-5" />
                    </button>
                    <button type="button" class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block" aria-label="Undo" @click="undo">
                        <Undo2 class="h-5 w-5" />
                    </button>
                    <button type="button" class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block" aria-label="Redo" @click="redo">
                        <Redo2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 md:block"
                        aria-label="Copy link"
                        @click="copyShareUrl"
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
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:-translate-y-0.5 hover:bg-slate-50"
                        :disabled="isSaving"
                        @click="saveDraft(false)"
                    >
                        {{ isSaving ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-700 lg:px-6"
                        :disabled="isSaving"
                        @click="saveDraft(true)"
                    >
                        {{ isSaving ? 'Saving...' : 'Publish' }}
                    </button>
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
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="copyShareUrl">
                                Copy public link
                            </button>
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="openPreview">
                                Open preview
                            </button>
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="cycleTheme">
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
            <input ref="fileInput" type="file" accept=".docx,.txt,.md" class="hidden" @change="handleImportFile" />
            <div class="space-y-4">
                <p class="text-sm font-medium text-slate-500">{{ statusMessage }}</p>

                <template v-if="activeTab === 'questions'">
                    <section class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm transition duration-200 hover:shadow-md">
                        <div :class="['h-3', themeColor]"></div>
                        <div class="grid gap-3 p-5 sm:p-6">
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full border-0 border-b border-transparent bg-transparent p-0 text-2xl font-normal leading-tight outline-none transition focus:border-indigo-500 sm:text-3xl"
                                @input="markChanged('Title updated')"
                            />
                            <input
                                v-model="form.description"
                                type="text"
                                class="w-full border-0 border-b border-transparent bg-transparent p-0 text-base text-slate-500 outline-none transition focus:border-indigo-500 sm:text-lg"
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
                                        @input="markChanged('Question updated')"
                                    />

                                    <input
                                        v-model="question.description"
                                        type="text"
                                        class="mb-4 w-full border-0 border-b border-transparent bg-transparent p-0 text-sm text-slate-500 outline-none transition focus:border-indigo-500"
                                        placeholder="Description"
                                        @input="markChanged('Description updated')"
                                    />

                                    <div v-if="question.media.length" class="mb-4 grid gap-3 sm:grid-cols-2">
                                        <div
                                            v-for="media in question.media"
                                            :key="`${question.id}-${media.type}-${media.label}`"
                                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-medium text-slate-500"
                                        >
                                            {{ media.label }}
                                        </div>
                                    </div>

                                    <div
                                        v-if="question.type === 'Short answer'"
                                        class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-400"
                                    >
                                        Short answer text
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
                                            <span
                                                v-for="option in question.options"
                                                :key="option"
                                                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-sm font-bold"
                                            >
                                                {{ option }}
                                            </span>
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
                                    </div>

                                    <div v-else-if="question.type === 'Rating'" class="space-y-4">
                                        <div class="flex gap-2 rounded-xl bg-slate-50 p-3 text-amber-400">
                                            <Star v-for="option in question.options" :key="option" class="h-8 w-8 fill-current" />
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
                                            class="flex items-center gap-3 text-base transition hover:translate-x-1 sm:text-lg"
                                        >
                                            <span
                                                :class="[
                                                    'flex h-5 w-5 shrink-0 items-center justify-center border-2 border-slate-300 text-xs font-bold text-slate-400',
                                                    question.type === 'Checkboxes'
                                                        ? 'rounded'
                                                        : question.type === 'Drop-down'
                                                          ? 'rounded-lg border-transparent'
                                                          : 'rounded-full',
                                                ]"
                                            >
                                                {{ question.type === 'Drop-down' ? optionIndex + 1 : '' }}
                                            </span>
                                            <input
                                                :value="question.options[optionIndex]"
                                                type="text"
                                                class="min-w-0 flex-1 border-0 border-b border-transparent bg-transparent p-0 outline-none transition focus:border-indigo-500"
                                                @input="updateOption(question, optionIndex, ($event.target as HTMLInputElement).value)"
                                            />
                                            <button
                                                type="button"
                                                :aria-label="`Jadikan ${option || `Option ${optionIndex + 1}`} sebagai jawaban benar`"
                                                :class="[
                                                    'shrink-0 rounded-full border px-3 py-1 text-xs font-bold transition',
                                                    isCorrectAnswer(question, option)
                                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                        : 'border-slate-200 bg-white text-slate-400 hover:border-emerald-300 hover:text-emerald-600',
                                                ]"
                                                @click.stop="setCorrectAnswer(question, option)"
                                            >
                                                {{ isCorrectAnswer(question, option) ? 'Benar' : 'Kunci' }}
                                            </button>
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
                                            <span v-if="Array.isArray(question.answer) && question.answer.length" class="ml-1">
                                                {{ question.answer.join(', ') }}
                                            </span>
                                            <span v-else-if="!Array.isArray(question.answer) && question.answer" class="ml-1">
                                                {{ question.answer }}
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
                                            <span class="text-slate-900">or</span>
                                            <button
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

                <section v-else-if="activeTab === 'responses'" class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm">
                    <h2 class="text-2xl font-semibold">Responses</h2>
                    <p class="mt-3 text-slate-500">Belum ada respons masuk.</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-sm font-bold text-slate-500">Total</p>
                            <p class="mt-2 text-4xl font-black text-indigo-600">{{ responseCount }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-sm font-bold text-slate-500">Accepting</p>
                            <p class="mt-2 text-xl font-bold text-emerald-600">On</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-sm font-bold text-slate-500">Share URL</p>
                            <button type="button" class="mt-2 truncate text-left text-sm font-bold text-indigo-600" @click="copyShareUrl">
                                {{ shareUrl }}
                            </button>
                        </div>
                    </div>
                </section>

                <section v-else class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm">
                    <h2 class="text-2xl font-semibold">Settings</h2>
                    <div class="mt-6 grid gap-4">
                        <label class="flex items-center justify-between rounded-2xl bg-slate-50 p-5">
                            <span>
                                <span class="block font-bold">Collect email addresses</span>
                                <span class="text-sm text-slate-500">Tambahkan field email otomatis.</span>
                            </span>
                            <input
                                v-model="form.settings.collectEmail"
                                type="checkbox"
                                class="h-5 w-5 accent-indigo-600"
                                @change="markChanged('Settings updated')"
                            />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl bg-slate-50 p-5">
                            <span>
                                <span class="block font-bold">Show progress bar</span>
                                <span class="text-sm text-slate-500">Tampilkan progres saat responden mengisi form.</span>
                            </span>
                            <input
                                v-model="form.settings.showProgress"
                                type="checkbox"
                                class="h-5 w-5 accent-indigo-600"
                                @change="markChanged('Settings updated')"
                            />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl bg-slate-50 p-5">
                            <span>
                                <span class="block font-bold">Shuffle question order</span>
                                <span class="text-sm text-slate-500">Acak urutan pertanyaan untuk setiap responden.</span>
                            </span>
                            <input
                                v-model="form.settings.shuffleQuestions"
                                type="checkbox"
                                class="h-5 w-5 accent-indigo-600"
                                @change="markChanged('Settings updated')"
                            />
                        </label>
                    </div>
                </section>
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
                        <p class="font-bold">{{ question.title }} <span v-if="question.required" class="text-red-500">*</span></p>
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
                        @click="
                            copyShareUrl();
                            showPublish = false;
                        "
                    >
                        Copy
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
.tool-button {
    @apply flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:-translate-y-0.5 hover:bg-slate-100 hover:text-indigo-600;
}

.tool-button-primary {
    @apply flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:-translate-y-0.5 hover:bg-indigo-50 hover:text-indigo-600;
}
</style>
