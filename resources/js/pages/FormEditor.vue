<script setup lang="ts">
import AccountMenu from '@/components/AccountMenu.vue';
import RichContent from '@/components/RichContent.vue';
import AddonExamSwitches from '@/components/addons/AddonExamSwitches.vue';
import AddonGallery, { type AddonEntry } from '@/components/addons/AddonGallery.vue';
import ExamTokenCard from '@/components/addons/ExamTokenCard.vue';
import MisconceptionPanel, { type MisconceptionMap } from '@/components/addons/MisconceptionPanel.vue';
import RemedialPanel, { type RemedialOverview } from '@/components/addons/RemedialPanel.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import GradebookTable from '@/components/form-editor/GradebookTable.vue';
import IdentityFieldsChecklist, { type IdentityChoice } from '@/components/form-editor/IdentityFieldsChecklist.vue';
import MathArabicToolbar from '@/components/form-editor/MathArabicToolbar.vue';
import QuestionBankPicker from '@/components/form-editor/QuestionBankPicker.vue';
import QuestionDrawSettings, { type QuestionDraw } from '@/components/form-editor/QuestionDrawSettings.vue';
import ClassMapPanel, { type ClassMapStudent } from '@/components/form-editor/ClassMapPanel.vue';
import GradingPanel from '@/components/form-editor/GradingPanel.vue';
import ItemAnalysisPanel, { type ItemAnalysis } from '@/components/form-editor/ItemAnalysisPanel.vue';
import QuestionLabelsEditor, { type LabelSuggestions, type QuestionLabels } from '@/components/form-editor/QuestionLabelsEditor.vue';
import RichTextInput from '@/components/form-editor/RichTextInput.vue';
import SectionTargetSelect from '@/components/form-editor/SectionTargetSelect.vue';
import SelectionFormatToolbar from '@/components/form-editor/SelectionFormatToolbar.vue';
import UnlockRequestsPanel, { type UnlockPanelEntry } from '@/components/form-editor/UnlockRequestsPanel.vue';
import {
    backgroundPatterns,
    colorThemes,
    fonts,
    gridQuestionTypes,
    noOptionQuestionTypes,
    optionQuestionTypes,
    OTHER_OPTIONS,
    pieColors,
    isPlaceholderTitle,
    questionTypeLabel,
    questionTypes,
    scaleQuestionTypes,
    templatePresets,
    UNTITLED_QUESTION,
} from '@/constants/form-editor';
import FormDesigner from '@/components/form-design/FormDesigner.vue';
import { BRAND_ICON, BRAND_NAME } from '@/lib/brand';
import { DEFAULT_DESIGN, designVariables, logoJustify, pageBackgroundStyle, useDesignCss, withDesignDefaults, type FormDesign } from '@/lib/formDesign';
import { CONTENT_ITEM_TYPES, type PreviewAnswer, type Question, type QuestionType, type QuizFormPayload, type SectionTarget } from '@/types/quiz';
import { hasMathOrArabic, isPredominantlyArabic, stripRichFormatting } from '@/utils/rich-content';
import { BRANCHING_TYPES, FIRST_SECTION, NEXT_SECTION, SUBMIT_FORM, flowSections } from '@/utils/section-flow';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    AlertTriangle,
    AlignCenter,
    AlignLeft,
    AlignRight,
    Archive,
    Library,
    Check,
    ChevronDown,
    Circle,
    Download,
    ExternalLink,
    Eye,
    FileText,
    FileUp,
    History,
    Image,
    Key,
    Link2,
    Maximize2,
    MoreVertical,
    Paintbrush,
    Palette,
    PanelTop,
    PlusCircle,
    PenLine,
    Puzzle,
    Redo2,
    School,
    Sparkles,
    Star,
    ToggleRight,
    Trash2,
    Type,
    Undo2,
    UploadCloud,
    UserPlus,
    Video,
    X,
    ZoomIn,
    CornerDownRight,
    GitBranch,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import MediaLightboxModal from '@/components/MediaLightboxModal.vue';

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
            // Width, alignment and caption of the media are kept too; not the state of an upload.
            media: (q.media ?? []).map((m: any) => {
                const kept = { ...m, type: m.type, url: m.url ?? '' };
                delete kept.isUploading;
                delete kept.uploadError;
                return kept;
            }),
            points: q.points ?? 1,
            // Kept, or every save would drop it.
            ...(q.explanation ? { explanation: q.explanation } : {}),
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
                points: props.quizForm?.settings?.defaultQuestionPoints ?? 1,
            },
        ]),
    ] as Question[],
    settings: {
        collectEmail: props.quizForm?.settings?.collectEmail ?? false,
        showProgress: props.quizForm?.settings?.showProgress ?? true,
        shuffleQuestions: props.quizForm?.settings?.shuffleQuestions ?? false,
        shuffleOptions: props.quizForm?.settings?.shuffleOptions ?? false,
        questionDraw: {
            enabled: false,
            mode: 'count',
            count: 0,
            ...(props.quizForm?.settings?.questionDraw ?? {}),
            perDifficulty: { mudah: 0, sedang: 0, sulit: 0, ...(props.quizForm?.settings?.questionDraw?.perDifficulty ?? {}) },
        } as QuestionDraw,
        isQuiz: props.quizForm?.settings?.isQuiz ?? true,
        // "verified" was never verified: it asks the email like "responder".
        emailCollectionMode: (props.quizForm?.settings?.emailCollectionMode ?? (props.quizForm?.settings?.collectEmail ? 'responder' : 'none')) === 'none' ? 'none' : 'responder',
        limitOneResponse: props.quizForm?.settings?.limitOneResponse ?? false,
        maxAttempts: props.quizForm?.settings?.maxAttempts ?? (props.quizForm?.settings?.limitOneResponse ? 1 : 0),
        // Add-ons switched on or off for this exam (absent: the add-on's default).
        addons: { ...(props.quizForm?.settings?.addons ?? {}) } as Record<string, boolean>,
        confirmationMessage: props.quizForm?.settings?.confirmationMessage ?? 'Jawaban Anda sudah tercatat.',
        showSubmitAnotherResponse: props.quizForm?.settings?.showSubmitAnotherResponse ?? true,
        showResultsSummary: props.quizForm?.settings?.showResultsSummary ?? false,
        showMadeWith: props.quizForm?.settings?.showMadeWith ?? true,
        // What students see once results are shown: the grade only, or also the key and pembahasan.
        resultsDetail: (props.quizForm?.settings?.resultsDetail ?? 'score') as 'score' | 'full',
        // When students may start the exam (ISO times, empty: any time).
        schedule: {
            opensAt: props.quizForm?.settings?.schedule?.opensAt ?? null,
            closesAt: props.quizForm?.settings?.schedule?.closesAt ?? null,
        } as { opensAt: string | null; closesAt: string | null },
        disableRespondentAutosave: props.quizForm?.settings?.disableRespondentAutosave ?? false,
        defaultQuestionRequired: props.quizForm?.settings?.defaultQuestionRequired ?? false,
        defaultQuestionPoints: props.quizForm?.settings?.defaultQuestionPoints ?? 1,
        maxUploadSize: Math.min(props.quizForm?.settings?.maxUploadSize ?? 20, 40),
        questionFont: props.quizForm?.settings?.questionFont ?? "'Inter', sans-serif",
        answerFont: props.quizForm?.settings?.answerFont ?? "'Inter', sans-serif",
        themeColorClass: props.quizForm?.settings?.themeColorClass ?? 'bg-indigo-600',
        backgroundColorClass: props.quizForm?.settings?.backgroundColorClass ?? 'bg-[#f0efff]',
        backgroundPatternClass: props.quizForm?.settings?.backgroundPatternClass ?? 'pattern-none',
        // Desainer Form add-on: null until the teacher designs the exam.
        design: props.quizForm?.settings?.design ? withDesignDefaults(props.quizForm.settings.design) : (null as FormDesign | null),
        lockOnBlur: props.quizForm?.settings?.lockOnBlur ?? false,
        timeLimit: props.quizForm?.settings?.timeLimit ?? 0,
        questionsPerPage: props.quizForm?.settings?.questionsPerPage ?? 'all',
        respondentFields: [...(props.quizForm?.settings?.respondentFields ?? [])] as ('name' | 'class')[],
    },
});

const activeTab = ref<'questions' | 'responses' | 'settings' | 'keamanan'>('questions');
const TAB_LABELS: Record<string, string> = { questions: 'Soal', responses: 'Jawaban', settings: 'Pengaturan', keamanan: 'Keamanan' };
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
    markChanged('Kelompok diperbarui');
};

const setCohortRestriction = (restricted: boolean) => {
    isCohortRestricted.value = restricted;
    if (!restricted) {
        selectedCohortIds.value = [];
    }
    markChanged('Batasan kelompok diperbarui');
};
const showPreview = ref(false);
const showPublish = ref(false);
const showMoreMenu = ref(false);
const openTypeMenuQuestionId = ref<number | null>(null);
const editingAnswerKeyQuestionId = ref<number | null>(null);
const draggedQuestionId = ref<number | null>(null);
const draggableQuestionId = ref<number | null>(null);
const activeLightboxMedia = ref<{ url: string; type: 'image' | 'video'; caption?: string } | null>(null);

const enableCardDrag = (id: number) => {
    draggableQuestionId.value = id;
};

const disableCardDrag = () => {
    if (draggedQuestionId.value === null) {
        draggableQuestionId.value = null;
    }
};

const setMediaWidth = (media: any, width: string) => {
    media.width = width;
    markChanged('Ukuran media diperbarui');
};

const setMediaAlign = (media: any, align: 'left' | 'center' | 'right') => {
    media.align = align;
    markChanged('Perataan media diperbarui');
};

const openLightbox = (media: any) => {
    activeLightboxMedia.value = {
        url: media.url,
        type: media.type,
        caption: media.caption ?? '',
    };
};
const docxFileInput = ref<HTMLInputElement | null>(null);
const examviewFileInput = ref<HTMLInputElement | null>(null);
const importSource = ref<'examview' | 'docx' | 'bank'>('examview');
const isImportModalOpen = ref(false);
const isImportingFile = ref(false);
const importError = ref('');
const importWarnings = ref<string[]>([]);
const importSummary = ref('');
watch(isImportModalOpen, (isOpen) => {
    if (!isOpen) {
        importWarnings.value = [];
        importSummary.value = '';
    }
});
watch(importSource, () => {
    importError.value = '';
    importWarnings.value = [];
    importSummary.value = '';
});
/** Values accepted after "Tipe:" in the Word import (see DocxImportService::TYPE_ALIASES). */
const docxImportTypes = [
    'Pilihan Ganda',
    'Kotak Centang',
    'Drop-down',
    'Benar Salah',
    'Isian Singkat',
    'Uraian',
    'Menjodohkan',
    'Kisi Pilihan Ganda',
    'Kisi Kotak Centang',
    'Skala Linear',
    'Rating',
    'Tanggal',
    'Waktu',
];
const page = usePage<any>();
const currentUser = computed(() => page.props.auth?.user);
const isCollaboratorModalOpen = ref(false);
const selectedTeacherId = ref<number | ''>('');
const isInvitingCollaborator = ref(false);
const collaboratorError = ref('');

