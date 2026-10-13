<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ExternalLink, GraduationCap, Plus, RefreshCw, Sparkles, UserRound, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type RemedialStudent = {
    key: string;
    userId: number | null;
    hasAccount: boolean;
    name: string;
    nis: string;
    kelas: string;
    grade: number;
    attempts: number;
    passed: boolean;
    wrong: string[];
    remedialGrade: number | null;
    enrichmentGrade: number | null;
    finalGrade: number;
    assignedTo: string[];
};

export type RemedialOverview = {
    kkm: number;
    students: RemedialStudent[];
    followUps: {
        id: number;
        kind: 'remedial' | 'pengayaan';
        title: string;
        editUrl: string;
        isPublished: boolean;
        questionCount: number;
        participants: number;
        submitted: number;
        createdAt: string | null;
    }[];
    topicOf: Record<string, string>;
    bankPerTopic: Record<string, number>;
    enrichmentCount: number;
    createUrl: string;
    kkmSettingsUrl: string;
};

type Kind = 'remedial' | 'pengayaan';

/*
 * Remedial & Pengayaan add-on, on the Responses tab: the students below the KKM and those who
 * passed (best attempt), their follow-up grades, and the remedial and enrichment exams to make.
 */
const props = defineProps<{
    overview: RemedialOverview;
}>();

const formatGrade = (value: number) => value.toLocaleString('id-ID', { maximumFractionDigits: 2 });

const belowKkm = computed(() => props.overview.students.filter((student) => !student.passed));
const passed = computed(() => props.overview.students.filter((student) => student.passed));
const groups = computed(() => [
    { kind: 'remedial' as Kind, title: 'Belum Tuntas', students: belowKkm.value, empty: 'Semua siswa mencapai KKM.', action: 'Buat ujian remedial' },
    {
        kind: 'pengayaan' as Kind,
        title: 'Tuntas',
        students: passed.value,
        empty: 'Belum ada siswa yang mencapai KKM.',
        action: 'Buat ujian pengayaan',
    },
]);
const passedAfterRemedial = computed(
    () => belowKkm.value.filter((student) => student.remedialGrade !== null && student.remedialGrade >= props.overview.kkm).length,
);

/* The dialog that makes a follow-up exam. */
const dialogKind = ref<Kind | null>(null);
const chosenKeys = ref<string[]>([]);
const source = ref('');
const title = ref('');
const isCreating = ref(false);
const createError = ref('');

const groupOf = (kind: Kind) => (kind === 'remedial' ? belowKkm.value : passed.value);
const dialogStudents = computed(() => (dialogKind.value ? groupOf(dialogKind.value) : []));
const chosenStudents = computed(() => dialogStudents.value.filter((student) => chosenKeys.value.includes(student.key)));
const choosesGuests = computed(() => chosenStudents.value.some((student) => !student.hasAccount));
const allChosen = computed(() => dialogStudents.value.length > 0 && chosenStudents.value.length === dialogStudents.value.length);

/* How many questions each source gives for the chosen students (as the server counts them). */
const wrongIds = computed(() => [...new Set(chosenStudents.value.flatMap((student) => student.wrong))]);
const sameTopicCount = computed(() => {
    const needed: Record<string, number> = {};
    for (const id of wrongIds.value) {
        const topic = props.overview.topicOf[id];
        if (topic) {
            needed[topic] = (needed[topic] ?? 0) + 1;
        }
    }
    return Object.entries(needed).reduce((total, [topic, count]) => total + Math.min(count, props.overview.bankPerTopic[topic] ?? 0), 0);
});

const sources = computed(() =>
    dialogKind.value === 'remedial'
        ? [
              {
                  value: 'wrong',
                  label: 'Soal yang dijawab salah',
                  hint: 'Soal ujian ini yang salah dijawab siswa terpilih.',
                  count: wrongIds.value.length,
              },
              {
                  value: 'topic',
                  label: 'Soal lain setopik dari Bank Soal',
                  hint: sameTopicCount.value
                      ? 'Soal baru dengan topik yang sama, sebanyak soal yang salah per topik.'
                      : 'Belum ada: beri label topik pada soal ujian ini dan soal lain di Bank Soal.',
                  count: sameTopicCount.value,
              },
              { value: 'empty', label: 'Kosong, saya tulis sendiri', hint: 'Ujian tanpa soal, untuk diisi di editor.', count: null },
          ]
        : [
              {
                  value: 'enrichment',
                  label: 'Soal sulit setopik dari Bank Soal',
                  hint: props.overview.enrichmentCount
                      ? 'Soal bertopik sama berlabel sulit (lalu sedang), paling banyak 10.'
                      : 'Belum ada: beri label topik dan tingkat kesulitan di Bank Soal.',
                  count: props.overview.enrichmentCount,
              },
              { value: 'empty', label: 'Kosong, saya tulis sendiri', hint: 'Ujian tanpa soal, untuk diisi di editor.', count: null },
          ],
);

const openDialog = (kind: Kind) => {
    dialogKind.value = kind;
    chosenKeys.value = groupOf(kind).map((student) => student.key);
    title.value = '';
    createError.value = '';
    source.value = sources.value.find((option) => option.count === null || option.count > 0)?.value ?? 'empty';
};

