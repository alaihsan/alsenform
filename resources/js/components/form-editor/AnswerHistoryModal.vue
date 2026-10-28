<script setup lang="ts">
import axios from 'axios';
import { History, ShieldAlert, ShieldCheck, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

type DescribedAnswer = { number: number; title: string; answer: string };
type AnswerHistory = {
    receipt: string | null;
    receiptIntact: boolean | null;
    submittedAt: string | null;
    submitAttempts: number | null;
    maxSyncDelaySeconds: number | null;
    final: { answered: number; answers: DescribedAnswer[] };
    snapshots: { id: number; time: string | null; answered: number; answers: DescribedAnswer[] }[];
};

/*
 * What the server had of one student's answers during the exam (a copy every few minutes) and what
 * was handed in, with the receipt the student got: to look into "my answers are gone" with facts.
 */
const props = defineProps<{
    url: string;
    name: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const history = ref<AnswerHistory | null>(null);
const loadError = ref('');
const openedSnapshot = ref<number | 'final' | null>(null);

onMounted(async () => {
    try {
        history.value = (await axios.get(props.url, { timeout: 15000 })).data;
    } catch {
        loadError.value = 'Riwayat jawaban belum dapat dimuat. Coba lagi.';
    }
});

const toggle = (id: number | 'final') => (openedSnapshot.value = openedSnapshot.value === id ? null : id);
const delayLabel = (seconds: number) => (seconds >= 60 ? `${Math.round(seconds / 60)} menit` : `${seconds} detik`);
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="answer-history-title"
    >
        <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <History class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 id="answer-history-title" class="text-base font-extrabold text-slate-900">Riwayat Jawaban · {{ name }}</h2>
                        <p class="text-xs text-slate-500">
                            Salinan jawaban yang tersimpan di server selama ujian, tiap ±5 menit, dan jawaban yang dikirim.
                        </p>
                    </div>
                </div>
                <button type="button" class="rounded-full p-2 text-slate-400 hover:bg-slate-100" aria-label="Tutup" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <p v-if="loadError" class="mt-4 text-sm font-semibold text-rose-700" role="alert">{{ loadError }}</p>
            <p v-else-if="!history" class="mt-4 text-sm text-slate-500">Memuat riwayat...</p>

            <template v-else>
                <div
                    v-if="history.receipt"
                    :class="[
                        'mt-4 flex items-start gap-2 rounded-2xl border px-4 py-3 text-sm',
                        history.receiptIntact ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900',
                    ]"
                >
                    <ShieldCheck v-if="history.receiptIntact" class="mt-0.5 h-4 w-4 shrink-0" />
                    <ShieldAlert v-else class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Bukti kirim <strong class="select-all font-mono tracking-wider">{{ history.receipt }}</strong>
                        <span v-if="history.receiptIntact">: jawaban yang tersimpan masih sama dengan yang dikirim siswa.</span>
                        <span v-else>: jawaban yang tersimpan sudah berubah di luar aplikasi sejak dikirim.</span>
                        Cocokkan dengan kode yang tampil di layar siswa setelah mengirim.
                    </p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="font-bold uppercase tracking-wider text-slate-500">Dikirim</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">{{ history.submittedAt }}</dd>
                    </div>
                    <div v-if="history.submitAttempts" class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="font-bold uppercase tracking-wider text-slate-500">Percobaan kirim</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">{{ history.submitAttempts }}x</dd>
                    </div>
                    <div v-if="history.maxSyncDelaySeconds !== null" class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="font-bold uppercase tracking-wider text-slate-500">Jeda simpan terlama</dt>
                        <dd :class="['mt-0.5 font-semibold', history.maxSyncDelaySeconds > 30 ? 'text-amber-700' : 'text-slate-800']">
                            {{ delayLabel(history.maxSyncDelaySeconds) }}
                        </dd>
                    </div>
                </dl>

                <ol class="mt-4 space-y-2">
                    <li v-for="snapshot in history.snapshots" :key="snapshot.id" class="rounded-xl border border-slate-200">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                            @click="toggle(snapshot.id)"
                        >
                            <span class="font-mono font-semibold tabular-nums text-slate-700">{{ snapshot.time }}</span>
                            <span class="text-xs text-slate-500">{{ snapshot.answered }} soal terjawab di server</span>
                        </button>
                        <ul v-if="openedSnapshot === snapshot.id" class="border-t border-slate-100 px-3 py-2 text-xs text-slate-700">
                            <li v-for="answer in snapshot.answers" :key="answer.number" class="py-0.5">
                                <b>{{ answer.number }}.</b> <span class="text-slate-500">{{ answer.title }}</span> → {{ answer.answer }}
                            </li>
                        </ul>
                    </li>
                    <li class="rounded-xl border border-indigo-200 bg-indigo-50/40">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                            @click="toggle('final')"
                        >
                            <span class="font-bold text-indigo-800">Jawaban yang dikirim</span>
                            <span class="text-xs text-indigo-700">{{ history.final.answered }} soal terjawab</span>
                        </button>
                        <ul v-if="openedSnapshot === 'final'" class="border-t border-indigo-100 px-3 py-2 text-xs text-slate-700">
                            <li v-for="answer in history.final.answers" :key="answer.number" class="py-0.5">
                                <b>{{ answer.number }}.</b> <span class="text-slate-500">{{ answer.title }}</span> → {{ answer.answer }}
                            </li>
                        </ul>
                    </li>
                </ol>
                <p v-if="!history.snapshots.length" class="mt-2 text-xs text-slate-500">
                    Belum ada salinan selama ujian (jawaban dikirim sebelum fitur ini ada, atau penyimpanan otomatis dimatikan).
                </p>
            </template>
        </section>
    </div>
</template>