const openCollaboratorModal = () => {
    if (!props.quizForm?.id) {
        triggerToast('Simpan formulir terlebih dahulu sebelum mengundang kolaborator.');
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

    const confirmMsg = isSelf ? 'Apakah Anda yakin ingin keluar dari kolaborasi formulir ini?' : 'Hapus guru ini dari daftar kolaborator?';

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

const statusMessage = ref('Semua perubahan tersimpan');
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
const isQuestionDefaultsOpen = ref(true);

const unlockRequests = ref<UnlockPanelEntry[]>([]);
const lockedStudentCount = computed(() => unlockRequests.value.filter((entry) => entry.state === 'locked').length);
const unlockingIds = ref<string[]>([]);
const isUnlockingAll = ref(false);

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

/** Unlock one student: their attempt when there is one, else their unlock request. */
const unlockStudent = async (entry: UnlockPanelEntry) => {
    if (!props.quizForm?.id || unlockingIds.value.includes(entry.id)) {
        return;
    }
    unlockingIds.value = [...unlockingIds.value, entry.id];
    try {
        if (entry.session_id !== null) {
            await axios.post(`/forms/${props.quizForm.id}/sessions/${entry.session_id}/unlock`);
        } else if (entry.request_id !== null) {
            await axios.post(`/unlock-requests/${entry.request_id}/approve`);
        }
        statusMessage.value = `Kunci ${entry.name ?? 'siswa'} dibuka`;
    } catch (error) {
        console.error('Failed to unlock student', error);
        statusMessage.value = 'Gagal membuka kunci. Coba lagi.';
    } finally {
        unlockingIds.value = unlockingIds.value.filter((id) => id !== entry.id);
        fetchUnlockRequests();
    }
};

/** Unlock a student from their card on the Peta Kelas. */
const unlockFromClassMap = (student: ClassMapStudent) =>
    unlockStudent({ id: `map-${student.id}`, session_id: student.id, request_id: null, name: student.name } as UnlockPanelEntry);

const unlockAllStudents = async () => {
    if (!props.quizForm?.id || isUnlockingAll.value) {
        return;
    }
    isUnlockingAll.value = true;
    try {
        const response = await axios.post(`/forms/${props.quizForm.id}/sessions/unlock-all`);
        statusMessage.value = response.data?.message ?? 'Semua siswa dibuka kuncinya';
    } catch (error) {
        console.error('Failed to unlock all students', error);
        statusMessage.value = 'Gagal membuka kunci. Coba lagi.';
    } finally {
        isUnlockingAll.value = false;
        fetchUnlockRequests();
    }
};

let unlockRequestsInterval: any = null;
let unlockRequestsTick = 0;
onMounted(() => {
    fetchUnlockRequests();
    fetchExamProgress();
    // Every 5 s on the Keamanan tab; elsewhere every 15 s while the lock is on, for the tab's count.
    // The number of students who handed in: every 5 s on the Responses tab, else every 15 s.
    unlockRequestsInterval = setInterval(() => {
        unlockRequestsTick++;
        if (activeTab.value === 'keamanan' || (form.settings.lockOnBlur && isPublished.value && unlockRequestsTick % 3 === 0)) {
            fetchUnlockRequests();
        }
        if (activeTab.value === 'responses' || (isPublished.value && unlockRequestsTick % 3 === 0)) {
            fetchExamProgress();
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
    if (newTab === 'responses') {
        fetchExamProgress();
    }
});

const markSettingsChanged = () => {
    form.settings.collectEmail = form.settings.emailCollectionMode !== 'none';
    markChanged('Pengaturan diperbarui');
};

/*
 * Add-ons, like in Google Workspace: installed once per teacher (or by an admin for every teacher)
 * in the gallery, then switched on or off for this exam. An add-on works on an exam while the
 * exam's owner has it installed and the exam has not switched it off.
 */
const addonCatalog = ref<AddonEntry[]>(props.quizForm?.addons ?? []);
const isAddonGalleryOpen = ref(false);
// Add-ons that are not switched per exam (Rekap Kuis) have nothing to switch here.
const examAddons = computed(() => addonCatalog.value.filter((addon) => addon.perExam && (addon.installed || addon.installedForOwner)));
const isAddonOnForExam = (addon: AddonEntry) => Boolean(addon.installedForOwner) && (form.settings.addons[addon.key] ?? addon.onByDefault);
const itemAnalysisAddon = computed(() => addonCatalog.value.find((addon) => addon.key === 'item-analysis') ?? null);
const examTokenAddon = computed(() => addonCatalog.value.find((addon) => addon.key === 'exam-token') ?? null);
const remedialAddon = computed(() => addonCatalog.value.find((addon) => addon.key === 'remedial') ?? null);
const misconceptionAddon = computed(() => addonCatalog.value.find((addon) => addon.key === 'misconception') ?? null);
const formDesignerAddon = computed(() => addonCatalog.value.find((addon) => addon.key === 'form-designer') ?? null);

// Desainer Form: the exam's design, drawn in the editor while the add-on is on for the exam.
const isDesignerOn = computed(() => Boolean(formDesignerAddon.value && isAddonOnForExam(formDesignerAddon.value)));
const activeDesign = computed<FormDesign | null>(() => (isDesignerOn.value && form.settings.design ? withDesignDefaults(form.settings.design) : null));
const activeDesignVariables = computed(() => (activeDesign.value ? designVariables(activeDesign.value) : undefined));
const themeClass = computed(() => (activeDesign.value ? 'af-accent-bg' : (form.settings.themeColorClass ?? 'bg-indigo-600')));
const showFormDesigner = ref(false);
useDesignCss(activeDesign);

/** The palette button: the Desainer Form while its add-on is on, the simple theme panel otherwise. */
const openThemeEditor = () => {
    if (isDesignerOn.value) {
        if (!form.settings.design) {
            form.settings.design = { ...DEFAULT_DESIGN };
            markChanged('Desain diperbarui');
        }
        showThemeSidebar.value = false;
        showFormDesigner.value = true;

        return;
    }
    showThemeSidebar.value = !showThemeSidebar.value;
};

const updateDesign = (design: FormDesign) => {
    form.settings.design = design;
    markChanged('Desain diperbarui');
};

/** From the simple theme panel: switch the add-on on for this exam (or show the gallery) and open the designer. */
const startFormDesigner = () => {
    const addon = formDesignerAddon.value;
    if (!addon?.installedForOwner) {
        showThemeSidebar.value = false;
        isAddonGalleryOpen.value = true;

        return;
    }
    if (!isAddonOnForExam(addon)) {
        setAddonForExam(addon, true);
    }
    if (!form.settings.design) {
        form.settings.design = { ...DEFAULT_DESIGN };
        markChanged('Desain diperbarui');
    }
    showThemeSidebar.value = false;
    showFormDesigner.value = true;
};

const setAddonForExam = (addon: AddonEntry, on: boolean) => {
    form.settings.addons = { ...form.settings.addons, [addon.key]: on };
    markSettingsChanged();
};

/** Installed or removed in the gallery: the exam is loaded again for what the add-on adds or takes away. */
const onAddonsChanged = (addons: AddonEntry[]) => {
    const previous = new Map(addonCatalog.value.map((addon) => [addon.key, addon]));
    // The owner's installations are the exam's: installing switches its add-on on right away, also
    // while a save is in flight and the exam is not reloaded.
    addonCatalog.value = addons.map((addon) => ({
        ...previous.get(addon.key),
        ...addon,
        ...(props.quizForm?.owner && props.quizForm.owner.id === page.props.auth?.user?.id ? { installedForOwner: addon.installed } : {}),
    }));
    if (!isSaveInFlight) {
        router.reload({ only: ['quizForm'] });
    }
};

watch(
    () => props.quizForm?.addons,
    (addons) => {
        if (addons) {
            addonCatalog.value = addons;
        }
    },
);

/*
 * Jadwal ujian: shown and typed in the device's local time (the school's), stored as ISO times.
 */
const toLocalDateTime = (iso: string | null) => {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
const setSchedule = (key: 'opensAt' | 'closesAt', value: string) => {
    form.settings.schedule = { ...form.settings.schedule, [key]: value ? new Date(value).toISOString() : null };
    markSettingsChanged();
};
const clearSchedule = () => {
    form.settings.schedule = { opensAt: null, closesAt: null };
    markSettingsChanged();
};
const formatScheduleTime = (iso: string) =>
    new Date(iso).toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
const scheduleProblem = computed(() => {
    const { opensAt, closesAt } = form.settings.schedule;
    return opensAt && closesAt && new Date(closesAt) <= new Date(opensAt) ? 'Waktu tutup harus sesudah waktu buka.' : '';
});
const scheduleSummary = computed(() => {
    const { opensAt, closesAt } = form.settings.schedule;
    return [opensAt ? `Dibuka ${formatScheduleTime(opensAt)}` : 'Dibuka kapan saja', closesAt ? `ditutup ${formatScheduleTime(closesAt)}` : 'tanpa waktu tutup'].join(', ') + '.';
});
/** Whether students can start the exam now: published and within its schedule. */
const acceptingNow = computed(() => {
    const { opensAt, closesAt } = form.settings.schedule;
    const now = Date.now();
    if (!isPublished.value) {
        return { on: false, label: 'Tidak', hint: 'Belum diterbitkan' };
    }
    if (opensAt && new Date(opensAt).getTime() > now) {
        return { on: false, label: 'Belum', hint: `Dibuka ${formatScheduleTime(opensAt)}` };
    }
    if (closesAt && new Date(closesAt).getTime() <= now) {
        return { on: false, label: 'Tidak', hint: 'Jadwal ujian sudah ditutup' };
    }
    return { on: true, label: 'Ya', hint: closesAt ? `Ditutup ${formatScheduleTime(closesAt)}` : 'Tanpa waktu tutup' };
});

/** How many times a student may take the quiz (0 is no limit); the older one-response switch follows it. */
const updateMaxAttempts = () => {
    form.settings.limitOneResponse = form.settings.maxAttempts === 1;
    markSettingsChanged();
};

const toggleSetting = (key: keyof typeof form.settings) => {
    const currentValue = Boolean(form.settings[key]);
    (form.settings as Record<string, unknown>)[key as string] = !currentValue;
    markSettingsChanged();
};

const questionTypeIcon = (type: QuestionType) => questionTypes.find((questionType) => questionType.value === type)?.icon ?? Circle;

const activeQuestion = computed(() => form.questions.find((question: Question) => question.id === activeQuestionId.value) ?? form.questions[0]);
const responseCount = computed(() => props.quizForm?.responses?.total ?? 0);

/*
 * Live progress while the exam runs: students who handed in (each once, however many attempts),
 * answers received, and students with the exam open now. New answers refresh the results shown.
 */
const examProgress = ref({
    submitted: props.quizForm?.responses?.students ?? 0,
    responses: props.quizForm?.responses?.total ?? 0,
    working: 0,
});
let isFetchingExamProgress = false;

const fetchExamProgress = async () => {
    const url = props.quizForm?.responses?.progressUrl;
    if (!url || isFetchingExamProgress) {
        return;
    }
    isFetchingExamProgress = true;
    try {
        const response = await axios.get(url);
        examProgress.value = response.data;
        // Not while an edit is being saved: that request brings the results along anyway.
        if (response.data.responses !== responseCount.value && activeTab.value === 'responses' && !isSaveInFlight) {
            router.reload({ only: ['quizForm'] });
        }
    } catch {
        // The next poll tries again.
    } finally {
        isFetchingExamProgress = false;
    }
};

watch(
    () => [props.quizForm?.responses?.total, props.quizForm?.responses?.students],
    ([total, students]) => {
        examProgress.value = { ...examProgress.value, responses: total ?? 0, submitted: students ?? 0 };
    },
);
const shareUrl = computed(() => publicUrl.value);
const responseQuestions = computed(() => props.quizForm?.responses?.questions ?? []);
const latestResponses = computed(() => props.quizForm?.responses?.latest ?? []);

const maxScore = computed(() => props.quizForm?.responses?.maxScore ?? 0);
const exportResponsesUrl = computed(
    () =>
        props.quizForm?.responses?.exportUrl ??
        props.quizForm?.exportResponsesUrl ??
        (props.quizForm ? `/forms/${props.quizForm.id}/responses/export` : '#'),
);
const gradebook = computed(() => props.quizForm?.responses?.gradebook ?? []);

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

const markChanged = (message = 'Draf diperbarui') => {
    statusMessage.value = message;
    debouncedRecordHistory();
};

const normalizePublicSlug = () => {
    publicSlug.value = publicSlug.value.replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'untitled-form';
    slugWarning.value = '';
};

const hasUnsavedChanges = ref(false);

/**
 * Edits not yet on the server are also kept in this browser, so a crash or a closed tab does not
 * lose them. The copy remembers the server version it started from: it is put back automatically
 * only while the server still has that version, so an old copy on this device can never replace
 * questions made later on another device or tab.
 */
type LocalDraft = {
    format: 2;
    title: string;
    description: string;
    questions: Question[];
    settings: typeof form.settings;
    isPublished: boolean;
    baseVersion: string | null;
    savedAt: number;
};

const localDraftKey = props.quizForm ? `quiz_draft_${props.quizForm.id}` : null;
// The server version the questions on screen descend from; sent with every save.
const baseVersion = ref<string | null>(props.quizForm?.version ?? null);
const saveConflict = ref('');
const recoverableDraft = ref<{ draft: Partial<LocalDraft>; questionCount: number; savedAt: number | null } | null>(null);
let editCount = 0;
let isSaveInFlight = false;
let queuedSave: { publishAfterSave: boolean; onComplete: (() => void)[]; onSaved: (() => void)[] } | null = null;

const writeLocalDraft = () => {
    if (!localDraftKey) {
        return;
    }
    const draft: LocalDraft = {
        format: 2,
        title: form.title,
        description: form.description,
        questions: form.questions,
        settings: form.settings,
        isPublished: isPublished.value,
        baseVersion: baseVersion.value,
        savedAt: Date.now(),
    };
    try {
        localStorage.setItem(localDraftKey, JSON.stringify(draft));
    } catch {
        // Storage full or blocked: the server copy is still saved.
    }
};

const clearLocalDraft = () => {
    if (!localDraftKey) {
        return;
    }
    try {
        localStorage.removeItem(localDraftKey);
    } catch {
        // Storage blocked.
    }
};

const saveDraft = (publishAfterSave = false, onComplete?: () => void, onSaved?: () => void) => {
    if (!props.quizForm || saveConflict.value) {
        onComplete?.();
        return;
    }

    // One save at a time, so each save carries the version returned by the previous one.
    if (isSaveInFlight) {
        queuedSave = {
            publishAfterSave: (queuedSave?.publishAfterSave ?? false) || publishAfterSave,
            onComplete: [...(queuedSave?.onComplete ?? []), ...(onComplete ? [onComplete] : [])],
            onSaved: [...(queuedSave?.onSaved ?? []), ...(onSaved ? [onSaved] : [])],
        };
        return;
    }

    normalizePublicSlug();
    if (!publicSlug.value) {
        publicSlug.value = 'untitled-form';
    }
    isSaving.value = true;
    isSaveInFlight = true;
    slugWarning.value = '';
    const savedEditCount = editCount;

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
            base_version: baseVersion.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                baseVersion.value = (page.props as { quizForm?: QuizFormPayload }).quizForm?.version ?? baseVersion.value;
                if (editCount === savedEditCount) {
                    hasUnsavedChanges.value = false;
                    clearLocalDraft();
                } else {
                    // Edits made while saving are still pending; keep them, now based on the new version.
                    writeLocalDraft();
                }
                publicUrl.value = `${appOrigin.value}/forms/${publicSlug.value}`;
                markChanged('Semua perubahan disimpan');
                showPublish.value = publishAfterSave;
                onSaved?.();
                onComplete?.();
            },
            onError: (errors) => {
                if (errors.conflict) {
                    saveConflict.value = errors.conflict;
                    markChanged('Perubahan belum disimpan');
                    onComplete?.();
                    return;
                }
                slugWarning.value = errors.slug ?? 'Formulir belum bisa disimpan. Periksa kembali data formulir.';
                markChanged('Gagal menyimpan perubahan');
                showPublish.value = true;
                onComplete?.();
            },
            onFinish: () => {
                isSaving.value = false;
                isSaveInFlight = false;
                const next = queuedSave;
                queuedSave = null;
                if (next) {
                    saveDraft(
                        next.publishAfterSave,
                        () => next.onComplete.forEach((callback) => callback()),
                        () => next.onSaved.forEach((callback) => callback()),
                    );
                }
            },
        },
    );
};

const saveDraftAndCopy = () => {
    saveDraft(false, undefined, () => {
        markChanged('Draf disimpan');
        copyShareUrl();
    });
};

const applyDraft = (draft: Partial<LocalDraft>) => {
    form.title = draft.title ?? form.title;
    form.description = draft.description ?? form.description;
    form.questions = Array.isArray(draft.questions) ? draft.questions : form.questions;
    form.settings = draft.settings ?? form.settings;
    if (draft.isPublished !== undefined) {
        isPublished.value = draft.isPublished;
    }
    form.questions.forEach((question: Question) => normalizeCorrectAnswer(question));
};

// A copy offered for recovery is parked under its own key, so autosaves cannot overwrite it.
const recoveryDraftKey = localDraftKey ? `${localDraftKey}_recovery` : null;

const clearRecoveryDraft = () => {
    recoverableDraft.value = null;
    if (!recoveryDraftKey) {
        return;
    }
    try {
        localStorage.removeItem(recoveryDraftKey);
    } catch {
        // Storage blocked.
    }
};

/**
 * Put back the copy kept on this device. It is saved over the server version like any edit.
 */
const restoreRecoverableDraft = () => {
    const draft = recoverableDraft.value?.draft;
    if (!draft) {
        return;
    }
    applyDraft(draft);
    clearRecoveryDraft();
    triggerToast('Salinan dari perangkat ini dipulihkan dan sedang disimpan');
};

const dismissRecoverableDraft = () => {
    clearRecoveryDraft();
};

const reloadLatestVersion = () => {
    window.location.reload();
};

const addQuestion = (type: QuestionType = 'Multiple choice') => {
    const question: Question = {
        id: nextQuestionId++,
        title: UNTITLED_QUESTION,
        description: '',
        type,
        options: noOptionQuestionTypes.includes(type) ? [] : scaleQuestionTypes.includes(type) ? ['1', '2', '3', '4', '5'] : ['Opsi 1'],
        rows: gridQuestionTypes.includes(type) ? ['Baris 1', 'Baris 2'] : [],
        columns: gridQuestionTypes.includes(type) ? ['Kolom 1', 'Kolom 2'] : [],
        answer: gridQuestionTypes.includes(type) ? {} : type === 'Checkboxes' ? [] : '',
        required: false,
        media: [],
        points: form.settings.defaultQuestionPoints ?? 1,
    };

    insertAfterActive(question);
    markChanged('Pertanyaan ditambahkan');
};

/*
 * Like in Google Forms, a new item comes right after the selected one (so it lands in the
 * section being worked on), and sections and "title and description" items are not questions.
 */
const isContentItem = (question: Question) => CONTENT_ITEM_TYPES.includes(question.type);

const insertAfterActive = (item: Question) => {
    const index = form.questions.findIndex((question: Question) => question.id === activeQuestionId.value);
    form.questions.splice(index >= 0 ? index + 1 : form.questions.length, 0, item);
    activeQuestionId.value = item.id;
    activeTab.value = 'questions';
};

const contentItem = (type: QuestionType, title: string): Question => ({
    id: nextQuestionId++,
    title,
    description: '',
    type,
    options: [],
    rows: [],
    columns: [],
    answer: '',
    required: false,
    media: [],
    points: 0,
});

/** "Bagian X dari Y" of each section: the form's own header is section 1, unless a section comes first. */
const sectionLabels = computed<Record<number, string>>(() => {
    const sections = form.questions.filter((question: Question) => question.type === 'Section');
    const startsWithSection = form.questions[0]?.type === 'Section';
    const total = sections.length + (startsWithSection ? 0 : 1);
    return Object.fromEntries(sections.map((section: Question, index: number) => [section.id, `Bagian ${index + (startsWithSection ? 1 : 2)} dari ${total}`]));
});
/** With sections, the form's own header is "Bagian 1 dari Y" (unless a section comes first). */
const headerSectionLabel = computed(() => {
    const sectionCount = form.questions.filter((question: Question) => question.type === 'Section').length;
    return sectionCount > 0 && form.questions[0]?.type !== 'Section' ? `Bagian 1 dari ${sectionCount + 1}` : null;
});
const answerableQuestions = computed(() => form.questions.filter((question: Question) => !isContentItem(question)));

/*
 * Going to another section, like in Google Forms: "Setelah bagian N" at the end of every section,
 * and "Buka bagian berdasarkan jawaban" on multiple-choice and drop-down questions (see
 * utils/section-flow, and App\Support\SectionFlow which applies it to the answers).
 */
const editorSections = computed(() => flowSections(form.questions));
const hasManySections = computed(() => editorSections.value.length > 1);
/** The key of the section each item is in, by the item's place in the form. */
const sectionKeyAt = computed<number[]>(() => {
    let key = FIRST_SECTION;
    return form.questions.map((item: Question) => (item.type === 'Section' ? (key = Number(item.id)) : key));
});
const sectionKeyOfItem = (itemId: number): number => sectionKeyAt.value[form.questions.findIndex((item: Question) => item.id === itemId)] ?? FIRST_SECTION;
const sectionNumberOf = (key: number): number => editorSections.value.findIndex((section) => section.key === key) + 1;
const sectionTitleOf = (key: number): string => {
    const section = editorSections.value.find((candidate) => candidate.key === key);
    const title = stripRichFormatting(section?.header ? section.header.title : form.title).trim();
    return title.length > 40 ? `${title.slice(0, 40)}…` : title || 'Tanpa judul';
};
/** Where a section, or an answer in it, can send the student. */
const targetChoices = (fromKey: number): { value: SectionTarget; label: string }[] => {
    const isLast = editorSections.value[editorSections.value.length - 1]?.key === fromKey;
    return [
        { value: NEXT_SECTION, label: isLast ? 'Kirim formulir (bagian terakhir)' : 'Lanjutkan ke bagian berikutnya' },
        ...editorSections.value
            .filter((section) => section.key !== fromKey)
            .map((section) => ({ value: section.key, label: `Buka bagian ${sectionNumberOf(section.key)} (${sectionTitleOf(section.key)})` })),
        ...(isLast ? [] : [{ value: SUBMIT_FORM as SectionTarget, label: 'Kirim formulir' }]),
    ];
};
const sectionGoToValue = (key: number): SectionTarget | undefined =>
    key === FIRST_SECTION ? form.settings.firstSectionGoTo : form.questions.find((item: Question) => item.type === 'Section' && Number(item.id) === key)?.goTo;
const setSectionGoTo = (key: number, target: SectionTarget) => {
    if (key === FIRST_SECTION) {
        form.settings.firstSectionGoTo = target;
    } else {
        const header = form.questions.find((item: Question) => item.type === 'Section' && Number(item.id) === key);
        if (header) {
            header.goTo = target;
        }
    }
    markChanged('Alur bagian diperbarui');
};
const canSendByAnswer = (question: Question): boolean => hasManySections.value && BRANCHING_TYPES.includes(question.type);
const toggleSendByAnswer = (question: Question) => {
    question.branching = !question.branching;
    question.optionGoTo = question.options.map((_, index) => question.optionGoTo?.[index] ?? NEXT_SECTION);
    markChanged(question.branching ? 'Bagian dibuka sesuai jawaban' : 'Bagian tidak lagi sesuai jawaban');
};
const setOptionGoTo = (question: Question, optionIndex: number, target: SectionTarget) => {
    const targets = question.options.map((_, index) => question.optionGoTo?.[index] ?? NEXT_SECTION);
    targets[optionIndex] = target;
    question.optionGoTo = targets;
    markChanged('Bagian tujuan diperbarui');
};

const insertTextIntoQuestion = (question: any, snippet: string) => {
    if (!question.title || isPlaceholderTitle(question.title)) {
        question.title = snippet;
    } else {
        question.title += ` ${snippet}`;
    }
    markChanged('Rumus atau teks Arab disisipkan');
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
        points: question.points ?? 1,
    };

    const index = form.questions.findIndex((item) => item.id === question.id);
    form.questions.splice(index + 1, 0, copy);
    activeQuestionId.value = copy.id;
    markChanged('Pertanyaan diduplikat');
};

const deleteQuestion = (question: Question) => {
    if (form.questions.length === 1 && !isContentItem(question)) {
        question.title = UNTITLED_QUESTION;
        question.description = '';
        question.options = ['Opsi 1'];
        question.answer = '';
        question.type = 'Multiple choice';
        question.required = false;
        question.media = [];
        markChanged('Pertanyaan dikosongkan');
        return;
    }

    const index = form.questions.findIndex((item) => item.id === question.id);
    form.questions.splice(index, 1);
    if (form.questions.length === 0) {
        addQuestion();
        return;
    }
    activeQuestionId.value = form.questions[Math.max(0, index - 1)].id;
    markChanged('Pertanyaan dihapus');
};

const addOption = (question: Question, label = `Opsi ${question.options.length + 1}`) => {
    question.options.push(label);
    markChanged('Pilihan ditambahkan');
};

/** Enter in an option adds the next one and moves there (Shift+Enter is a line break). */
const handleOptionEnter = (event: KeyboardEvent, question: Question) => {
    const container = (event.target as HTMLElement).closest('[data-options-list]');
    addOption(question);
    nextTick(() => {
        const fields = container?.querySelectorAll<HTMLElement>('[data-rich-input]');
        const added = fields?.[fields.length - 1];
        if (!added) {
            return;
        }
        // Its default text ("Option 4") is selected, so typing replaces it.
        added.focus();
        const range = document.createRange();
        range.selectNodeContents(added);
        window.getSelection()?.removeAllRanges();
        window.getSelection()?.addRange(range);
    });
};

const removeOption = (question: Question, optionIndex: number) => {
    question.options.splice(optionIndex, 1);
    question.optionGoTo?.splice(optionIndex, 1);
    if (question.options.length === 0 && !noOptionQuestionTypes.includes(question.type)) {
        question.options.push('Opsi 1');
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
    markChanged('Pilihan dihapus');
};

const isQuestionAnswered = (question: Question) => {
    if (question.type === 'Checkboxes') {
        return Array.isArray(question.answer) && question.answer.length > 0;
    }
    return question.answer !== '' && question.answer !== null && question.answer !== undefined;
};

const normalizeCorrectAnswer = (question: Question) => {
    // Sections and "title and description" items have no answer.
    if (isContentItem(question)) {
        return;
    }
    if (gridQuestionTypes.includes(question.type)) {
        if (typeof question.answer !== 'object' || question.answer === null) {
            question.answer = {};
        }
        return;
    }

    if (!optionQuestionTypes.includes(question.type)) {
        // Text keys (short answer, essay rubric, scale value, date, time) are kept, so an imported
        // "Bandung | Kota Bandung" survives opening the editor. Changing the type clears the key.
        if (typeof question.answer === 'number') {
            question.answer = String(question.answer);
        } else if (typeof question.answer !== 'string') {
            question.answer = '';
        }
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
            question.options = ['Opsi 1'];
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
    markChanged('Jenis pertanyaan diubah');
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
    markChanged('Kunci jawaban kisi diperbarui');
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
    markChanged('Jawaban benar diperbarui');
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

/** A new section: students see the items after it on a page of their own, with Berikutnya / Kembali. */
const addSection = () => {
    insertAfterActive(contentItem('Section', 'Bagian tanpa judul'));
    markChanged('Bagian ditambahkan');
};

/** A title with a description between questions: text only, nothing to answer. */
const addTitleDescription = () => {
    insertAfterActive(contentItem('Title and description', 'Judul'));
    markChanged('Judul dan deskripsi ditambahkan');
};

const addMediaToActiveQuestion = (type: 'image' | 'video') => {
    activeQuestion.value.media.push({
        type,
        url: '',
        sourceType: 'upload',
        isUploading: false,
        uploadError: '',
    });
    markChanged(type === 'image' ? 'Gambar ditambahkan ke pertanyaan' : 'Video ditambahkan ke pertanyaan');
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
        markChanged('Media berhasil diunggah');
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
    markChanged('Urutan pertanyaan diubah');
};

const handleDragStart = (question: Question, event: DragEvent) => {
    const target = event.target as HTMLElement;
    const isFromHandle = target.closest('[data-drag-handle]');
    if (!draggableQuestionId.value || !isFromHandle) {
        event.preventDefault();
        return;
    }
    draggedQuestionId.value = question.id;
    activeQuestionId.value = question.id;
    event.dataTransfer?.setData('text/plain', String(question.id));
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const handleDragEnd = () => {
    draggedQuestionId.value = null;
    draggableQuestionId.value = null;
};

const handleDrop = (question: Question, event: DragEvent) => {
    event.preventDefault();
    const fromId = Number(event.dataTransfer?.getData('text/plain') || draggedQuestionId.value);
    if (fromId) {
        moveQuestion(fromId, question.id);
    }
    draggedQuestionId.value = null;
    draggableQuestionId.value = null;
};

/*
 * Labels of the questions (kept in the question bank): subject, class, chapter and difficulty.
 */
const questionLabels = ref<Record<string, QuestionLabels>>({ ...((props.quizForm?.questionLabels as Record<string, QuestionLabels> | undefined) ?? {}) });
const labelSuggestions = ref<LabelSuggestions>(props.quizForm?.labelSuggestions ?? { subjects: [], grades: [], topics: [] });
const savingLabelsOf = ref<string | null>(null);
/* Analisis butir soal (Responses tab), and its difficulty applied to the labels. */
const itemAnalysis = computed<ItemAnalysis | null>(() => (props.quizForm?.responses as { analysis?: ItemAnalysis } | undefined)?.analysis ?? null);
const remedialOverview = computed<RemedialOverview | null>(() => (props.quizForm?.responses as { remedial?: RemedialOverview } | undefined)?.remedial ?? null);
const misconceptionMap = computed<MisconceptionMap | null>(
    () => (props.quizForm?.responses as { misconceptions?: MisconceptionMap } | undefined)?.misconceptions ?? null,
);
const followUp = computed(() => props.quizForm?.followUp ?? null);

/* Grading by hand: essays, files, and short answers whose key judged too strictly. */
const isGradingOpen = ref(false);
const closeGrading = (changed: boolean) => {
    isGradingOpen.value = false;
    // The gradebook, the Excel numbers and the add-on panels follow the new grades.
    if (changed) {
        router.reload({ only: ['quizForm'] });
    }
};
const isApplyingDifficulty = ref(false);
const applyDifficultyMessage = ref('');
const applyAnalysedDifficulty = async () => {
    const url = (props.quizForm?.responses as { applyDifficultyUrl?: string } | undefined)?.applyDifficultyUrl;
    if (!url || isApplyingDifficulty.value) {
        return;
    }
    isApplyingDifficulty.value = true;
    try {
        const response = await axios.post(url);
        questionLabels.value = { ...questionLabels.value, ...(response.data.labels ?? {}) };
        applyDifficultyMessage.value = `Label tingkat kesulitan ${response.data.labelled} soal diisi dari hasil analisis (Sukar menjadi "Sulit").`;
    } catch {
        applyDifficultyMessage.value = 'Label gagal diterapkan. Coba lagi.';
    } finally {
        isApplyingDifficulty.value = false;
    }
};

const questionDifficulties = computed<Record<string, string | null | undefined>>(() =>
    Object.fromEntries(Object.entries(questionLabels.value).map(([id, labels]) => [id, labels?.difficulty])),
);
const labelErrors = ref<Record<string, string>>({});

const saveQuestionLabels = async (question: Question, labels: QuestionLabels) => {
    if (!props.quizForm?.labelsUrl) {
        return;
    }
    const key = String(question.id);
    savingLabelsOf.value = key;
    labelErrors.value = { ...labelErrors.value, [key]: '' };
    try {
        // The question must be in the bank (saved) first: flush a pending autosave.
        if (hasUnsavedChanges.value) {
            await new Promise<void>((resolve) => saveDraft(false, resolve));
        }
        const response = await axios.patch(props.quizForm.labelsUrl.replace('__question__', encodeURIComponent(key)), labels);
        questionLabels.value = { ...questionLabels.value, [key]: response.data.labels };
        statusMessage.value = 'Label soal disimpan';
    } catch (error: any) {
        labelErrors.value = { ...labelErrors.value, [key]: error.response?.data?.message || 'Label gagal disimpan. Coba lagi.' };
    } finally {
        savingLabelsOf.value = null;
    }
};

/** Questions chosen in the "Bank Soal" tab: copies of earlier questions, added at the end. */
const addQuestionsFromBank = (bankQuestions: Record<string, any>[]) => {
    bankQuestions.forEach((bankQuestion) => {
        const copy = JSON.parse(JSON.stringify(bankQuestion));
        const question: Question = {
            ...copy,
            id: nextQuestionId++,
            title: copy.title || UNTITLED_QUESTION,
            description: copy.description || '',
            type: copy.type || 'Multiple choice',
            options: Array.isArray(copy.options) ? copy.options : [],
            rows: Array.isArray(copy.rows) ? copy.rows : [],
            columns: Array.isArray(copy.columns) ? copy.columns : [],
            answer: copy.answer ?? '',
            required: !!copy.required,
            media: Array.isArray(copy.media) ? copy.media : [],
            points: copy.points ?? 1,
        };
        form.questions.push(question);
        activeQuestionId.value = question.id;
    });

    activeTab.value = 'questions';
    isImportModalOpen.value = false;
    markChanged(`${bankQuestions.length} soal dari bank soal ditambahkan`);
};

const downloadImportTemplate = () => {
    const link = document.createElement('a');
    link.href = route('questions.import.template');
    link.download = 'template_import_soal_falcon_forms.docx';
    link.click();
    markChanged('Templat DOCX diunduh');
};

const handleImportDocxFile = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    isImportingFile.value = true;
    importError.value = '';
    importWarnings.value = [];
    importSummary.value = '';

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
                    title: q.title || UNTITLED_QUESTION,
                    description: q.description || '',
                    type: q.type || 'Multiple choice',
                    options: Array.isArray(q.options) ? [...q.options] : [],
                    rows: Array.isArray(q.rows) ? [...q.rows] : [],
                    columns: Array.isArray(q.columns) ? [...q.columns] : [],
                    answer: answerVal,
                    required: !!q.required,
                    media: Array.isArray(q.media) ? [...q.media] : [],
                    points: q.points ?? 1,
                };
                form.questions.push(question);
                activeQuestionId.value = question.id;
            });

            activeTab.value = 'questions';
            markChanged(`${importedQuestions.length} pertanyaan berhasil diimpor`);

            // Keep the dialog open when some questions were not read exactly as written (unknown type, key, ...).
            const warnings: string[] = Array.isArray(response.data.warnings) ? response.data.warnings : [];
            if (warnings.length > 0) {
                importWarnings.value = warnings;
                importSummary.value = `${importedQuestions.length} soal berhasil diimpor. Periksa catatan berikut:`;
            } else {
                isImportModalOpen.value = false;
            }
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
    importWarnings.value = [];
    importSummary.value = '';

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
                    title: q.title || UNTITLED_QUESTION,
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
                    points: q.points ?? 1,
                };
                form.questions.push(question);
                activeQuestionId.value = question.id;
            });

            activeTab.value = 'questions';
            markChanged(`${importedQuestions.length} soal ExamView berhasil diimpor`);

            // Keep the dialog open when some questions need attention (missing image, answer key, ...).
            const warnings: string[] = Array.isArray(response.data.warnings) ? response.data.warnings : [];
            if (warnings.length > 0) {
                importWarnings.value = warnings;
                importSummary.value = `${importedQuestions.length} soal berhasil diimpor. Periksa catatan berikut:`;
            } else {
                isImportModalOpen.value = false;
            }
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
        markChanged('Tautan disalin');
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
        statusMessage.value = 'Perubahan dibatalkan';
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
        statusMessage.value = 'Perubahan diulang';
        triggerToast('Redo berhasil dilakukan');
    }
};

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(() => historyIndex.value < history.value.length - 1);