const closeDialog = () => {
    if (!isCreating.value) {
        dialogKind.value = null;
    }
};

const toggleAll = () => {
    chosenKeys.value = allChosen.value ? [] : dialogStudents.value.map((student) => student.key);
};

const selectedSource = computed(() => sources.value.find((option) => option.value === source.value));
const canCreate = computed(
    () => chosenStudents.value.length > 0 && !!selectedSource.value && (selectedSource.value.count === null || selectedSource.value.count > 0),
);

/** Make the exam (a draft) and open it in the editor. */
const createFollowUp = async () => {
    if (!dialogKind.value || !canCreate.value || isCreating.value) {
        return;
    }
    isCreating.value = true;
    createError.value = '';
    try {
        const response = await axios.post(props.overview.createUrl, {
            kind: dialogKind.value,
            students: chosenStudents.value.map((student) => student.key),
            source: source.value,
            title: title.value.trim() || null,
        });
        router.visit(response.data.editUrl);
    } catch (exception: unknown) {
        const data = (exception as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
        createError.value = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Ujian belum dapat dibuat. Coba lagi.';
        isCreating.value = false;
    }
};
</script>

<template>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white" aria-labelledby="remedial-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <GraduationCap class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h3 id="remedial-heading" class="text-base font-extrabold text-slate-900">Remedial & Pengayaan</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        KKM {{ formatGrade(overview.kkm) }} (<a :href="overview.kkmSettingsUrl" class="font-semibold text-indigo-600 hover:underline"
                            >ubah di Profil</a
                        >) · memakai nilai terbaik tiap siswa
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-bold">
                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">Belum tuntas {{ belowKkm.length }}</span>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">Tuntas {{ passed.length }}</span>
                <span v-if="passedAfterRemedial" class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700"
                    >Tuntas setelah remedial {{ passedAfterRemedial }}</span
                >
            </div>
        </div>

        <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
            <div v-for="group in groups" :key="group.kind" class="flex flex-col rounded-2xl border border-slate-200">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3.5 py-2.5">
                    <p :class="['text-sm font-extrabold', group.kind === 'remedial' ? 'text-rose-700' : 'text-emerald-700']">
                        {{ group.title }} <span class="font-semibold text-slate-400">({{ group.students.length }})</span>
                    </p>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        {{ group.kind === 'remedial' ? 'Asli → Remedial' : 'Asli · Pengayaan' }}
                    </p>
                </div>
                <ul v-if="group.students.length" class="max-h-72 flex-1 divide-y divide-slate-100 overflow-y-auto">
                    <li v-for="student in group.students" :key="student.key" class="flex items-center gap-3 px-3.5 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ student.name }}
                                <span
                                    v-if="!student.hasAccount"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500"
                                    >tamu</span
                                >
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ student.kelas !== '-' ? `Kelas ${student.kelas}` : 'Tanpa kelas' }}
                                <template v-if="student.attempts > 1"> · {{ student.attempts }} percobaan</template>
                                <template v-if="student.assignedTo.includes(group.kind)"> · sudah dipilih</template>
                                <template v-if="group.kind === 'remedial' && student.remedialGrade !== null">
                                    · nilai akhir {{ formatGrade(student.finalGrade) }}</template
                                >
                            </p>
                        </div>
                        <div class="shrink-0 text-right text-sm tabular-nums">
                            <span :class="['font-bold', student.passed ? 'text-emerald-700' : 'text-rose-700']">{{
                                formatGrade(student.grade)
                            }}</span>
                            <template v-if="group.kind === 'remedial' && student.remedialGrade !== null">
                                <span class="text-slate-400"> → </span>
                                <span
                                    :class="['font-bold', student.remedialGrade >= overview.kkm ? 'text-emerald-700' : 'text-rose-700']"
                                    :title="`Nilai akhir ${formatGrade(student.finalGrade)} (paling tinggi KKM)`"
                                    >{{ formatGrade(student.remedialGrade) }}</span
                                >
                            </template>
                            <template v-if="group.kind === 'pengayaan' && student.enrichmentGrade !== null">
                                <span class="text-slate-400"> · </span>
                                <span class="font-bold text-indigo-700">{{ formatGrade(student.enrichmentGrade) }}</span>
                            </template>
                        </div>
                    </li>
                </ul>
                <p v-else class="flex-1 px-3.5 py-6 text-center text-sm text-slate-500">{{ group.empty }}</p>
                <div class="border-t border-slate-100 p-3">
                    <button
                        type="button"
                        :disabled="!group.students.length"
                        :class="[
                            'inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-40',
                            group.kind === 'remedial' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700',
                        ]"
                        @click="openDialog(group.kind)"
                    >
                        <Plus class="h-3.5 w-3.5" /> {{ group.action }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="overview.followUps.length" class="border-t border-slate-100 px-4 py-4 sm:px-5">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Ujian tindak lanjut</p>
            <ul class="mt-2 space-y-2">
                <li
                    v-for="followUp in overview.followUps"
                    :key="followUp.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-slate-200 px-3.5 py-2.5"
                >
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase',
                            followUp.kind === 'remedial' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700',
                        ]"
                        >{{ followUp.kind }}</span
                    >
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ followUp.title }}</span>
                    <span class="text-xs text-slate-500">
                        {{ followUp.isPublished ? 'Terbit' : 'Draf' }} · {{ followUp.questionCount }} soal ·
                        {{
                            followUp.participants
                                ? `${followUp.submitted}/${followUp.participants} siswa mengerjakan`
                                : `${followUp.submitted} siswa mengerjakan`
                        }}
                    </span>
                    <Link :href="followUp.editUrl" class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                        Buka <ExternalLink class="h-3 w-3" />
                    </Link>
                </li>
            </ul>
        </div>
        <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:px-5">
            Nilai remedial dan pengayaan tercatat di ujiannya masing-masing; nilai asli tidak berubah. Laporan Excel memuat lembar "Remedial &
            Pengayaan" dengan nilai akhir remedial paling tinggi sama dengan KKM.
        </p>

        <Teleport to="body">
            <div v-if="dialogKind" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" @click.self="closeDialog">
                <section
                    class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="follow-up-heading"
                >
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-xl',
                                    dialogKind === 'remedial' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600',
                                ]"
                            >
                                <component :is="dialogKind === 'remedial' ? RefreshCw : Sparkles" class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 id="follow-up-heading" class="text-lg font-extrabold text-slate-900">
                                    {{ dialogKind === 'remedial' ? 'Buat ujian remedial' : 'Buat ujian pengayaan' }}
                                </h2>
                                <p class="text-xs text-slate-500">Dibuat sebagai draf; periksa soalnya lalu terbitkan.</p>
                            </div>
                        </div>
                        <button type="button" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Tutup" @click="closeDialog">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                        <fieldset>
                            <div class="flex items-center justify-between">
                                <legend class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
                                    Siswa ({{ chosenStudents.length }}/{{ dialogStudents.length }})
                                </legend>
                                <button type="button" class="text-xs font-bold text-indigo-600 hover:text-indigo-800" @click="toggleAll">
                                    {{ allChosen ? 'Kosongkan' : 'Pilih semua' }}
                                </button>
                            </div>
                            <div class="mt-2 max-h-48 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                                <label
                                    v-for="student in dialogStudents"
                                    :key="student.key"
                                    class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50"
                                >
                                    <input
                                        v-model="chosenKeys"
                                        type="checkbox"
                                        :value="student.key"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                    />
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-800">
                                        {{ student.name }}
                                        <span class="text-xs text-slate-500">{{ student.kelas !== '-' ? `· ${student.kelas}` : '' }}</span>
                                        <span v-if="!student.hasAccount" class="ml-1 text-[10px] font-bold uppercase text-slate-400">tamu</span>
                                    </span>
                                    <span class="text-sm font-bold tabular-nums text-slate-600">{{ formatGrade(student.grade) }}</span>
                                </label>
                            </div>
                            <p v-if="choosesGuests" class="mt-2 flex items-start gap-1.5 text-xs text-amber-700">
                                <UserRound class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                Siswa tamu (tanpa akun) tidak dapat dibatasi: ujian akan terbuka bagi siapa pun yang punya tautannya.
                            </p>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Sumber soal</legend>
                            <div class="mt-2 space-y-2">
                                <label
                                    v-for="option in sources"
                                    :key="option.value"
                                    :class="[
                                        'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition',
                                        source === option.value ? 'border-indigo-300 bg-indigo-50/60' : 'border-slate-200 hover:bg-slate-50',
                                        option.count === 0 ? 'cursor-not-allowed opacity-60' : '',
                                    ]"
                                >
                                    <input
                                        v-model="source"
                                        type="radio"
                                        name="follow-up-source"
                                        :value="option.value"
                                        :disabled="option.count === 0"
                                        class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-slate-800">{{ option.label }}</span>
                                        <span class="block text-xs text-slate-500">{{ option.hint }}</span>
                                    </span>
                                    <span
                                        v-if="option.count !== null"
                                        class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600"
                                        >{{ option.count }} soal</span
                                    >
                                </label>
                            </div>
                        </fieldset>

                        <div>
                            <label for="follow-up-title" class="text-xs font-extrabold uppercase tracking-wider text-slate-500"
                                >Judul (opsional)</label
                            >
                            <input
                                id="follow-up-title"
                                v-model="title"
                                type="text"
                                maxlength="255"
                                :placeholder="dialogKind === 'remedial' ? 'Remedial: judul ujian ini' : 'Pengayaan: judul ujian ini'"
                                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            />
                        </div>

                        <p v-if="createError" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800" role="alert">
                            {{ createError }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
                            :disabled="isCreating"
                            @click="closeDialog"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canCreate || isCreating"
                            @click="createFollowUp"
                        >
                            <RefreshCw v-if="isCreating" class="h-4 w-4 animate-spin" />
                            {{ isCreating ? 'Membuat...' : 'Buat ujian (draf)' }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </section>
</template>
