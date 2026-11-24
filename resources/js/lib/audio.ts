/**
 * Sound in questions, choices and spoken answers: recording in the browser, and one sound at a
 * time on a page. The server compresses what is sent (App\Services\AudioCompressor).
 */

/** The sound of a choice, or of a question (as a media item of type "audio"). */
export type AudioClip = {
    url: string;
    /** Seconds, measured by the server: shown before the sound is loaded. */
    duration?: number | null;
    size?: number | null;
};

/** Sound files the server takes (App\Services\AudioCompressor::EXTENSIONS). */
export const AUDIO_ACCEPT = 'audio/*,.mp3,.m4a,.aac,.wav,.ogg,.oga,.opus,.webm,.weba,.flac,.amr,.3gp,.3gpp';

/** Recording formats, best first: Opus is small and clear; Safari records MP4 (AAC). */
const RECORDER_TYPES = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg'];

/** Bits per second of a recording: plenty for a voice, and the upload stays small. */
export const RECORDER_BITS_PER_SECOND = 48000;

/**
 * Whether this page may record from the microphone. Browsers allow it on https and on localhost
 * only: on the school's http address teachers and students pick or record a file instead.
 */
export const canRecordAudio = (): boolean =>
    typeof window !== 'undefined' &&
    window.isSecureContext === true &&
    typeof navigator !== 'undefined' &&
    typeof navigator.mediaDevices?.getUserMedia === 'function' &&
    typeof window.MediaRecorder === 'function';

/** The best recording format the browser has, or '' to let it choose. */
export const recorderMimeType = (): string => {
    if (typeof window === 'undefined' || typeof window.MediaRecorder?.isTypeSupported !== 'function') {
        return '';
    }

    return RECORDER_TYPES.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
};

/** A file name for a recording of the given type. */
export const recordingFileName = (mimeType: string): string => {
    const extension = mimeType.includes('mp4') ? 'm4a' : mimeType.includes('ogg') ? 'ogg' : 'webm';

    return `rekaman-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${extension}`;
};

/** What went wrong when the microphone could not be opened, in the words of the page. */
export const microphoneErrorMessage = (error: unknown): string => {
    const name = (error as { name?: string } | null)?.name ?? '';
    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return 'Izin mikrofon ditolak. Izinkan mikrofon untuk halaman ini di pengaturan browser, atau pilih file suara.';
    }
    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return 'Mikrofon tidak ditemukan. Pasang mikrofon atau pilih file suara.';
    }
    if (name === 'NotReadableError') {
        return 'Mikrofon sedang dipakai aplikasi lain. Tutup aplikasi itu, lalu coba lagi.';
    }

    return 'Mikrofon tidak bisa dibuka. Coba lagi, atau pilih file suara.';
};

/** 75 → "1:15". */
export const formatAudioTime = (seconds: number | null | undefined): string => {
    if (seconds === null || seconds === undefined || !Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }
    const whole = Math.floor(seconds);

    return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
};

/** 90 → "90 detik", 120 → "2 menit". */
export const durationLabel = (seconds: number): string =>
    seconds < 60 || seconds % 60 !== 0 ? `${seconds} detik` : `${seconds / 60} menit`;

export const formatAudioSize = (bytes: number | null | undefined): string => {
    if (!bytes || bytes <= 0) {
        return '';
    }

    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

/*
 * One sound at a time: starting a sound pauses the one that was playing, so two never mix.
 */
let playing: HTMLAudioElement | null = null;

export const claimPlayback = (element: HTMLAudioElement): void => {
    if (playing && playing !== element && !playing.paused) {
        playing.pause();
    }
    playing = element;
};

export const releasePlayback = (element: HTMLAudioElement): void => {
    if (playing === element) {
        playing = null;
    }
};

/*
 * Batas putar (like the listening part of a TOEFL test): how many times a sound was started on
 * this device, kept per attempt so a reload does not give the plays back.
 */
const PLAYS_PREFIX = 'falcon-audio-plays:';

export const playsUsed = (key: string): number => {
    try {
        return Math.max(0, Number(window.localStorage.getItem(PLAYS_PREFIX + key)) || 0);
    } catch {
        return 0;
    }
};

export const recordPlay = (key: string, plays: number): void => {
    try {
        window.localStorage.setItem(PLAYS_PREFIX + key, String(plays));
    } catch {
        // Private mode or full storage: the count holds for this page only.
    }
};
