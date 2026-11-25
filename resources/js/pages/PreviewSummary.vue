<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Clock, Eye, EyeOff, LogIn, LogOut, Send } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Moment {
    iso: string;
    /** "Senin, 24 November 2026" */
    date: string;
    /** "08:15:03" */
    time: string;
    /** "WIB" */
    timezone: string;
}

/**
 * After "Tutup pratinjau": when the teacher opened the preview of the exam, when they closed it,
 * and how long they looked at it (ExamPreviewController).
 */
const props = defineProps<{
    quizForm: {
        title: string;
        editUrl: string;
        previewUrl: string;
    };
    preview: {
        openedAt: Moment;
        closedAt: Moment;
        seconds: number;
        /** "17 menit 37 detik" */
        duration: string;
        /** The preview was handed in with "Kirim" before it was closed. */
        handedIn: boolean;
    };
}>();

/** Opened from the editor's Pratinjau button (a new tab): going back closes this tab. */
const isOpenedFromEditor = ref(false);

onMounted(() => {
    try {
        isOpenedFromEditor.value = window.opener !== null && !window.opener.closed;
    } catch {
        isOpenedFromEditor.value = false;
    }
});

const backToEditor = (): void => {
    if (isOpenedFromEditor.value) {
        window.close();
    }
    // Still here (a tab the browser will not close): open the editor in this tab.
    window.setTimeout(() => window.location.assign(props.quizForm.editUrl), 200);
};
</script>

<template>
    <AlsenformLayout>
        <Head title="Pratinjau ditutup" />

        <main class="flex justify-center px-4 py-10 sm:px-6 sm:py-16">
            <section
                class="w-full max-w-xl rounded-[2rem] border border-indigo-50 bg-white p-6 shadow-[0_10px_0_theme(colors.indigo.100)] sm:p-10"
                data-testid="preview-summary"
            >
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-[0_5px_0_theme(colors.indigo.200)]">
                        <EyeOff class="h-8 w-8" />
                    </div>
                    <p class="mt-6 text-xs font-black uppercase tracking-[0.2em] text-indigo-500">Pratinjau selesai</p>
                    <h1 class="mt-2 text-2xl font-black leading-tight text-slate-900 sm:text-3xl">Pratinjau ditutup</h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-[15px]">{{ quizForm.title }}</p>
                </div>

                <div class="mt-7 rounded-3xl border border-indigo-100 bg-indigo-50/60 px-5 py-4 text-center">
                    <p class="flex items-center justify-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-indigo-700">
                        <Clock class="h-3.5 w-3.5" /> Durasi melihat pratinjau
                    </p>
                    <p class="mt-1 text-3xl font-black tabular-nums text-slate-900" data-testid="preview-duration">{{ preview.duration }}</p>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 px-4 py-3">
                        <dt class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <LogIn class="h-3.5 w-3.5 text-emerald-600" /> Dibuka
                        </dt>
                        <dd class="mt-1">
                            <time :datetime="preview.openedAt.iso" data-testid="preview-opened-at">
                                <span class="block font-mono text-lg font-black tabular-nums text-slate-900">
                                    {{ preview.openedAt.time }} <span class="text-xs font-bold text-slate-500">{{ preview.openedAt.timezone }}</span>
                                </span>
                                <span class="block text-xs font-semibold text-slate-500">{{ preview.openedAt.date }}</span>
                            </time>
                        </dd>
                    </div>
                    <div class="rounded-2xl border border-slate-200 px-4 py-3">
                        <dt class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <LogOut class="h-3.5 w-3.5 text-rose-600" /> Ditutup
                        </dt>
                        <dd class="mt-1">
                            <time :datetime="preview.closedAt.iso" data-testid="preview-closed-at">
                                <span class="block font-mono text-lg font-black tabular-nums text-slate-900">
                                    {{ preview.closedAt.time }} <span class="text-xs font-bold text-slate-500">{{ preview.closedAt.timezone }}</span>
                                </span>
                                <span class="block text-xs font-semibold text-slate-500">{{ preview.closedAt.date }}</span>
                            </time>
                        </dd>
                    </div>
                </dl>

                <p
                    :class="[
                        'mt-4 flex items-start gap-2 rounded-2xl px-4 py-3 text-sm font-semibold',
                        preview.handedIn ? 'border border-amber-200 bg-amber-50 text-amber-900' : 'bg-slate-50 text-slate-600',
                    ]"
                >
                    <Send v-if="preview.handedIn" class="mt-0.5 h-4 w-4 shrink-0" />
                    <Eye v-else class="mt-0.5 h-4 w-4 shrink-0" />
                    <span v-if="preview.handedIn">Jawaban pratinjau ini sudah dikirim, jadi tercatat di daftar jawaban ujian.</span>
                    <span v-else>Jawaban yang diisi saat pratinjau tidak dikirim dan tidak masuk hasil ujian.</span>
                </p>

                <div class="mt-7 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-center">
                    <!-- A full page load: a new preview starts with its own time. -->
                    <a
                        :href="quizForm.previewUrl"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        data-testid="preview-again"
                    >
                        <Eye class="h-4 w-4" />
                        Buka pratinjau lagi
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-[0_4px_0_theme(colors.indigo.800)] transition hover:-translate-y-px hover:bg-indigo-700"
                        data-testid="back-to-editor"
                        @click="backToEditor"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Kembali ke editor
                    </button>
                </div>
            </section>
        </main>
    </AlsenformLayout>
</template>
