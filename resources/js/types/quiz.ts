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
    media: { type: 'image' | 'video'; url: string; sourceType?: 'upload' | 'link'; isUploading?: boolean; uploadError?: string }[];
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
        maxUploadSize?: number;
        questionFont?: string;
        answerFont?: string;
        themeColorClass?: string;
        backgroundColorClass?: string;
        backgroundPatternClass?: string;
        lockOnBlur?: boolean;
        timeLimit?: number;
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
    } | null;
    updateUrl: string;
    publicUrl: string;
};

export type QuestionTypeOption = {
    value: QuestionType;
    group: string;
    icon: Component;
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
    isPublished: boolean;
    isTrashed: boolean;
};

export type QuizFolder = {
    id: number;
    name: string;
    formsCount: number;
    updateUrl: string;
    deleteUrl: string;
};
