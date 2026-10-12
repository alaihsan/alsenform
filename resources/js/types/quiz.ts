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
    | 'Time';

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
};

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
    settings: {
        collectEmail: boolean;
        showProgress: boolean;
        shuffleQuestions: boolean;
        shuffleOptions?: boolean;
        /** A random selection of the questions for each student (see App\\Support\\ExamLayout). */
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
        confirmationMessage?: string;
        showSubmitAnotherResponse?: boolean;
        showResultsSummary?: boolean;
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
        lockOnBlur?: boolean;
        timeLimit?: number;
        /** Identity fields guests fill in on a public quiz (email is collectEmail). */
        respondentFields?: ('name' | 'class')[];
    };
    responses: {
        total: number;
        maxScore?: number;
        exportUrl?: string;
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
            /** Answers that arrived after the time ran out (a page offline at the end). */
            lateMinutes?: number | null;
            is_timeout: boolean;
            /** Handed in by the server: the time ran out while the exam page was closed or offline. */
            is_auto_submitted?: boolean;
            submittedAt: string;
            formattedDate: string;
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
    questionsCount?: number;
    previewQuestions?: PreviewQuestion[];
    firstQuestion?: PreviewQuestion | null;
    isPublished: boolean;
    isTrashed: boolean;
    isCollaborator?: boolean;
    ownerName?: string;
    hasSubmitted?: boolean;
};

export type QuizFolder = {
    id: number;
    name: string;
    formsCount: number;
    updateUrl: string;
    deleteUrl: string;
};