const setStatus = (status: boolean) => {
    isPublished.value = status;
    showStatusMenu.value = false;
    hasUnsavedChanges.value = true;
    editCount++;
    writeLocalDraft();
    // Save to server immediately
    saveDraft(false);
    triggerToast(status ? 'Formulir diterbitkan' : 'Formulir dijadikan draf');
};

/*
 * Identity of guests on a public quiz: name, class and email asked on the confirmation screen.
 * Publishing a public quiz asks the teacher which of them to show; Pengaturan can change it later.
 */
const identityChoiceFromSettings = (): IdentityChoice => ({
    name: form.settings.respondentFields.includes('name'),
    class: form.settings.respondentFields.includes('class'),
    email: form.settings.emailCollectionMode !== 'none',
});

const applyIdentityChoice = (choice: IdentityChoice) => {
    form.settings.respondentFields = (['name', 'class'] as const).filter((field) => choice[field]);
    if (!choice.email) {
        form.settings.emailCollectionMode = 'none';
    } else if (form.settings.emailCollectionMode === 'none') {
        form.settings.emailCollectionMode = 'responder';
    }
    markSettingsChanged();
};

const identityChecklist = computed<IdentityChoice>({
    get: identityChoiceFromSettings,
    set: applyIdentityChoice,
});

const showPublishIdentity = ref(false);
const publishIdentityChoice = ref<IdentityChoice>({ name: true, class: false, email: false });

const requestPublish = () => {
    showStatusMenu.value = false;
    // Accounts of the chosen cohorts identify themselves: nothing to ask.
    if (isCohortRestricted.value) {
        setStatus(true);
        return;
    }
    const current = identityChoiceFromSettings();
    // The first time, the name is suggested; afterwards the teacher's last choice is shown.
    const hasChosenBefore = props.quizForm?.settings?.respondentFields !== undefined || form.settings.respondentFields.length > 0;
    publishIdentityChoice.value = hasChosenBefore ? current : { ...current, name: true };
    showPublishIdentity.value = true;
};

const confirmPublish = () => {
    applyIdentityChoice(publishIdentityChoice.value);
    showPublishIdentity.value = false;
    setStatus(true);
};

const comparableContent = (content: Partial<LocalDraft>) =>
    JSON.stringify([content.title, content.description, content.questions, content.settings, content.isPublished]);

