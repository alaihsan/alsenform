import type { AddonEntry } from '@/components/addons/AddonGallery.vue';
import type { CardDesign, FormDesign } from '@/lib/formDesign';
import type { Component } from 'vue';

export type QuestionType =
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
    | 'Time'
    // Not questions, like in Google Forms: a page break with a title, and a block of text.
    | 'Section'
    | 'Title and description';

/** Items of a form that are not questions: never answered, numbered or scored. */
export const CONTENT_ITEM_TYPES: QuestionType[] = ['Section', 'Title and description'];

export type Question = {
    id: number;
    title: string;
    description: string;
    type: QuestionType;
    options: string[];
    rows?: string[];
    columns?: string[];
    answer: any;
    required: boolean;
    media: { type: 'image' | 'video'; url: string; sourceType?: 'upload' | 'link'; isUploading?: boolean; uploadError?: string; width?: string; align?: 'left' | 'center' | 'right'; caption?: string }[];
    points: number;
    /** Pembahasan: why the answer is right, shown to students with their results. */
    explanation?: string;
    /** A section: where the student goes after it (see utils/section-flow). */
    goTo?: SectionTarget;
    /** A multiple-choice or drop-down question: "Buka bagian berdasarkan jawaban". */
    branching?: boolean;
    /** With branching: where each option sends the student, in the order of the options. */
    optionGoTo?: SectionTarget[];
    /** Editor only, not saved: the question's field written right to left (the RTL button of Teks Arab (RTL)). */
    isRtl?: boolean;
};

/** Where a section leads: the next section, the end of the form, or the key of another section. */
export type SectionTarget = 'next' | 'submit' | number;

export type PreviewAnswer = string | string[] | Record<number, any>;