const readStoredDraft = (key: string | null): Partial<LocalDraft> | null => {
    if (!key) {
        return null;
    }
    try {
        const draft = JSON.parse(localStorage.getItem(key) ?? 'null');

        return draft && Array.isArray(draft.questions) ? draft : null;
    } catch {
        return null;
    }
};

/**
 * Edits left unsaved on this device (crash, closed tab) come back automatically while the server
 * still has the version they were made on. Any other copy is only offered, never applied by itself.
 */
const checkLocalDraft = () => {
    if (!localDraftKey || !recoveryDraftKey) {
        return;
    }

    const serverContent = comparableContent({
        title: form.title,
        description: form.description,
        questions: form.questions,
        settings: form.settings,
        isPublished: isPublished.value,
    });
    const draft = readStoredDraft(localDraftKey);
    clearLocalDraft();

    if (draft && comparableContent(draft) !== serverContent) {
        if (draft.format === 2 && draft.baseVersion && draft.baseVersion === baseVersion.value) {
            applyDraft(draft);
            triggerToast('Perubahan yang belum tersimpan dipulihkan');

            return;
        }

        try {
            localStorage.setItem(recoveryDraftKey, JSON.stringify(draft));
        } catch {
            // Storage blocked: the copy is still offered on this page.
        }
    }

    const recovery = readStoredDraft(recoveryDraftKey);
    if (!recovery || comparableContent(recovery) === serverContent) {
        clearRecoveryDraft();

        return;
    }
    recoverableDraft.value = { draft: recovery, questionCount: recovery.questions?.length ?? 0, savedAt: recovery.savedAt ?? null };
};