export type QuizFormPayload = {
    id: number;
    title: string;
    description: string;
    slug: string;
    questions: Question[];
    isPublished?: boolean;
    /** Fingerprint of the saved content; sent back with each save to detect outdated copies. */
    version?: string;
    /** Labels of each question (from the question bank), by question id. */
    questionLabels?: Record<string, { subject?: string | null; grade?: string | null; topic?: string | null; difficulty?: string | null }>;
    labelSuggestions?: { subjects: string[]; grades: string[]; topics: string[] };
    labelsUrl?: string;
    /** The add-ons of the teacher looking at the exam, with whether each is on for it. */
    addons?: AddonEntry[];
    addonUrls?: { gallery: string; install: string; uninstall: string };
    canInstallAddonsForEveryone?: boolean;
    /** Token Ujian add-on: the current token for the proctor. */
    examTokenUrl?: string;
    /** Peta Kelas: every student of the exam while it runs. */
    classMapUrl?: string;
    /** A remedial or enrichment exam (Remedial & Pengayaan add-on): what it follows up, and for whom. */
    followUp?: {
        kind: 'remedial' | 'pengayaan';
        of: { title: string; editUrl: string | null } | null;
        participants: { id: number; name: string; kelas: string | null }[];
    } | null;
    settings: {
        collectEmail: boolean;
        showProgress: boolean;
        shuffleQuestions: boolean;
        shuffleOptions?: boolean;
        /** A random selection of the questions for each student (see App\\Support\\ExamLayout). */
        /** Where the first section leads when the form does not start with a section item (see utils/section-flow). */
        firstSectionGoTo?: SectionTarget;
        questionDraw?: {
            enabled: boolean;
            mode: 'count' | 'difficulty';
            count: number;
            perDifficulty: { mudah: number; sedang: number; sulit: number };
        };
        isQuiz?: boolean;
        emailCollectionMode?: 'none' | 'verified' | 'responder';
        sendResponseCopy?: 'off' | 'request' | 'always';
        allowResponseEditing?: boolean;
        limitOneResponse?: boolean;
        /** How many times a student may take the quiz; 0 is no limit. */
        maxAttempts?: number;
        /** Add-ons switched on or off for this exam, by add-on key. */
        addons?: Record<string, boolean>;
        confirmationMessage?: string;
        showSubmitAnotherResponse?: boolean;
        showResultsSummary?: boolean;
        /** The "Dibuat dengan Falcon Forms" line on the finish screen (absent: shown). */
        showMadeWith?: boolean;
        resultsDetail?: 'score' | 'full';
        schedule?: { opensAt?: string | null; closesAt?: string | null };
        disableRespondentAutosave?: boolean;
        defaultCollectEmailMode?: 'none' | 'verified' | 'responder';
        defaultQuestionRequired?: boolean;
        defaultQuestionPoints?: number;
        maxUploadSize?: number;
        questionFont?: string;
        answerFont?: string;
        themeColorClass?: string;
        backgroundColorClass?: string;
        backgroundPatternClass?: string;
        /** Desainer Form add-on: the exam's look (see App\\Support\\FormDesign). */
        design?: Partial<FormDesign> | null;
        lockOnBlur?: boolean;
        timeLimit?: number;
        /** Identity fields guests fill in on a public quiz (email is collectEmail). */
        respondentFields?: ('name' | 'class')[];
    };
    responses: {
        total: number;
        /** Students who handed in at least once (each counted once). */
        students?: number;
        progressUrl?: string;
        maxScore?: number;
        exportUrl?: string;
        /** Grading by hand (essays, files, short answers). */
        gradingUrl?: string;
        ungradedTotal?: number;
        hasGradableQuestions?: boolean;
        /** Filter Cerdas add-on on for this exam: the Excel mode's data, fetched when it is opened. */
        smartFilterUrl?: string | null;
        latest: {
            id: number;
            name?: string | null;
            email: string | null;
            submittedAt: string;
        }[];
        gradebook?: {
            id: number;
            user_id?: number | null;
            name: string;
            /** No account: the name and class come from the confirmation screen. */
            isGuest?: boolean;
            /** A guest name (and class) that appears on more than one response. */
            duplicateName?: boolean;
            nis: string;
            kelas: string;
            email: string;
            score: number;
            /** The points this student could earn: their own when questions are drawn per student. */
            maxScore?: number;
            percentage: number;
            /** Essays and files the teacher has not graded yet: the grade is provisional. */
            ungraded?: number;
            /** Answers that arrived after the time ran out (a page offline at the end). */
            lateMinutes?: number | null;
            /** Attempt number of this response (1 is the first) and the student's attempts in all. */
            attempt?: number;
            attempts?: number;
            is_timeout: boolean;
            /** Handed in by the server: the time ran out while the exam page was closed or offline. */
            is_auto_submitted?: boolean;
            /** Unix time of handing in, for sorting. */
            submittedAtTimestamp?: number;
            submittedAt: string;
            formattedDate: string;
            /** The receipt shown to the student after handing in, as "7F3A-9C21". */
            receipt?: string | null;
            /** Whether the answers kept are still the ones handed in (null: no receipt). */
            receiptIntact?: boolean | null;
            /** The answers the server had during the exam, and what was handed in. */
            historyUrl?: string;
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
    } | null;
    exportResponsesUrl?: string;
    updateUrl: string;
    publicUrl: string;
    cohortIds?: number[];
    availableCohorts?: {
        id: number;
        name: string;
        code?: string | null;
    }[];
    isOwner?: boolean;
    owner?: {
        id: number;
        name: string;
        email: string | null;
    } | null;
    collaborators?: {
        id: number;
        name: string;
        email: string | null;
    }[];
    availableTeachers?: {
        id: number;
        name: string;
        email: string | null;
    }[];
    inviteCollaboratorUrl?: string;
};

export type QuestionTypeOption = {
    value: QuestionType;
    group: string;
    icon: Component;
};

export type PreviewQuestion = {
    id?: number | string | null;
    title: string;
    type: string;
    options?: string[];
};

export type RecentForm = {
    id: number;
    title: string;
    description: string;
    folderId: number | null;
    folder: string | null;
    editUrl: string;
    publicUrl: string;
    duplicateUrl: string;
    moveFolderUrl: string;
    deleteUrl: string;
    restoreUrl: string;
    forceDeleteUrl: string;
    updatedLabel: string;
    updatedAt: string;
    tone: string;
    stripe: string;
    accent: string;
    themeColorClass?: string | null;
    backgroundColorClass?: string | null;
    /** Desainer Form add-on: what the card shows of the exam's design. */
    design?: CardDesign | null;
    questionsCount?: number;
    previewQuestions?: PreviewQuestion[];
    firstQuestion?: PreviewQuestion | null;
    isPublished: boolean;
    isTrashed: boolean;
    isCollaborator?: boolean;
    ownerName?: string;
    hasSubmitted?: boolean;
    /** Students: their results, once the teacher shows them; and the exam's schedule. */
    resultsUrl?: string | null;
    scheduleLabel?: string | null;
};

export type QuizFolder = {
    id: number;
    name: string;
    formsCount: number;
    updateUrl: string;
    deleteUrl: string;
};