onMounted(() => {
    // Convert legacy correct answers to indices for all loaded questions
    form.questions.forEach((q: Question) => normalizeCorrectAnswer(q));
    checkLocalDraft();

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

/**
 * Leave the editor only after pending changes are saved to the server.
 */
const leaveEditor = (leave: () => void) => {
    if (props.quizForm && (hasUnsavedChanges.value || isSaving.value)) {
        statusMessage.value = 'Menyimpan sebelum keluar...';
        saveDraft(false, leave);
    } else {
        leave();
    }
};

const navigateBackToDashboard = () => leaveEditor(() => router.visit(route('dashboard')));

// Watch form changes: keep unsaved edits in this browser until the server has them.
watch(
    () => [form.title, form.description, form.questions, form.settings],
    () => {
        hasUnsavedChanges.value = true;
        editCount++;
        statusMessage.value = saveConflict.value ? 'Perubahan belum disimpan' : 'Menyimpan perubahan...';
        writeLocalDraft();

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
            activeDesign ? '' : (form.settings.backgroundColorClass ?? 'bg-[#f0efff]'),
            activeDesign?.backgroundImage ? '' : (form.settings.backgroundPatternClass ?? 'pattern-none'),
        ]"
        :style="activeDesign ? pageBackgroundStyle(activeDesign) : undefined"
    >
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-5">
                <button
                    type="button"
                    class="h-9 w-9 shrink-0 rounded-[22%] transition hover:-translate-y-0.5 hover:shadow-[0_4px_0_#311a72]"
                    aria-label="Kembali ke beranda"
                    title="Kembali ke Beranda"
                    @click="navigateBackToDashboard"
                >
                    <img :src="BRAND_ICON" alt="" class="h-9 w-9" width="36" height="36" />
                </button>

                <div class="flex min-w-0 items-center gap-3">
                    <input
                        v-model="form.title"
                        type="text"
                        class="min-w-0 max-w-[170px] border-0 bg-transparent p-0 text-lg font-medium outline-none transition focus:border-b focus:border-indigo-500 sm:max-w-md sm:text-xl"
                        @input="markChanged('Judul diperbarui')"
                        @blur="handleTitleBlur"
                        @change="handleTitleBlur"
                        @keydown.enter.prevent="handleTitleBlur"
                    />
                    <Star class="hidden h-5 w-5 text-slate-500 sm:block" />
                </div>

                <div class="ml-auto flex items-center gap-2 text-slate-600 lg:gap-4">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <button
                                type="button"
                                class="hidden rounded-full p-2 transition hover:bg-slate-100 data-[state=open]:bg-indigo-50 data-[state=open]:text-indigo-700 lg:block"
                                aria-label="Add-on"
                                title="Add-on"
                            >
                                <Puzzle class="h-5 w-5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="z-50 mt-1.5 w-80 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="flex items-center justify-between px-2 pb-1 pt-1.5">
                                <p class="text-sm font-bold text-slate-900">Add-on</p>
                                <DropdownMenuItem
                                    class="cursor-pointer rounded-lg px-2 py-1 text-xs font-bold text-indigo-600 focus:bg-indigo-50 focus:text-indigo-700"
                                    @select="isAddonGalleryOpen = true"
                                >
                                    Jelajahi add-on
                                </DropdownMenuItem>
                            </div>
                            <AddonExamSwitches v-if="examAddons.length" :addons="examAddons" :is-on="isAddonOnForExam" @toggle="setAddonForExam" />
                            <div v-else class="flex items-start gap-3 px-2 py-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <Sparkles class="h-5 w-5" />
                                </div>
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Belum ada add-on terpasang. Tambahkan kemampuan seperti Analisis Butir Soal lewat <b>Jelajahi add-on</b>.
                                </p>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <button
                        type="button"
                        class="hidden rounded-full p-2 transition hover:bg-slate-100 lg:block"
                        aria-label="Tema"
                        :title="isDesignerOn ? 'Desainer Formulir' : 'Tema'"
                        @click="openThemeEditor"
                    >
                        <Palette class="h-5 w-5" />
                    </button>
                    <button type="button" class="rounded-full p-2 transition hover:bg-slate-100" aria-label="Pratinjau" @click="openPreview">
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
                        aria-label="Salin dan ubah tautan"
                        @click="showPublish = true"
                    >
                        <Link2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="relative hidden rounded-full p-2 transition hover:bg-slate-100 md:block"
                        aria-label="Undang kolaborator"
                        title="Kolaborasi Formulir Antar Guru"
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
                            <span>{{ isPublished ? 'Terbit' : 'Draf' }}</span>
                            <ChevronDown class="h-4 w-4" />
                        </button>
                        <div
                            v-if="showStatusMenu"
                            class="absolute right-0 top-11 z-50 w-40 rounded-2xl border border-slate-200 bg-white p-2 text-sm font-medium shadow-lg"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
                                @click="requestPublish"
                            >
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                <span>Terbitkan</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
                                @click="setStatus(false)"
                            >
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                <span>Jadikan draf</span>
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
                                Salin tautan publik
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
                                Kolaborasi Formulir
                            </button>
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50" @click="openPreview">
                                Buka pratinjau
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left hover:bg-slate-50 lg:hidden"
                                @click="
                                    isAddonGalleryOpen = true;
                                    showMoreMenu = false;
                                "
                            >
                                <Puzzle class="h-4 w-4 text-indigo-600" />
                                Add-on
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50"
                                @click="
                                    openThemeEditor();
                                    showMoreMenu = false;
                                "
                            >
                                Change theme
                            </button>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <AccountMenu :leave="leaveEditor" />
                    </div>
                </div>
            </div>

            <nav class="grid h-10 grid-cols-4 items-end bg-white px-1 sm:flex sm:justify-center sm:gap-8 sm:px-0">
                <button
                    v-for="tab in ['questions', 'responses', 'settings', 'keamanan']"
                    :key="tab"
                    type="button"
                    :class="[
                        'whitespace-nowrap px-1 pb-2.5 text-[13px] font-bold capitalize transition sm:px-3 sm:text-sm',
                        activeTab === tab ? 'border-b-4 border-indigo-600 text-indigo-700' : 'text-slate-700 hover:text-indigo-600',
                    ]"
                    @click="activeTab = tab as 'questions' | 'responses' | 'settings' | 'keamanan'"
                >
                    {{ TAB_LABELS[tab] }}<span v-if="tab === 'keamanan'" class="hidden sm:inline">&nbsp;&amp; kunci</span
                    ><span
                        v-if="tab === 'responses' && (isPublished || examProgress.submitted > 0)"
                        class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white"
                        :title="`${examProgress.submitted} siswa sudah submit`"
                        :aria-label="`${examProgress.submitted} siswa sudah submit`"
                        >{{ examProgress.submitted }}</span
                    ><span
                        v-if="tab === 'keamanan' && lockedStudentCount > 0"
                        class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white"
                        :title="`${lockedStudentCount} siswa terkunci`"
                        >{{ lockedStudentCount }}</span
                    >
                </button>
            </nav>
        </header>

        <section class="mx-auto grid max-w-[980px] grid-cols-1 gap-4 px-4 pb-28 pt-5 sm:px-5 lg:grid-cols-[minmax(0,1fr)_60px] lg:pb-5">
            <input ref="docxFileInput" type="file" accept=".docx" class="hidden" @change="handleImportDocxFile" />
            <input ref="examviewFileInput" type="file" accept=".zip" class="hidden" @change="handleImportExamViewFile" />
            <div class="space-y-4">
                <p class="text-sm font-medium text-slate-500" :style="activeDesignVariables ? { color: activeDesignVariables['--af-on-bg-muted'] } : undefined">{{ statusMessage }}</p>

                <div
                    v-if="saveConflict"
                    role="alert"
                    class="flex flex-col gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 sm:flex-row sm:items-center"
                >
                    <AlertTriangle class="h-5 w-5 shrink-0 text-rose-600" />
                    <p class="flex-1 font-medium">{{ saveConflict }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-rose-700"
                        @click="reloadLatestVersion"
                    >
                        Muat Versi Terbaru
                    </button>
                </div>

                <div
                    v-if="recoverableDraft"
                    role="alert"
                    class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center"
                >
                    <History class="h-5 w-5 shrink-0 text-amber-600" />
                    <p class="flex-1">
                        <span class="block font-bold">Perangkat ini menyimpan salinan lain dari ujian ini.</span>
                        Salinan: {{ recoverableDraft.questionCount }} soal<span v-if="recoverableDraft.questionCount === form.questions.length">
                            dengan isi berbeda</span
                        ><span v-if="recoverableDraft.savedAt"> (disimpan {{ new Date(recoverableDraft.savedAt).toLocaleString('id-ID') }})</span>.
                        Versi tersimpan yang tampil sekarang: {{ form.questions.length }} soal. Pulihkan salinan hanya jika soal di versi tersimpan
                        hilang atau tidak lengkap.
                    </p>
                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-amber-300 bg-white px-3 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-100"
                            @click="dismissRecoverableDraft"
                        >
                            Abaikan
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700"
                            @click="restoreRecoverableDraft"
                        >
                            Pulihkan Salinan
                        </button>
                    </div>
                </div>

                <div v-if="activeTab === 'questions'" :class="['space-y-4', activeDesign ? 'af-designed' : '']" :style="activeDesignVariables">
                    <img v-if="activeDesign?.headerImage" :src="activeDesign.headerImage" alt="" class="af-header-image" data-testid="design-header-image" />
                    <div
                        v-if="headerSectionLabel"
                        :class="['-mb-4 w-fit rounded-t-xl px-4 py-1.5 text-xs font-bold text-white', themeClass]"
                    >
                        {{ headerSectionLabel }}
                    </div>
                    <!-- A remedial or enrichment exam: what it follows up, and the students who may take it -->
                    <details
                        v-if="followUp"
                        :class="[
                            'rounded-2xl border px-4 py-3 text-sm',
                            followUp.kind === 'remedial' ? 'border-rose-200 bg-rose-50/60 text-rose-900' : 'border-emerald-200 bg-emerald-50/60 text-emerald-900',
                        ]"
                        data-testid="follow-up-banner"
                    >
                        <summary class="cursor-pointer list-none">
                            <span class="font-extrabold">{{ followUp.kind === 'remedial' ? 'Ujian remedial' : 'Ujian pengayaan' }}</span>
                            <template v-if="followUp.of">
                                dari
                                <Link v-if="followUp.of.editUrl" :href="followUp.of.editUrl" class="font-bold underline" @click.stop>{{ followUp.of.title }}</Link>
                                <span v-else class="font-bold">{{ followUp.of.title }}</span>
                            </template>
                            ·
                            {{ followUp.participants.length ? `khusus ${followUp.participants.length} siswa terpilih (lihat)` : 'terbuka bagi siapa pun yang punya tautannya' }}
                        </summary>
                        <p v-if="followUp.participants.length" class="mt-2 text-xs leading-relaxed">
                            {{ followUp.participants.map((participant) => (participant.kelas ? `${participant.name} (${participant.kelas})` : participant.name)).join(', ') }}
                        </p>
                    </details>
                    <section class="af-card af-header overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm transition duration-200 hover:shadow-md">
                        <div :class="['af-header-bar h-3 transition-all duration-300', themeClass]"></div>
                        <div class="grid gap-3 p-5 sm:p-6">
                            <div v-if="activeDesign?.logo" class="flex" :style="{ justifyContent: logoJustify(activeDesign) }">
                                <img :src="activeDesign.logo" alt="Logo" class="af-logo" />
                            </div>
                            <input
                                v-model="form.title"
                                type="text"
                                aria-label="Judul ujian"
                                :class="[
                                    'w-full border-0 border-b border-transparent bg-transparent p-0 text-2xl font-normal leading-tight outline-none transition focus:border-indigo-500 sm:text-3xl',
                                    activeDesign ? 'af-title' : '',
                                ]"
                                :style="activeDesign ? undefined : { fontFamily: form.settings.questionFont ?? 'inherit' }"
                                @input="markChanged('Judul diperbarui')"
                                @blur="handleTitleBlur"
                                @change="handleTitleBlur"
                                @keydown.enter.prevent="handleTitleBlur"
                            />
                            <RichTextInput
                                v-model="form.description"
                                :dir="isPredominantlyArabic(form.description) ? 'rtl' : 'auto'"
                                class="af-description w-full border-0 border-b border-transparent bg-transparent p-0 text-base text-slate-500 transition focus:border-indigo-500 sm:text-lg"
                                :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                placeholder="Deskripsi ujian"
                                @update:model-value="markChanged('Deskripsi diperbarui')"
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
                        <template v-for="(question, itemIndex) in form.questions" :key="question.id">
                        <!-- Like Google Forms: where the student goes at the end of the section before -->
                        <div
                            v-if="hasManySections && question.type === 'Section' && itemIndex > 0"
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 px-2 text-sm text-slate-600"
                            data-testid="after-section"
                        >
                            <CornerDownRight class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="af-on-page font-semibold">Setelah bagian {{ sectionNumberOf(sectionKeyAt[itemIndex - 1]) }}</span>
                            <SectionTargetSelect
                                :model-value="sectionGoToValue(sectionKeyAt[itemIndex - 1])"
                                :choices="targetChoices(sectionKeyAt[itemIndex - 1])"
                                :label="`Setelah bagian ${sectionNumberOf(sectionKeyAt[itemIndex - 1])}`"
                                @update:model-value="setSectionGoTo(sectionKeyAt[itemIndex - 1], $event)"
                            />
                        </div>
                        <section
                            :draggable="draggableQuestionId === question.id"
                            :class="[
                                'af-card relative overflow-visible rounded-2xl border border-slate-300 bg-white shadow-sm transition duration-200 hover:shadow-md',
                                isContentItem(question) ? 'af-section' : 'af-question',
                                draggedQuestionId === question.id ? 'scale-[0.99] opacity-60' : '',
                            ]"
                            @click="activeQuestionId = question.id"
                            @dragstart="handleDragStart(question, $event)"
                            @dragover.prevent
                            @drop="handleDrop(question, $event)"
                            @dragend="handleDragEnd"
                        >
                            <!-- Like Google Forms: the section label on top of its card -->
                            <div
                                v-if="question.type === 'Section'"
                                :class="['af-header-bar rounded-t-2xl px-4 py-1.5 text-xs font-bold text-white', themeClass]"
                            >
                                {{ sectionLabels[question.id] }}
                            </div>
                            <div
                                :class="[
                                    'grid grid-cols-1 gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_260px]',
                                    activeQuestionId === question.id ? 'border-l-4 border-blue-500' : 'border-l-4 border-transparent',
                                ]"
                            >
                                <!-- A section or a "title and description": a title and a description only -->
                                <template v-if="isContentItem(question)">
                                    <div class="col-span-full" data-testid="content-item">
                                        <div
                                            data-drag-handle
                                            class="mx-auto mb-2 flex w-12 cursor-grab justify-center gap-1.5 py-1 text-slate-300 select-none hover:text-slate-500 active:cursor-grabbing"
                                            title="Tahan dan geser untuk memindahkan"
                                            @mousedown="enableCardDrag(question.id)"
                                            @mouseenter="enableCardDrag(question.id)"
                                            @mouseleave="disableCardDrag"
                                            @mouseup="disableCardDrag"
                                        >
                                            <span v-for="dot in 6" :key="dot" class="h-1 w-1 rounded-full bg-current"></span>
                                        </div>
                                        <RichTextInput
                                            v-model="question.title"
                                            :multiline="false"
                                            :dir="isPredominantlyArabic(question.title) ? 'rtl' : 'auto'"
                                            :label="question.type === 'Section' ? 'Judul bagian' : 'Judul'"
                                            :placeholder="question.type === 'Section' ? 'Judul bagian' : 'Judul'"
                                            :class="[
                                                'w-full border-0 border-b border-transparent bg-transparent p-0 font-normal text-slate-950 transition focus:border-indigo-500',
                                                question.type === 'Section' ? 'text-2xl' : 'text-xl',
                                            ]"
                                            @update:model-value="markChanged(question.type === 'Section' ? 'Judul bagian diperbarui' : 'Judul diperbarui')"
                                        />
                                        <RichTextInput
                                            v-model="question.description"
                                            :dir="isPredominantlyArabic(question.description) ? 'rtl' : 'auto'"
                                            label="Deskripsi"
                                            placeholder="Deskripsi (opsional)"
                                            class="mt-3 w-full border-0 border-b border-transparent bg-transparent p-0 text-sm text-slate-600 transition focus:border-indigo-500"
                                            @update:model-value="markChanged('Deskripsi diperbarui')"
                                        />
                                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-slate-600">
                                            <span class="text-xs text-slate-500">
                                                {{
                                                    question.type === 'Section'
                                                        ? 'Soal di bawahnya tampil di halaman tersendiri bagi siswa.'
                                                        : 'Teks saja: tidak dijawab dan tidak dinilai.'
                                                }}
                                            </span>
                                            <span class="flex items-center gap-4">
                                                <button
                                                    type="button"
                                                    :aria-label="question.type === 'Section' ? 'Duplikat bagian' : 'Duplikat judul'"
                                                    class="transition hover:text-indigo-600"
                                                    @click.stop="duplicateQuestion(question)"
                                                >
                                                    <FileText class="h-5 w-5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-label="question.type === 'Section' ? 'Hapus bagian' : 'Hapus judul'"
                                                    class="transition hover:text-red-500"
                                                    @click.stop="deleteQuestion(question)"
                                                >
                                                    <Trash2 class="h-5 w-5" />
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="editingAnswerKeyQuestionId === question.id">
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
                                                    @input="markChanged('Poin diperbarui')"
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-4 p-6">
                                            <div class="text-sm font-bold text-slate-800">
                                                Pertanyaan: <RichContent :content="question.title || 'Tanpa Judul'" class="font-normal text-slate-600" />
                                            </div>

                                            <!-- Short Answer Kunci Jawaban -->
                                            <div v-if="question.type === 'Short answer'" class="space-y-2">
                                                <label class="text-xs font-bold text-slate-600">Tuliskan jawaban yang benar:</label>
                                                <p class="text-[11px] leading-relaxed text-slate-500">
                                                    Beberapa jawaban benar dipisah dengan <code class="font-mono">|</code> (contoh:
                                                    <code class="font-mono">Jakarta | DKI Jakarta</code>). Rentang angka ditulis
                                                    <code class="font-mono">9.5..10.5</code>.
                                                </p>
                                                <input
                                                    v-model="question.answer"
                                                    type="text"
                                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/30 px-3.5 text-sm outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100"
                                                    placeholder="Masukkan teks jawaban yang benar..."
                                                    @input="markChanged('Kunci jawaban diperbarui')"
                                                />
                                            </div>

                                            <!-- Paragraph Kunci Jawaban Info -->
                                            <div
                                                v-else-if="question.type === 'Paragraph'"
                                                class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-medium leading-relaxed text-slate-500"
                                            >
                                                💡 Pertanyaan bertipe <strong>Paragraf</strong> akan dinilai secara manual oleh Anda setelah responden
                                                mengirimkan jawaban. Tidak ada kunci jawaban otomatis.
                                                <span v-if="question.answer" class="mt-2 block whitespace-pre-line text-slate-700">
                                                    <strong>Contoh jawaban / rubrik:</strong> {{ question.answer }}
                                                </span>
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
                                                        <RichContent :content="option" />
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
                                                            markChanged('Kunci jawaban diperbarui');
                                                        "
                                                    >
                                                        <RichContent :content="option" />
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
                                                            markChanged('Kunci jawaban diperbarui');
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
                                                    @change="markChanged('Kunci jawaban diperbarui')"
                                                />
                                            </div>

                                            <!-- Time Kunci Jawaban -->
                                            <div v-else-if="question.type === 'Time'" class="space-y-2">
                                                <label class="text-xs font-bold text-slate-600">Pilih waktu yang benar:</label>
                                                <input
                                                    v-model="question.answer"
                                                    type="time"
                                                    class="h-10 rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none focus:border-indigo-600"
                                                    @change="markChanged('Kunci jawaban diperbarui')"
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
                                                                    <RichContent :content="col" />
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="(row, rIndex) in question.rows"
                                                                :key="`row-key-${rIndex}`"
                                                                class="border-slate-150 border-b transition-colors last:border-0 hover:bg-slate-50"
                                                            >
                                                                <td class="p-3 font-semibold text-slate-800"><RichContent :content="row" /></td>
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
                                            data-drag-handle
                                            class="mx-auto mb-3 flex w-12 cursor-grab justify-center gap-1.5 py-1 text-slate-300 select-none hover:text-slate-500 active:cursor-grabbing"
                                            title="Tahan dan geser untuk memindahkan urutan soal"
                                            @mousedown="enableCardDrag(question.id)"
                                            @mouseenter="enableCardDrag(question.id)"
                                            @mouseleave="disableCardDrag"
                                            @mouseup="disableCardDrag"
                                        >
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                            <span class="h-1 w-1 rounded-full bg-current"></span>
                                        </div>

                                        <!-- Math & Arabic Toolbar -->
                                        <MathArabicToolbar
                                            @insert="(snip) => insertTextIntoQuestion(question, snip)"
                                            @toggle-rtl="question.isRtl = !question.isRtl"
                                        />

                                        <RichTextInput
                                            v-model="question.title"
                                            :dir="question.isRtl ? 'rtl' : isPredominantlyArabic(question.title) ? 'rtl' : 'auto'"
                                            label="Pertanyaan"
                                            class="mb-3 w-full rounded-xl border-0 border-b border-slate-400 bg-slate-50 px-4 py-3 text-lg font-medium transition focus:border-indigo-600 sm:text-xl"
                                            :style="{ fontFamily: form.settings.questionFont ?? 'inherit' }"
                                            placeholder="Tulis pertanyaan, rumus matematika $...$, atau ketik bahasa Arab..."
                                            data-question-title
                                            @update:model-value="markChanged('Pertanyaan diperbarui')"
                                        />

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

                                        <RichTextInput
                                            v-model="question.description"
                                            :dir="isPredominantlyArabic(question.description) ? 'rtl' : 'auto'"
                                            label="Deskripsi soal"
                                            class="mb-4 w-full border-0 border-b border-transparent bg-transparent p-0 text-sm text-slate-500 transition focus:border-indigo-500"
                                            placeholder="Deskripsi (opsional)"
                                            :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                            @update:model-value="markChanged('Deskripsi diperbarui')"
                                        />

                                        <!-- Media Rendering and Editing -->
                                        <div v-if="question.media && question.media.length" class="mb-4 space-y-4">
                                            <div
                                                v-for="(media, mediaIndex) in question.media"
                                                :key="mediaIndex"
                                                class="relative rounded-2xl border border-slate-200 bg-slate-50 p-4 transition-all"
                                            >
                                                <div class="mb-2.5 flex items-center justify-between">
                                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                        {{ media.type === 'image' ? 'Gambar Soal' : 'Video Soal' }}
                                                    </span>
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            v-if="media.url"
                                                            type="button"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100"
                                                            title="Buka Layar Penuh & Zoom"
                                                            @click="openLightbox(media)"
                                                        >
                                                            <Maximize2 class="h-3.5 w-3.5" />
                                                            Layar Penuh
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="rounded-lg p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-500"
                                                            title="Hapus media"
                                                            @click.stop="
                                                                question.media.splice(mediaIndex, 1);
                                                                markChanged('Media dihapus');
                                                            "
                                                        >
                                                            <Trash2 class="h-4 w-4" />
                                                        </button>
                                                    </div>
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
                                                        {{ media.type === 'video' ? 'Tautan YouTube' : 'Tautan URL' }}
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
                                                        v-model="media.url"
                                                        type="text"
                                                        :placeholder="
                                                            media.type === 'image'
                                                                ? 'Masukkan URL tautan gambar (http://...)'
                                                                : 'Masukkan Tautan YouTube (https://youtube.com/...)'
                                                        "
                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-indigo-500"
                                                        @input="markChanged('URL media diperbarui')"
                                                    />
                                                </div>

                                                <!-- Preview, Resize & Fullscreen Zoom Area -->
                                                <div v-if="media.url" class="mt-3 space-y-2">
                                                    <!-- Size and Alignment Toolbar -->
                                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-2 text-xs">
                                                        <!-- Size presets -->
                                                        <div class="flex items-center gap-1">
                                                            <span class="mr-1 text-[11px] font-bold text-slate-400">Ukuran:</span>
                                                            <button
                                                                v-for="w in ['25%', '50%', '75%', '100%']"
                                                                :key="w"
                                                                type="button"
                                                                :class="[
                                                                    'rounded-lg px-2.5 py-1 text-xs font-bold transition',
                                                                    (media.width ?? '100%') === w
                                                                        ? 'bg-indigo-600 text-white shadow-xs'
                                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                                ]"
                                                                @click="setMediaWidth(media, w)"
                                                            >
                                                                {{ w === '100%' ? 'Maks (100%)' : w }}
                                                            </button>
                                                        </div>

                                                        <!-- Alignment presets -->
                                                        <div class="flex items-center gap-1">
                                                            <span class="mr-1 text-[11px] font-bold text-slate-400">Posisi:</span>
                                                            <button
                                                                type="button"
                                                                :class="[
                                                                    'rounded-lg p-1.5 transition',
                                                                    (media.align ?? 'center') === 'left' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                                ]"
                                                                title="Rata Kiri"
                                                                @click="setMediaAlign(media, 'left')"
                                                            >
                                                                <AlignLeft class="h-3.5 w-3.5" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                :class="[
                                                                    'rounded-lg p-1.5 transition',
                                                                    (media.align ?? 'center') === 'center' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                                ]"
                                                                title="Rata Tengah"
                                                                @click="setMediaAlign(media, 'center')"
                                                            >
                                                                <AlignCenter class="h-3.5 w-3.5" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                :class="[
                                                                    'rounded-lg p-1.5 transition',
                                                                    (media.align ?? 'center') === 'right' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                                ]"
                                                                title="Rata Kanan"
                                                                @click="setMediaAlign(media, 'right')"
                                                            >
                                                                <AlignRight class="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Resizable Media Container -->
                                                    <div
                                                        :class="[
                                                            'flex w-full',
                                                            media.align === 'left' ? 'justify-start' : media.align === 'right' ? 'justify-end' : 'justify-center'
                                                        ]"
                                                    >
                                                        <div
                                                            class="group relative max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:border-indigo-400 hover:shadow-md cursor-pointer"
                                                            :style="{ width: media.width ?? '100%' }"
                                                            @click="openLightbox(media)"
                                                        >
                                                            <!-- Floating Hover Badge for Fullscreen Zoom -->
                                                            <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-slate-950/25 opacity-0 transition group-hover:opacity-100">
                                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900/85 px-3.5 py-1.5 text-xs font-bold text-white shadow-lg backdrop-blur-xs">
                                                                    <ZoomIn class="h-4 w-4" />
                                                                    Klik untuk layar penuh & zoom
                                                                </span>
                                                            </div>

                                                            <img
                                                                v-if="media.type === 'image'"
                                                                :src="media.url"
                                                                class="w-full object-contain p-2 transition"
                                                            />
                                                            <iframe
                                                                v-else-if="media.type === 'video' && getYoutubeEmbedUrl(media.url)"
                                                                :src="getYoutubeEmbedUrl(media.url)"
                                                                class="aspect-video w-full pointer-events-auto"
                                                                frameborder="0"
                                                                allowfullscreen
                                                            ></iframe>
                                                            <video
                                                                v-else-if="media.type === 'video'"
                                                                :src="media.url"
                                                                controls
                                                                class="w-full bg-slate-950 pointer-events-auto"
                                                            ></video>
                                                        </div>
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
                                            <p class="text-sm text-slate-600">
                                                Siswa mengunggah foto atau berkas (PDF, Word, Excel, PowerPoint), paling besar
                                                {{ form.settings.maxUploadSize ?? 20 }} MB per berkas. Jawabannya dinilai guru di tab Jawaban.
                                            </p>
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
                                                        markChanged('Skala ditambah');
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
                                                        markChanged('Skala dikurangi');
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
                                                        markChanged('Rating ditambah');
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
                                                        markChanged('Rating dikurangi');
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
                                                        class="flex items-start gap-2 pt-1"
                                                    >
                                                        <span class="mt-0.5 text-xs font-bold text-slate-400">#{{ rIndex + 1 }}</span>
                                                        <RichTextInput
                                                            v-model="question.rows[rIndex]"
                                                            :dir="isPredominantlyArabic(question.rows[rIndex]) ? 'rtl' : 'auto'"
                                                            :label="`Baris ${rIndex + 1}`"
                                                            class="min-w-0 flex-1 border-b border-slate-200 bg-transparent py-0.5 text-xs focus:border-indigo-500"
                                                            @update:model-value="markChanged('Label baris diperbarui')"
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
                                                        class="flex items-start gap-2 pt-1"
                                                    >
                                                        <span class="mt-0.5 text-xs font-bold text-slate-400">#{{ cIndex + 1 }}</span>
                                                        <RichTextInput
                                                            v-model="question.columns[cIndex]"
                                                            :dir="isPredominantlyArabic(question.columns[cIndex]) ? 'rtl' : 'auto'"
                                                            :label="`Kolom ${cIndex + 1}`"
                                                            class="min-w-0 flex-1 border-b border-slate-200 bg-transparent py-0.5 text-xs focus:border-indigo-500"
                                                            @update:model-value="markChanged('Label kolom diperbarui')"
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
                                                    - {{ stripRichFormatting(question.rows?.[Number(rowIdx)]) }}:
                                                    <span class="font-bold">
                                                        {{
                                                            Array.isArray(colIdx)
                                                                ? colIdx.map((c) => stripRichFormatting(question.columns?.[c])).join(', ')
                                                                : stripRichFormatting(question.columns?.[colIdx])
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="space-y-5" data-options-list>
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
                                                class="flex items-start gap-3 pt-1 text-base transition hover:translate-x-1 sm:text-lg"
                                            >
                                                <!-- Visual icon only for checkboxes/radio in edit mode -->
                                                <span
                                                    :class="[
                                                        'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center border-2 transition',
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
                                                <span v-if="question.type === 'Drop-down'" class="mt-0.5 text-xs font-bold text-slate-400">
                                                    #{{ optionIndex + 1 }}
                                                </span>

                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <RichTextInput
                                                        v-model="question.options[optionIndex]"
                                                        :multiline="question.type !== 'Drop-down'"
                                                        enter="emit"
                                                        :dir="isPredominantlyArabic(question.options[optionIndex]) ? 'rtl' : 'auto'"
                                                        :label="`Pilihan ${optionIndex + 1}`"
                                                        class="w-full border-0 border-b border-transparent bg-transparent p-0 transition focus:border-indigo-500"
                                                        :style="{ fontFamily: form.settings.answerFont ?? 'inherit' }"
                                                        @update:model-value="markChanged('Pilihan diperbarui')"
                                                        @enter="handleOptionEnter($event, question)"
                                                    />
                                                    <div
                                                        v-if="hasMathOrArabic(question.options[optionIndex])"
                                                        class="inline-flex items-center gap-1 self-start rounded border border-indigo-100 bg-indigo-50/70 px-2 py-0.5 text-xs text-slate-800"
                                                    >
                                                        <span class="text-[10px] font-bold text-indigo-500">Pratinjau:</span>
                                                        <RichContent :content="question.options[optionIndex]" />
                                                    </div>
                                                    <!-- "Buka bagian berdasarkan jawaban": where this option sends the student -->
                                                    <div
                                                        v-if="question.branching && canSendByAnswer(question)"
                                                        class="flex min-w-0 items-center gap-1.5"
                                                        data-testid="option-go-to"
                                                    >
                                                        <CornerDownRight class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                                        <SectionTargetSelect
                                                            :model-value="question.optionGoTo?.[optionIndex]"
                                                            :choices="targetChoices(sectionKeyOfItem(question.id))"
                                                            :label="`Bagian tujuan bila memilih ${stripRichFormatting(option) || `pilihan ${optionIndex + 1}`}`"
                                                            @update:model-value="setOptionGoTo(question, optionIndex, $event)"
                                                        />
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="mt-0.5 rounded-full px-2 text-slate-400 transition hover:bg-slate-100 hover:text-red-500"
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
                                                                    return stripRichFormatting(question.options[asNum]);
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
                                                    {{ stripRichFormatting(question.options[Number(question.answer)] || String(question.answer)) }}
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
                                                    Tambah pilihan
                                                </button>
                                                <span
                                                    v-if="!question.options.some((option) => OTHER_OPTIONS.includes(option)) && !isQuestionAnswered(question)"
                                                    class="text-slate-900"
                                                    >atau</span
                                                >
                                                <button
                                                    v-if="!question.options.some((option) => OTHER_OPTIONS.includes(option)) && !isQuestionAnswered(question)"
                                                    type="button"
                                                    class="text-blue-600 transition hover:text-blue-700"
                                                    @click.stop="addOption(question, 'Lainnya')"
                                                >
                                                    Tambah "Lainnya"
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
                                                    <span class="truncate">{{ questionTypeLabel(question.type) }}</span>
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
                                                        <span>{{ questionTypeLabel(type.value) }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </aside>

                                    <!-- Pembahasan: shown to students with their results (when shown in full) -->
                                    <div v-if="form.settings.isQuiz && (activeQuestionId === question.id || question.explanation)" class="lg:col-span-2">
                                        <p :id="`explanation-label-${question.id}`" class="text-xs font-bold uppercase tracking-wider text-slate-500">Pembahasan (opsional)</p>
                                        <RichTextInput
                                            :id="`explanation-${question.id}`"
                                            v-model="question.explanation"
                                            :dir="isPredominantlyArabic(question.explanation) ? 'rtl' : 'auto'"
                                            label="Pembahasan"
                                            placeholder="Jelaskan jawaban yang benar. Siswa melihatnya di halaman hasil bila hasil lengkap ditampilkan."
                                            class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm transition focus:border-indigo-500 focus:bg-white"
                                            @update:model-value="markChanged('Pembahasan diperbarui')"
                                        />
                                    </div>

                                    <div class="border-t border-slate-200 pt-4 lg:col-span-2">
                                        <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 text-slate-600 sm:gap-5">
                                            <QuestionLabelsEditor
                                                :labels="questionLabels[String(question.id)]"
                                                :suggestions="labelSuggestions"
                                                :question-id="question.id"
                                                :is-saving="savingLabelsOf === String(question.id)"
                                                :error="labelErrors[String(question.id)] ?? ''"
                                                @save="saveQuestionLabels(question, $event)"
                                            />
                                            <button
                                                v-if="canSendByAnswer(question)"
                                                type="button"
                                                :aria-pressed="question.branching === true"
                                                title="Buka bagian berdasarkan jawaban"
                                                :class="[
                                                    'flex shrink-0 items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-bold transition',
                                                    question.branching
                                                        ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                                                        : 'border-slate-200 text-slate-600 hover:border-indigo-200 hover:text-indigo-700',
                                                ]"
                                                @click.stop="toggleSendByAnswer(question)"
                                            >
                                                <GitBranch class="h-3.5 w-3.5" />
                                                Bagian sesuai jawaban
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Duplikat pertanyaan"
                                                class="transition hover:text-indigo-600"
                                                @click.stop="duplicateQuestion(question)"
                                            >
                                                <FileText class="h-6 w-6" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Hapus pertanyaan"
                                                class="transition hover:text-red-500"
                                                @click.stop="deleteQuestion(question)"
                                            >
                                                <Trash2 class="h-6 w-6" />
                                            </button>
                                            <span class="hidden h-8 border-l border-slate-200 sm:block"></span>
                                            <button
                                                v-if="form.settings.isQuiz"
                                                type="button"
                                                class="flex shrink-0 items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-700 shadow-sm transition hover:bg-indigo-100"
                                                @click.stop="editingAnswerKeyQuestionId = question.id"
                                            >
                                                <Key class="h-3.5 w-3.5" />
                                                Kunci<span class="hidden sm:inline">Jawaban</span>
                                            </button>
                                            <span v-if="form.settings.isQuiz" class="hidden h-8 border-l border-slate-200 sm:block"></span>
                                            <span class="flex items-center gap-2">
                                                <span class="text-sm font-medium">Wajib</span>
                                                <button
                                                    type="button"
                                                    aria-label="Wajib diisi"
                                                    :aria-pressed="question.required"
                                                    @click.stop="
                                                        question.required = !question.required;
                                                        markChanged('Pengaturan wajib diubah');
                                                    "
                                                >
                                                    <ToggleRight
                                                        :class="[
                                                            'h-8 w-8 transition',
                                                            question.required ? 'text-indigo-600' : 'rotate-180 text-slate-300',
                                                        ]"
                                                    />
                                                </button>
                                            </span>
                                            <MoreVertical class="hidden h-6 w-6 sm:block" />
                                        </div>
                                    </div>
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
                                    class="fixed bottom-4 left-1/2 z-30 flex -translate-x-1/2 items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg lg:absolute lg:-right-20 lg:bottom-auto lg:left-auto lg:top-4 lg:z-20 lg:translate-x-0 lg:flex-col"
                                >
                                    <button type="button" aria-label="Tambah pertanyaan" class="tool-button-primary" @click.stop="addQuestion()">
                                        <PlusCircle class="h-5 w-5" />
                                    </button>
                                    <button type="button" aria-label="Impor soal" class="tool-button" @click.stop="isImportModalOpen = true">
                                        <FileUp class="h-5 w-5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Tambah judul dan deskripsi"
                                        class="tool-button"
                                        @click.stop="addTitleDescription"
                                    >
                                        <Type class="h-5 w-5" />
                                    </button>
                                    <button
                                        v-if="!isContentItem(question)"
                                        type="button"
                                        aria-label="Tambah gambar"
                                        class="tool-button"
                                        @click.stop="addMediaToActiveQuestion('image')"
                                    >
                                        <Image class="h-5 w-5" />
                                    </button>
                                    <button
                                        v-if="!isContentItem(question)"
                                        type="button"
                                        aria-label="Tambah video"
                                        class="tool-button"
                                        @click.stop="addMediaToActiveQuestion('video')"
                                    >
                                        <Video class="h-5 w-5" />
                                    </button>
                                    <button type="button" aria-label="Tambah bagian" class="tool-button" @click.stop="addSection">
                                        <PanelTop class="h-5 w-5" />
                                    </button>
                                </div>
                            </Transition>
                        </section>
                        </template>
                    </TransitionGroup>
                    <div v-if="hasManySections" class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 px-2 text-sm text-slate-600" data-testid="after-section">
                        <CornerDownRight class="h-4 w-4 shrink-0 text-slate-400" />
                        <span class="af-on-page font-semibold">Setelah bagian {{ editorSections.length }}</span>
                        <SectionTargetSelect
                            :model-value="sectionGoToValue(editorSections[editorSections.length - 1].key)"
                            :choices="targetChoices(editorSections[editorSections.length - 1].key)"
                            :label="`Setelah bagian ${editorSections.length}`"
                            @update:model-value="setSectionGoTo(editorSections[editorSections.length - 1].key, $event)"
                        />
                    </div>
                </div>

                <section v-else-if="activeTab === 'responses'" class="space-y-5">
                    <div class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-2xl font-semibold">Jawaban</h2>
                                <p class="mt-2 text-sm text-slate-500">Ringkasan jawaban responden dari tautan publik formulir ini.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    v-if="responseCount"
                                    :href="exportResponsesUrl"
                                    class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"
                                >
                                    <Download class="h-4 w-4" />
                                    Unduh Laporan (Excel)
                                </a>
                                <button
                                    type="button"
                                    class="with-tooltip rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-indigo-600 hover:bg-indigo-50"
                                    aria-label="Salin tautan publik"
                                    @click="copyShareUrl"
                                >
                                    Salin tautan
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-5">
                                <p class="flex items-center gap-2 text-sm font-bold text-slate-500">
                                    Siswa sudah submit
                                    <span
                                        v-if="isPublished"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold uppercase text-emerald-700"
                                        title="Diperbarui otomatis setiap 5 detik"
                                        ><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>Live</span
                                    >
                                </p>
                                <p class="mt-2 text-4xl font-black text-indigo-600">{{ examProgress.submitted }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ examProgress.responses }} jawaban masuk<span v-if="examProgress.working">
                                        · {{ examProgress.working }} sedang mengerjakan</span
                                    >
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-5">
                                <p class="text-sm font-bold text-slate-500">Menerima jawaban</p>
                                <p :class="['mt-2 text-xl font-bold', acceptingNow.on ? 'text-emerald-600' : 'text-rose-600']">{{ acceptingNow.label }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ acceptingNow.hint }}</p>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-slate-50 p-5">
                                <p class="text-sm font-bold text-slate-500">Tautan ujian</p>
                                <button
                                    type="button"
                                    class="mt-2 max-w-full truncate text-left text-sm font-bold text-indigo-600"
                                    @click="copyShareUrl"
                                >
                                    {{ shareUrl }}
                                </button>
                            </div>
                        </div>

                        <!-- Grading by hand: essays and files wait for the teacher -->
                        <div
                            v-if="responseCount && quizForm?.responses?.hasGradableQuestions && quizForm.responses.gradingUrl"
                            :class="[
                                'mt-6 flex flex-wrap items-center gap-3 rounded-2xl border px-4 py-3',
                                quizForm.responses.ungradedTotal ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white',
                            ]"
                        >
                            <PenLine :class="['h-5 w-5 shrink-0', quizForm.responses.ungradedTotal ? 'text-amber-600' : 'text-slate-400']" />
                            <p class="min-w-0 flex-1 text-sm text-slate-700">
                                <template v-if="quizForm.responses.ungradedTotal">
                                    <b>{{ quizForm.responses.ungradedTotal }} jawaban esai/berkas belum dinilai.</b> Nilai siswa yang bersangkutan masih
                                    sementara.
                                </template>
                                <template v-else>Semua esai dan berkas sudah dinilai. Jawaban isian singkat juga bisa dikoreksi manual.</template>
                            </p>
                            <button
                                type="button"
                                class="rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700"
                                @click="isGradingOpen = true"
                            >
                                Nilai esai & isian
                            </button>
                        </div>
                        <GradingPanel v-if="isGradingOpen && quizForm?.responses?.gradingUrl" :url="quizForm.responses.gradingUrl" @close="closeGrading" />

                        <!-- Rekapitulasi Nilai Siswa: every column sortable, A–Z initials like Moodle -->
                        <GradebookTable :rows="gradebook" :max-score="maxScore" :export-url="exportResponsesUrl" />

                        <ItemAnalysisPanel
                            v-if="itemAnalysis"
                            :analysis="itemAnalysis"
                            :is-applying="isApplyingDifficulty"
                            :apply-message="applyDifficultyMessage"
                            @apply-difficulty="applyAnalysedDifficulty"
                        />
                        <!-- The analysis is an add-on: a quiet pointer when it is not on for this exam -->
                        <div
                            v-else-if="responseCount && itemAnalysisAddon"
                            class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-3"
                        >
                            <Puzzle class="h-5 w-5 shrink-0 text-indigo-500" />
                            <p class="min-w-0 flex-1 text-sm text-slate-700">
                                {{
                                    itemAnalysisAddon.installedForOwner
                                        ? 'Add-on Analisis Butir Soal dimatikan untuk ujian ini.'
                                        : 'Ingin tahu soal mana yang perlu diperbaiki? Pasang add-on Analisis Butir Soal.'
                                }}
                            </p>
                            <button
                                type="button"
                                class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-50"
                                @click="itemAnalysisAddon.installedForOwner ? setAddonForExam(itemAnalysisAddon, true) : (isAddonGalleryOpen = true)"
                            >
                                {{ itemAnalysisAddon.installedForOwner ? 'Aktifkan' : 'Lihat add-on' }}
                            </button>
                        </div>

                        <MisconceptionPanel v-if="misconceptionMap && responseCount" :map="misconceptionMap" />
                        <div
                            v-else-if="responseCount && misconceptionAddon && form.settings.isQuiz !== false"
                            class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-3"
                        >
                            <Puzzle class="h-5 w-5 shrink-0 text-indigo-500" />
                            <p class="min-w-0 flex-1 text-sm text-slate-700">
                                {{
                                    misconceptionAddon.installedForOwner
                                        ? 'Add-on Peta Miskonsepsi dimatikan untuk ujian ini.'
                                        : 'Siswa yang menebak, belum paham, atau yakin pada konsep keliru? Pasang add-on Peta Miskonsepsi.'
                                }}
                            </p>
                            <button
                                type="button"
                                class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-50"
                                @click="misconceptionAddon.installedForOwner ? setAddonForExam(misconceptionAddon, true) : (isAddonGalleryOpen = true)"
                            >
                                {{ misconceptionAddon.installedForOwner ? 'Aktifkan' : 'Lihat add-on' }}
                            </button>
                        </div>

                        <RemedialPanel v-if="remedialOverview && responseCount" :overview="remedialOverview" />
                        <div
                            v-else-if="responseCount && remedialAddon"
                            class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-3"
                        >
                            <Puzzle class="h-5 w-5 shrink-0 text-indigo-500" />
                            <p class="min-w-0 flex-1 text-sm text-slate-700">
                                {{
                                    remedialAddon.installedForOwner
                                        ? 'Add-on Remedial & Pengayaan dimatikan untuk ujian ini.'
                                        : 'Siapkan remedial dan pengayaan dari nilai ini dalam beberapa klik: pasang add-on Remedial & Pengayaan.'
                                }}
                            </p>
                            <button
                                type="button"
                                class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-50"
                                @click="remedialAddon.installedForOwner ? setAddonForExam(remedialAddon, true) : (isAddonGalleryOpen = true)"
                            >
                                {{ remedialAddon.installedForOwner ? 'Aktifkan' : 'Lihat add-on' }}
                            </button>
                        </div>

                        <div v-if="latestResponses.length" class="mt-6 rounded-2xl border border-slate-200">
                            <div class="border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">Kiriman terbaru</div>
                            <div class="divide-y divide-slate-100">
                                <div v-for="response in latestResponses" :key="response.id" class="flex items-center justify-between gap-4 px-4 py-3">
                                    <span class="min-w-0 truncate text-sm font-semibold text-slate-700">
                                        {{ response.name ?? response.email ?? 'Responden anonim' }}
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
                                    <RichContent :content="question.title" as="h3" class="break-words text-base font-bold text-slate-900 sm:truncate sm:text-lg" />
                                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ question.total }} jawaban</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ questionTypeLabel(question.type) }}</span>
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
                            Bagikan tautan publik, lalu jawaban akan muncul di sini dalam bentuk ringkasan dan grafik.
                        </p>
                    </div>
                </section>

                <template v-else-if="activeTab === 'settings'">
                    <section class="rounded-xl border border-slate-300 bg-white shadow-sm">
                        <div class="px-5 py-6 sm:px-7 sm:py-7">
                            <h2 class="text-xl font-normal text-slate-950 sm:text-2xl">Pengaturan</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <div class="flex items-center justify-between gap-4 py-8 sm:gap-6 sm:px-11 sm:py-16">
                                <div>
                                    <h3 class="text-base font-normal text-slate-950 sm:text-xl">Jadikan ujian (dinilai)</h3>
                                    <p class="mt-1 text-sm text-slate-600 sm:mt-2 sm:text-lg">
                                        Beri poin dan kunci jawaban: jawaban dinilai otomatis, esai dan berkas dinilai guru
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    :class="[
                                        'relative h-5 w-11 shrink-0 rounded-full transition',
                                        form.settings.isQuiz ? 'bg-indigo-500' : 'bg-slate-300',
                                    ]"
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

                            <section class="py-8 sm:px-11 sm:py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isResponsesSettingsOpen = !isResponsesSettingsOpen"
                                >
                                    <span>
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Jawaban</span>
                                        <span class="mt-1 block text-sm text-slate-600 sm:mt-2 sm:text-lg"
                                            >Atur cara jawaban dikumpulkan</span
                                        >
                                    </span>
                                    <ChevronDown
                                        :class="['mt-2 h-6 w-6 shrink-0 transition-transform', isResponsesSettingsOpen ? 'rotate-180' : '']"
                                    />
                                </button>

                                <div v-if="isResponsesSettingsOpen" class="mt-8 space-y-8 sm:mt-10 sm:space-y-9">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:pl-11">
                                        <span>
                                            <label class="block text-base font-normal text-slate-950 sm:text-xl" for="emailCollectionMode"
                                                >Kumpulkan alamat email</label
                                            >
                                            <span class="mt-1 block text-sm text-slate-500">Untuk peserta tamu; siswa dengan akun sudah dikenali dari akunnya.</span>
                                        </span>
                                        <select
                                            id="emailCollectionMode"
                                            v-model="form.settings.emailCollectionMode"
                                            class="h-12 w-full rounded border border-slate-300 bg-white px-4 text-base text-slate-950 focus:border-indigo-500 focus:ring-indigo-500 sm:h-14 sm:w-64 sm:px-5 sm:text-lg"
                                            @change="markSettingsChanged"
                                        >
                                            <option value="none">Tidak dikumpulkan</option>
                                            <option value="responder">Diisi peserta</option>
                                        </select>
                                    </div>

                                    <p class="text-sm font-bold uppercase tracking-widest text-slate-600 sm:pl-11">Kesempatan mengerjakan</p>
                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Batas pengerjaan per siswa</span>
                                            <span class="mt-1 block text-sm text-slate-500"
                                                >Setiap pengerjaan disimpan dengan jawaban dan nilainya sendiri: yang lama tidak tertimpa.</span
                                            >
                                        </span>
                                        <select
                                            v-model.number="form.settings.maxAttempts"
                                            aria-label="Batas pengerjaan per siswa"
                                            class="h-11 shrink-0 rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-800"
                                            @change="updateMaxAttempts"
                                        >
                                            <option :value="0">Tanpa batas</option>
                                            <option v-for="count in 10" :key="count" :value="count">{{ count }} kali</option>
                                            <option v-if="form.settings.maxAttempts > 10" :value="form.settings.maxAttempts">
                                                {{ form.settings.maxAttempts }} kali
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <div class="border-t border-slate-200"></div>

                            <section class="py-8 sm:px-11 sm:py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isCohortSettingsOpen = !isCohortSettingsOpen"
                                >
                                    <span>
                                        <span class="flex flex-wrap items-center gap-2 text-base font-normal text-slate-950 sm:text-xl">
                                            <span>Peserta & Kelompok</span>
                                            <span
                                                v-if="selectedCohortIds.length > 0"
                                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800"
                                            >
                                                {{ selectedCohortIds.length }} Kelompok Terpilih
                                            </span>
                                        </span>
                                        <span class="mt-1 block text-sm text-slate-600 sm:mt-2 sm:text-lg">
                                            Batasi formulir agar hanya dapat dikerjakan oleh anggota kelompok/rombel tertentu
                                        </span>
                                    </span>
                                    <ChevronDown :class="['mt-2 h-6 w-6 shrink-0 transition-transform', isCohortSettingsOpen ? 'rotate-180' : '']" />
                                </button>

                                <div v-if="isCohortSettingsOpen" class="mt-8 space-y-6 sm:mt-10 sm:pl-11">
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
                                                    >Siapapun yang memiliki tautan dapat mengisi dan mengerjakan formulir ini.</span
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
                                                <span class="block text-base font-bold text-slate-900">Khusus Kelompok Tertentu</span>
                                                <span class="text-sm text-slate-500"
                                                    >Hanya siswa yang terdaftar di kelompok terpilih yang dapat mengakses formulir.</span
                                                >
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Guests' identity when public -->
                                    <div v-if="!isCohortRestricted" class="mt-5 max-w-xl space-y-3">
                                        <div>
                                            <p class="text-sm font-bold uppercase tracking-wider text-slate-500">Identitas Peserta Tamu</p>
                                            <p class="mt-1 text-sm text-slate-500">
                                                Diisi peserta tanpa akun di layar konfirmasi, sebelum mulai mengerjakan.
                                            </p>
                                        </div>
                                        <IdentityFieldsChecklist v-model="identityChecklist" />
                                    </div>

                                    <!-- Cohort Checklist when restricted -->
                                    <div v-if="isCohortRestricted" class="mt-5 space-y-3">
                                        <p class="text-sm font-bold uppercase tracking-wider text-slate-500">Pilih Kelompok yang Diizinkan:</p>

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
                                                >Belum ada Kelompok yang tersedia di sistem. Buat Kelompok terlebih dahulu di menu Kelompok & Kelas.</span
                                            >
                                            <Link :href="route('cohorts.index')" class="ml-2 font-bold underline">Kelola Kelompok</Link>
                                        </div>

                                        <p v-if="selectedCohortIds.length === 0" class="text-sm font-medium text-red-600">
                                            * Harap pilih minimal satu Kelompok jika pembatasan aktif, atau pilih "Terbuka untuk Semua".
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <div class="border-t border-slate-200"></div>

                            <section class="py-8 sm:px-11 sm:py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isPresentationSettingsOpen = !isPresentationSettingsOpen"
                                >
                                    <span>
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Tampilan</span>
                                        <span class="mt-1 block text-sm text-slate-600 sm:mt-2 sm:text-lg"
                                            >Atur tampilan soal dan halaman setelah mengirim</span
                                        >
                                    </span>
                                    <ChevronDown
                                        :class="['mt-2 h-6 w-6 shrink-0 transition-transform', isPresentationSettingsOpen ? 'rotate-180' : '']"
                                    />
                                </button>

                                <div v-if="isPresentationSettingsOpen" class="mt-8 space-y-8 sm:mt-10 sm:space-y-9">
                                    <p class="text-sm font-bold uppercase tracking-widest text-slate-600 sm:pl-11">Tampilan soal</p>
                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Tampilkan bilah kemajuan</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
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

                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Acak urutan soal</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
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

                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <div>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Acak urutan pilihan jawaban</span>
                                            <span class="mt-1 block text-sm text-slate-500">
                                                Tiap siswa melihat pilihan A–E dalam urutan berbeda, tetap sama walau halaman dimuat ulang. Pilihan
                                                seperti "Semua jawaban benar" atau "A dan C benar" membuat soal itu tidak diacak.
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="Boolean(form.settings.shuffleOptions)"
                                            aria-label="Acak urutan pilihan jawaban"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
                                                form.settings.shuffleOptions ? 'bg-indigo-500' : 'bg-slate-300',
                                            ]"
                                            @click="toggleSetting('shuffleOptions')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.shuffleOptions ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:pl-11">
                                        <div>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Pembagian Tampilan Soal</span>
                                            <span class="mt-1 block text-sm text-slate-500">Atur jumlah butir soal yang ditampilkan per halaman untuk siswa</span>
                                        </div>
                                        <select
                                            v-model="form.settings.questionsPerPage"
                                            class="h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-800 outline-none transition hover:border-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 sm:w-auto"
                                            @change="markSettingsChanged"
                                        >
                                            <option value="all">Semua Soal (1 Halaman Penuh)</option>
                                            <option value="1">1 Soal per Halaman</option>
                                            <option value="2">2 Soal per Halaman</option>
                                            <option value="3">3 Soal per Halaman</option>
                                            <option value="4">4 Soal per Halaman</option>
                                            <option value="5">5 Soal per Halaman</option>
                                            <option value="6">6 Soal per Halaman</option>
                                            <option value="7">7 Soal per Halaman</option>
                                            <option value="8">8 Soal per Halaman</option>
                                            <option value="9">9 Soal per Halaman</option>
                                            <option value="10">10 Soal per Halaman</option>
                                        </select>
                                    </div>

                                    <QuestionDrawSettings
                                        :draw="form.settings.questionDraw"
                                        :question-ids="answerableQuestions.map((question: Question) => question.id)"
                                        :difficulties="questionDifficulties"
                                        @update:draw="
                                            form.settings.questionDraw = $event;
                                            markSettingsChanged();
                                        "
                                    />

                                    <p class="text-sm font-bold uppercase tracking-widest text-slate-600 sm:pl-11">Setelah mengirim</p>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-8 sm:pl-11">
                                        <span>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Pesan konfirmasi</span>
                                            <span class="mt-1 block text-sm italic text-slate-600 sm:mt-2 sm:text-lg">{{
                                                form.settings.confirmationMessage
                                            }}</span>
                                        </span>
                                        <input
                                            v-model="form.settings.confirmationMessage"
                                            type="text"
                                            class="h-11 w-full rounded border border-slate-200 px-3 text-left text-base font-semibold text-blue-600 outline-none hover:border-slate-200 focus:border-indigo-500 focus:text-slate-900 sm:w-80 sm:border-transparent sm:text-right sm:text-lg"
                                            @input="markSettingsChanged"
                                        />
                                    </div>

                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl"
                                            >Tampilkan tautan untuk mengirim jawaban lagi</span
                                        >
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
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

                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Tampilkan "Dibuat dengan {{ BRAND_NAME }}"</span>
                                            <span class="mt-1 block text-sm text-slate-600 sm:text-lg">Satu baris kecil di bawah layar selesai.</span>
                                        </span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="Boolean(form.settings.showMadeWith)"
                                            :aria-label="`Tampilkan Dibuat dengan ${BRAND_NAME}`"
                                            :class="['relative h-5 w-11 shrink-0 rounded-full transition', form.settings.showMadeWith ? 'bg-indigo-500' : 'bg-slate-300']"
                                            @click="toggleSetting('showMadeWith')"
                                        >
                                            <span
                                                :class="[
                                                    'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                                                    form.settings.showMadeWith ? 'left-5' : '-left-1',
                                                ]"
                                            ></span>
                                        </button>
                                    </div>

                                    <div class="sm:pl-11">
                                        <div class="flex items-center justify-between gap-4 sm:gap-8">
                                            <span>
                                                <span class="block text-base font-normal text-slate-950 sm:text-xl">Tampilkan hasil ke siswa</span>
                                                <span class="mt-1 block text-sm text-slate-600 sm:text-lg"
                                                    >Siswa melihat nilainya di layar selesai dan di halaman hasil. Bisa dinyalakan kapan saja, juga setelah
                                                    ujian.</span
                                                >
                                            </span>
                                            <button
                                                type="button"
                                                role="switch"
                                                :aria-checked="Boolean(form.settings.showResultsSummary)"
                                                aria-label="Tampilkan hasil ke siswa"
                                                :class="[
                                                    'relative h-5 w-11 shrink-0 rounded-full transition',
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
                                        <div v-if="form.settings.showResultsSummary" class="mt-4 space-y-2 rounded-2xl bg-slate-50 p-4">
                                            <label class="flex cursor-pointer items-start gap-3">
                                                <input
                                                    v-model="form.settings.resultsDetail"
                                                    type="radio"
                                                    value="score"
                                                    class="mt-1 h-4 w-4 text-indigo-600"
                                                    @change="markSettingsChanged"
                                                />
                                                <span class="text-sm text-slate-700"><b>Nilai saja.</b> Kunci jawaban tetap rahasia.</span>
                                            </label>
                                            <label class="flex cursor-pointer items-start gap-3">
                                                <input
                                                    v-model="form.settings.resultsDetail"
                                                    type="radio"
                                                    value="full"
                                                    class="mt-1 h-4 w-4 text-indigo-600"
                                                    @change="markSettingsChanged"
                                                />
                                                <span class="text-sm text-slate-700"
                                                    ><b>Nilai, jawaban benar, pembahasan, dan komentar guru</b> untuk setiap soal.</span
                                                >
                                            </label>
                                            <p v-if="form.settings.resultsDetail === 'full'" class="text-xs font-semibold text-amber-700">
                                                Nyalakan setelah semua siswa selesai agar kunci jawaban tidak tersebar ke siswa yang belum mengerjakan.
                                            </p>
                                        </div>
                                    </div>

                                    <p class="text-sm font-bold uppercase tracking-widest text-slate-600 sm:pl-11">Pembatasan</p>
                                    <div class="flex items-center justify-between gap-4 sm:gap-8 sm:pl-11">
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl"
                                            >Matikan simpan otomatis jawaban siswa</span
                                        >
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
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
                        <div class="px-5 py-6 sm:px-7 sm:py-7">
                            <h2 class="text-xl font-normal text-slate-950 sm:text-2xl">Keamanan & Batas</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <div class="flex items-center justify-between gap-4 py-8 sm:gap-6 sm:px-11 sm:py-12">
                                <div>
                                    <h3 class="text-base font-normal text-slate-950 sm:text-xl">Kunci saat siswa pindah tab</h3>
                                    <p class="mt-1 text-sm text-slate-600 sm:mt-2 sm:text-lg">
                                        Ujian langsung terkunci bila siswa meninggalkan halaman ujian; pengawas yang membukanya.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    :class="[
                                        'relative h-5 w-11 shrink-0 rounded-full transition',
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

                            <div class="flex items-center justify-between gap-4 py-8 sm:gap-6 sm:px-11 sm:py-12">
                                <div>
                                    <h3 class="text-base font-normal text-slate-950 sm:text-xl">Batas Waktu Pengerjaan (Menit)</h3>
                                    <p class="mt-1 text-sm text-slate-600 sm:mt-2 sm:text-lg">
                                        Batasi waktu pengisian formulir (0 atau kosong untuk tanpa batas waktu)
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <input
                                        v-model.number="form.settings.timeLimit"
                                        type="number"
                                        min="0"
                                        class="h-11 w-20 rounded border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:h-12 sm:w-28 sm:px-4 sm:text-lg"
                                        @change="markSettingsChanged"
                                    />
                                    <span class="text-sm text-slate-600 sm:text-lg">menit</span>
                                </div>
                            </div>

                            <div class="border-t border-slate-200"></div>

                            <!-- When students may start: closed before, and the time runs out at the closing time -->
                            <div class="py-8 sm:px-11 sm:py-12">
                                <h3 class="text-base font-normal text-slate-950 sm:text-xl">Jadwal ujian</h3>
                                <p class="mt-1 text-sm text-slate-600 sm:mt-2 sm:text-lg">
                                    Siswa hanya dapat mulai di antara waktu buka dan tutup. Saat ujian ditutup, waktu siswa yang masih mengerjakan habis dan
                                    jawabannya terkirim otomatis.
                                </p>
                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <label class="block text-sm font-semibold text-slate-700">
                                        Dibuka
                                        <input
                                            type="datetime-local"
                                            :value="toLocalDateTime(form.settings.schedule.opensAt)"
                                            class="mt-1 h-11 w-full rounded border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                                            @change="setSchedule('opensAt', ($event.target as HTMLInputElement).value)"
                                        />
                                    </label>
                                    <label class="block text-sm font-semibold text-slate-700">
                                        Ditutup
                                        <input
                                            type="datetime-local"
                                            :value="toLocalDateTime(form.settings.schedule.closesAt)"
                                            class="mt-1 h-11 w-full rounded border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                                            @change="setSchedule('closesAt', ($event.target as HTMLInputElement).value)"
                                        />
                                    </label>
                                </div>
                                <p v-if="scheduleProblem" class="mt-2 text-sm font-semibold text-rose-700" role="alert">{{ scheduleProblem }}</p>
                                <p v-else-if="form.settings.schedule.opensAt || form.settings.schedule.closesAt" class="mt-2 text-sm text-slate-500">
                                    {{ scheduleSummary }}
                                    <button type="button" class="ml-1 font-bold text-indigo-600 hover:underline" @click="clearSchedule">Hapus jadwal</button>
                                </p>
                                <p v-else class="mt-2 text-sm text-slate-500">Tanpa jadwal: ujian dapat dimulai kapan saja selama diterbitkan.</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-300 bg-white shadow-sm">
                        <div class="px-5 py-6 sm:px-7 sm:py-7">
                            <h2 class="text-xl font-normal text-slate-950 sm:text-2xl">Bawaan</h2>
                            <div class="mt-7 border-t border-slate-200"></div>

                            <section class="py-8 sm:px-11 sm:py-12">
                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-5 text-left"
                                    @click="isQuestionDefaultsOpen = !isQuestionDefaultsOpen"
                                >
                                    <span>
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Bawaan soal baru</span>
                                        <span class="mt-1 block text-sm text-slate-600 sm:mt-2 sm:text-lg"
                                            >Berlaku untuk soal yang ditambahkan ke ujian ini</span
                                        >
                                    </span>
                                    <ChevronDown
                                        :class="['mt-2 h-6 w-6 shrink-0 transition-transform', isQuestionDefaultsOpen ? 'rotate-180' : '']"
                                    />
                                </button>

                                <div v-if="isQuestionDefaultsOpen" class="mt-8 space-y-8 sm:mt-10 sm:pl-11">
                                    <div v-if="form.settings.isQuiz" class="flex items-center justify-between gap-4 sm:gap-8">
                                        <span class="block text-base font-normal text-slate-950 sm:text-xl">Soal baru wajib dijawab</span>
                                        <button
                                            type="button"
                                            :class="[
                                                'relative h-5 w-11 shrink-0 rounded-full transition',
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

                                    <div class="flex items-center justify-between gap-4 sm:gap-8">
                                        <span>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Poin bawaan soal</span>
                                            <span class="mt-1 block text-sm text-slate-600 sm:text-lg">Dipakai saat menambah soal baru.</span>
                                        </span>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <input
                                                v-model.number="form.settings.defaultQuestionPoints"
                                                type="number"
                                                min="0"
                                                max="1000"
                                                class="h-11 w-20 rounded border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:h-12 sm:w-28 sm:px-4 sm:text-lg"
                                                @change="markSettingsChanged"
                                            />
                                            <span class="text-sm text-slate-600 sm:text-lg">poin</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-4 sm:gap-8">
                                        <span>
                                            <span class="block text-base font-normal text-slate-950 sm:text-xl">Batas ukuran berkas</span>
                                            <span class="mt-1 block text-sm text-slate-600 sm:text-lg"
                                                >Untuk gambar/video di editor dan berkas jawaban siswa (soal Unggah berkas).</span
                                            >
                                        </span>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <input
                                                v-model.number="form.settings.maxUploadSize"
                                                type="number"
                                                min="1"
                                                max="40"
                                                class="h-11 w-20 rounded border border-slate-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:h-12 sm:w-28 sm:px-4 sm:text-lg"
                                                @change="markSettingsChanged"
                                            />
                                            <span class="text-sm text-slate-600 sm:text-lg">MB</span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>
                </template>

                <div v-else-if="activeTab === 'keamanan'" class="space-y-5">
                    <ExamTokenCard
                        v-if="examTokenAddon && quizForm?.examTokenUrl"
                        :addon="examTokenAddon"
                        :is-on="isAddonOnForExam(examTokenAddon)"
                        :token-url="quizForm.examTokenUrl"
                        :exam-title="form.title"
                        @toggle="(on) => examTokenAddon && setAddonForExam(examTokenAddon, on)"
                        @open-gallery="isAddonGalleryOpen = true"
                    />
                    <ClassMapPanel v-if="quizForm?.classMapUrl" :url="quizForm.classMapUrl" :busy-ids="unlockingIds" @unlock="unlockFromClassMap" />
                    <UnlockRequestsPanel
                        :unlock-requests="unlockRequests"
                        :lock-on-blur="Boolean(form.settings.lockOnBlur)"
                        :busy-ids="unlockingIds"
                        :is-unlocking-all="isUnlockingAll"
                        @refresh="fetchUnlockRequests"
                        @unlock="unlockStudent"
                        @unlock-all="unlockAllStudents"
                        @toggle-lock="toggleSetting('lockOnBlur')"
                    />
                </div>
            </div>
        </section>

        <div v-if="showPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section
                :class="['max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl', activeDesign ? 'af-designed' : '']"
                :style="activeDesignVariables"
            >
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Pratinjau</h2>
                    <button type="button" class="rounded-full px-3 py-1 text-slate-500 hover:bg-slate-100" @click="showPreview = false">Tutup</button>
                </div>
                <img v-if="activeDesign?.headerImage" :src="activeDesign.headerImage" alt="" class="af-header-image mb-4" />
                <div :class="['mb-5 h-3 rounded-full', themeClass]"></div>
                <div v-if="activeDesign?.logo" class="mb-3 flex" :style="{ justifyContent: logoJustify(activeDesign) }">
                    <img :src="activeDesign.logo" alt="Logo" class="af-logo" />
                </div>
                <h3 :class="['text-3xl font-semibold', activeDesign ? 'af-title' : '']"><RichContent :content="form.title" /></h3>
                <RichContent v-if="form.description" :content="form.description" as="p" class="mt-2 text-slate-500" />
                <div class="mt-6 space-y-5">
                    <div
                        v-for="question in form.questions"
                        :key="`preview-${question.id}`"
                        :class="['rounded-2xl border p-5', question.type === 'Section' ? 'border-indigo-300 bg-indigo-50/40' : 'border-slate-200']"
                    >
                        <p v-if="question.type === 'Section'" class="mb-1 text-xs font-bold uppercase tracking-wider text-indigo-700">
                            {{ sectionLabels[question.id] }}
                        </p>
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
                        <RichContent v-if="question.description" :content="question.description" as="p" class="mt-1 text-sm text-slate-500" />
                        <!-- Preview media display -->
                        <div v-if="question.media && question.media.length" class="mb-4 mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            Area unggah berkas
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
                            <option v-for="option in question.options" :key="option" :value="option">{{ stripRichFormatting(option) }}</option>
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
                            v-else-if="!isContentItem(question)"
                            type="text"
                            class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                            placeholder="Jawaban Anda"
                        />
                    </div>
                </div>
            </section>
        </div>

        <div v-if="isImportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section
                :class="[
                    'max-h-[92vh] w-full space-y-5 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl',
                    importSource === 'bank' ? 'max-w-2xl' : 'max-w-lg',
                ]"
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-extrabold text-slate-900">Import Soal</h2>
                    <button type="button" class="text-lg font-bold text-slate-400 transition hover:text-slate-600" @click="isImportModalOpen = false">
                        x
                    </button>
                </div>

                <!-- Segmented Tabs: ExamView (.zip), Word (.docx) and the question bank -->
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
                    <button
                        type="button"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-bold transition',
                            importSource === 'bank' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="importSource = 'bank'"
                    >
                        <Library class="h-4 w-4" />
                        Bank Soal
                    </button>
                </div>

                <!-- Question bank: the teacher's own earlier questions -->
                <QuestionBankPicker v-if="importSource === 'bank'" :exam-id="props.quizForm?.id" @add="addQuestionsFromBank" />

                <!-- ExamView Import Tab -->
                <div v-else-if="importSource === 'examview'" class="space-y-4">
                    <div class="space-y-2 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 text-xs text-indigo-950">
                        <span class="block font-bold text-indigo-900">Panduan Ekspor dari ExamView:</span>
                        <ol class="list-inside list-decimal space-y-1 text-slate-600">
                            <li>
                                Buka bank soal (<code class="font-mono text-indigo-700">.bnk</code>) di aplikasi
                                <strong>ExamView Test Generator</strong>.
                            </li>
                            <li>
                                Pilih menu <strong>File &gt; Export &gt; Blackboard 7.1 - 9.0</strong> (atau
                                <strong>Blackboard 6.0 - 7.0</strong>).
                            </li>
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
                            <span class="mt-1 text-[11px] text-slate-500">Mendukung .zip Blackboard 6.0 - 7.0 dan 7.1 - 9.0 (maks. 64MB)</span>
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

                        <div v-if="importWarnings.length" class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900">
                            <span class="block font-extrabold">{{ importSummary }}</span>
                            <ul class="max-h-48 list-inside list-disc space-y-1 overflow-y-auto font-medium">
                                <li v-for="(warning, warningIndex) in importWarnings" :key="`import-warning-${warningIndex}`">{{ warning }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Word (.docx) Import Tab -->
                <div v-else class="space-y-4">
                    <p class="text-sm leading-relaxed text-slate-500">
                        Unggah dokumen Word (.docx) berisi soal Anda. Templat di bawah berisi contoh setiap tipe soal yang bisa langsung dicoba impor.
                    </p>

                    <!-- Clean style template download section -->
                    <div class="flex items-center justify-between rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                <FileText class="h-5 w-5" />
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-slate-800">Templat Soal DOCX</span>
                                <span class="block text-[11px] font-medium text-slate-500">Contoh semua tipe soal</span>
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

                    <details class="group rounded-2xl border border-slate-200 bg-slate-50/60 text-xs text-slate-600">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 font-bold text-slate-800">
                            Ringkasan aturan penulisan
                            <ChevronDown class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="space-y-3 border-t border-slate-200 px-4 pb-4 pt-3">
                            <pre
                                class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3 font-mono text-[11px] leading-relaxed text-slate-700"
                            >
1. Teks soal
Tipe: Kotak Centang
Poin: 1
A. Pilihan pertama
B. Pilihan kedua
C. Pilihan ketiga
Jawaban: A, C</pre
                            >
                            <ul class="list-inside list-disc space-y-1">
                                <li>Setiap soal diakhiri baris <strong>Jawaban:</strong> (soal tanpa kunci: <code>Jawaban: -</code>).</li>
                                <li>
                                    <strong>Tipe:</strong> boleh tidak ditulis untuk Pilihan Ganda dan Isian Singkat. <strong>Poin:</strong>,
                                    <strong>Wajib:</strong> dan <strong>Skala:</strong> opsional.
                                </li>
                                <li>Benar/Salah beberapa pernyataan, Menjodohkan dan Kisi ditulis dalam tabel Word.</li>
                                <li>Kop/petunjuk di atas baris <strong>MULAI SOAL</strong> dan baris diawali <code>//</code> tidak ikut diimpor.</li>
                            </ul>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="docxType in docxImportTypes"
                                    :key="docxType"
                                    class="rounded-full border border-indigo-100 bg-white px-2.5 py-1 text-[11px] font-semibold text-indigo-700"
                                >
                                    {{ docxType }}
                                </span>
                            </div>
                            <a
                                :href="route('help', { topic: 'docx-import' })"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 font-bold text-indigo-700 hover:underline"
                            >
                                Panduan lengkap setiap tipe soal di Pusat Bantuan
                                <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </details>

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
                            <span class="mt-1 text-[11px] text-slate-500">Dukung file format .docx (maks. 10MB)</span>
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

                        <div
                            v-if="importWarnings.length"
                            class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"
                        >
                            <span class="block font-extrabold">{{ importSummary }}</span>
                            <ul class="max-h-48 list-inside list-disc space-y-1 overflow-y-auto font-medium">
                                <li v-for="(warning, warningIndex) in importWarnings" :key="`docx-import-warning-${warningIndex}`">{{ warning }}</li>
                            </ul>
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
        <div v-if="isAddonGalleryOpen" class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section
                class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl duration-150 animate-in fade-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-labelledby="addon-gallery-title"
            >
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <Puzzle class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 id="addon-gallery-title" class="text-xl font-extrabold text-slate-900">Add-on</h2>
                            <p class="text-xs text-slate-500">Pasang sekali, lalu nyalakan atau matikan untuk tiap ujian.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Tutup"
                        @click="isAddonGalleryOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-5 overflow-y-auto p-5 sm:p-6">
                    <div v-if="examAddons.length" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                        <p class="px-2 pb-2 pt-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Di ujian ini</p>
                        <AddonExamSwitches :addons="examAddons" :is-on="isAddonOnForExam" layout="grid" @toggle="setAddonForExam" />
                    </div>
                    <AddonGallery
                        :addons="addonCatalog"
                        :can-install-for-everyone="Boolean(quizForm?.canInstallAddonsForEveryone)"
                        :install-url="quizForm?.addonUrls?.install ?? ''"
                        :uninstall-url="quizForm?.addonUrls?.uninstall ?? ''"
                        @update:addons="onAddonsChanged"
                    />
                </div>
            </section>
        </div>

        <div v-if="isCollaboratorModalOpen" class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="w-full max-w-lg space-y-5 rounded-3xl bg-white p-6 shadow-2xl duration-150 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <UserPlus class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Kolaborasi Formulir</h2>
                            <p class="text-xs text-slate-500">Kelola guru yang dapat melihat dan mengedit formulir ini.</p>
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
                        Kolaborasi formulir hanya dapat dilakukan antar sesama akun Guru. Akun siswa tidak dapat ditambahkan ke formulir.
                    </div>
                </div>

                <!-- Section: Pemilik (Owner) -->
                <div class="space-y-2">
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pemilik Formulir</label>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                                {{ (props.quizForm?.owner?.name || 'G')[0].toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900">{{ props.quizForm?.owner?.name ?? 'Pemilik Formulir' }}</div>
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

        <div v-if="showPublishIdentity" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section role="dialog" aria-modal="true" aria-labelledby="publish-identity-title" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <h2 id="publish-identity-title" class="text-xl font-black text-slate-900">Publikasikan untuk Publik</h2>
                <p class="mt-1.5 text-sm text-slate-500">
                    Siapa pun yang punya tautan dapat mengerjakan formulir ini tanpa akun. Pilih data yang wajib diisi peserta di layar konfirmasi
                    sebelum mulai.
                </p>
                <IdentityFieldsChecklist v-model="publishIdentityChoice" class="mt-5" />
                <p class="mt-3 text-xs text-slate-400">Siswa yang masuk dengan akun tidak perlu mengisi: data diambil dari akunnya.</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100"
                        @click="showPublishIdentity = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700"
                        @click="confirmPublish"
                    >
                        Publikasikan
                    </button>
                </div>
            </section>
        </div>

        <div v-if="showPublish" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h2 class="text-2xl font-bold">Terbitkan ujian</h2>
                <p class="mt-2 text-slate-500">Formulir siap dibagikan. Tautan tetap memakai slug ini meskipun judul formulir diganti.</p>
                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <label class="text-sm font-bold text-slate-500" for="public-slug">Tautan publik</label>
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
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2 font-bold text-white hover:bg-indigo-700"
                        :disabled="isSaving"
                        @click="saveDraft(false)"
                    >
                        {{ isSaving ? 'Menyimpan...' : 'Simpan tautan' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-emerald-500 px-4 py-2 font-bold text-white hover:bg-emerald-600"
                        :disabled="isSaving || Boolean(slugWarning)"
                        @click="saveDraftAndCopy()"
                    >
                        Salin
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
                    <!-- Desainer Form add-on: the full designer -->
                    <div v-if="formDesignerAddon" class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4" data-testid="designer-callout">
                        <p class="flex items-center gap-2 text-sm font-black text-slate-900">
                            <Paintbrush class="h-4 w-4 text-indigo-600" />
                            Desainer Formulir
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">
                            Tema siap pakai, gambar header, logo lembaga, foto latar, huruf, bentuk kartu dan CSS sendiri, untuk editor dan semua halaman siswa.
                        </p>
                        <p v-if="formDesignerAddon.installed && !formDesignerAddon.installedForOwner" class="mt-3 text-xs font-semibold text-amber-700">
                            Pemilik ujian ini belum memasang Desainer Formulir, jadi desain belum dapat dipakai di ujian ini.
                        </p>
                        <button
                            v-else
                            type="button"
                            class="mt-3 w-full rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-indigo-700"
                            @click="startFormDesigner"
                        >
                            {{ formDesignerAddon.installedForOwner ? 'Aktifkan & buka Desainer Formulir' : 'Pasang add-on Desainer Formulir' }}
                        </button>
                    </div>

                    <!-- Fonts Configuration -->
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Gaya Font</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Font Pertanyaan</label>
                                <select
                                    v-model="form.settings.questionFont"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                                    @change="markChanged('Huruf tema diperbarui')"
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
                                    @change="markChanged('Huruf tema diperbarui')"
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
                                    markChanged('Warna tema diperbarui');
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
                                    markChanged('Pola latar diperbarui');
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

        <!-- Bold, italic, underline and highlighter over selected text of any field -->
        <SelectionFormatToolbar />

        <FormDesigner
            v-if="showFormDesigner && activeDesign"
            :model-value="activeDesign"
            :title="form.title"
            :description="form.description"
            :question-font="form.settings.questionFont"
            :answer-font="form.settings.answerFont"
            :public-url="quizForm?.publicUrl"
            @update:model-value="updateDesign"
            @update:question-font="(font) => { form.settings.questionFont = font; markChanged('Huruf soal diperbarui'); }"
            @update:answer-font="(font) => { form.settings.answerFont = font; markChanged('Huruf jawaban diperbarui'); }"
            @close="showFormDesigner = false"
        />

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
        <!-- Media Lightbox Zoom Modal -->
        <MediaLightboxModal
            :show="!!activeLightboxMedia"
            :media="activeLightboxMedia"
            @close="activeLightboxMedia = null"
        />
    </main>
</template>

<style scoped>
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
    transform: translateX(-50%);
    white-space: nowrap;
    border-radius: 8px;
    background: #0f172a;
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    color: white;
    box-shadow: 0 10px 25px rgb(15 23 42 / 18%);
    /* Only rendered while shown: a hidden tooltip near the screen edge would widen the page on phones. */
    display: none;
}

button[aria-label]:focus-visible::after,
.with-tooltip:focus-visible::after {
    display: block;
}

@media (hover: hover) {
    button[aria-label]:hover::after,
    .with-tooltip:hover::after {
        display: block;
    }
}
</style>
